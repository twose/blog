---
title: "在Swoole中实现MySQL存储过程"
date: 2018-07-16 23:59:58
tags: [swoole, mysql]
---

大概是在一个月前了...那时候刚开始给swoole contribute代码, 初生牛犊, 修了不少小bug, 最后某位仁兄贴了个issue说swoole的mysql-client搞不掂存储过程, 当时我想想, 存储过程这东西实在没什么用, 甚至在很多大公司开发手册上是禁止使用的(某里粑粑), 具体的 [**为什么不要使用存储过程**](https://www.zhihu.com/question/57545650) 戳这里, 但是考虑到一个作为一个底层扩展, 各种用户都有, rango就给我分配了这个任务, 于是我就马上进行了一番研究.



其实内容当时在PR里都贴了, https://github.com/swoole/swoole-src/pull/1688, 现在在博客补个票



完整的MySQL存储过程支持

------

做了以下几件事:

## fetch mode

一开始先想着和PDO一样给Swoole做一个fetch模式

```php
['fetch_mode' => true] //连接配置里加入这个
```

```php
$stmt = $db->prepare('SELECT `id` FROM `userinfo` LIMIT 2');
$stmt->execute(); // true = success
$stmt->fetch(); // result-set array 1
$stmt->fetch(); // result-set array 2
```



<!--more-->



## 分离client和statement

加了一个 `MYSQL_RESPONSE_BUFFER` 宏, 处理了一些代码分离了client和statement的buffer

并给statement结构上也挂了一个result的zval指针

```C
typedef struct
{
    ...
    swString *buffer; /* save the mysql multi responses data */
    zval *result; /* save the zval array result */
} mysql_statement;
```

这样就可以实现以下代码:

```php
$stmt1 = $db->prepare('SELECT * FROM ckl LIMIT 1');
$stmt1->execute();
$stmt2 = $db->prepare('SELECT * FROM ckl LIMIT 2');
$stmt2->execute();
$stmt1->fetchAll();
$stmt2->fetchAll();
```

因为现在result是挂在statement上的, 和client分离干净, 就不会因为这样的写法产生错误

当然这并没有多大用, **主要还是为了后面处理多响应多结果集**



## 分离mysql_parse_response

这样就就可以在除了`onRead`回调之外的别的地方复用这个方法, 处理多结果集了



## 存储过程

存储过程会返回多个响应, 如果和swoole之前的设计一样, 一次性全返回是不太现实的

PDO和MySQLi的设计都是用一个 next 方法来切换到下一个响应

刚开始是想做一个链表存储多个响应, 很快就发现并不需要

所以首先做了一个 [`mysql_is_over`](https://github.com/twose/swoole-src/blob/13ff4ff8ac2723649f05b69f337f49557cf74546/swoole_mysql.c#L1478)方法

它用来**校验MySQL包的完整性**, 这是swoole以前没有的, 所以在之前的PR后虽然可以使用存储过程, 但是并不能每次都收到完整的响应包, 第一次没收到的包会被丢弃

然后说一下几个注意点

1. MySQL协议决定了并不能倒着检查status flag, 我们必须把每个包的包头都扫描一遍, 通过package length快速扫描到最后一个已接收的包体, 这里只是每次只是检查每个包前几个字节, 消耗不大
2. MySQL其它包体中的 `MYSQL_SERVER_MORE_RESULTS_EXISTS` 的标志位并不准确, 不可采信, 只有`eof`和`ok`包中的是准确的 (这里一定要注意)
3. 在存储过程中执行一个耗时操作的话, recv一次性收不完, 而且会等很久, 这时候需要return等下一次onRead触发(之前的代码里是continue阻塞), 这就不得不在client上加一个check_offset来保存上次完整性校验的位置, 从上个位置开始继续校验后续的MySQL包是否完整, 节省时间
4. 存储过程中遇到错误(error响应)就可以直接终止接收了
5. 在PHP7的zval使用上踩了点坑, 现在理解了, 幸好有鸟哥的文章[zval](https://github.com/laruence/php7-internal/blob/master/zval.md)给我解惑..

**校验包的完整性直到所有数据接收完毕**

(分离了client和statement后, execute获取的数据是被存在`statement->buffer`里而不是`client->buffer`)

**这时候onRead中只会解析第一个响应的结果, 并置到statement对象上, 而剩下的数据仍在buffer中, 并等待nextResult来推动offset解析下一个, 可以说是懒解析了, 有时候会比一次性解析所有响应划算, 而且我们可以清楚的知道每一次nextResult切换前后, 对应的affected_rows和insert_id的值(如果一次性读完, 只能知道最后的)**

最后效果就是以下代码

```php
$stmt = $db->prepare('CALL reply(?)');
$stmt->execute(['hello mysql!']); // true
do {
    $res = $stmt->fetchAll();
    var_dump($res);
} while ($stmt->nextResult());
```

非fetch_mode模式下这么写

```php
$stmt = $db->prepare('CALL reply(?)');
$res = $stmt->execute(['hello mysql!']); // the first result
do {
    var_dump($res);
} while ($res = $stmt->nextResult());
```

比较巧妙的是nextResult推到最后一个response_ok包的时候会返回null, while循环终止, 我们就可以在循环后读取ok包的affected_rows, 如果最后存储过程最后一个语句是insert成功, 这里会显示1

```php
var_dump($stmt->affected_rows); //1
```



最近忙起来真的是很少时间能写文章了, 慢慢补吧.