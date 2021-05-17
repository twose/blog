---
title: 如何在PDO查询中返回强类型
date: 2017-12-30 13:11:36
tags: [pdo,mysql]
categories: [php,mysql]
---

>  有些驱动不支持或有限度地支持本地预处理。使用此设置强制PDO总是模拟预处理语句（如果为 TRUE ），或试着使用本地预处理语句（如果为 FALSE）。如果驱动不能成功预处理当前查询，它将总是回到模拟预处理语句上。 需要 bool 类型。

PDO::ATTR_EMULATE_PREPARES 启用或禁用预处理语句的模拟。

这是之前我说的默认总是模拟prepare,因为低版本MYSQL驱动不支持prepare.
数据类型问题,在旧版本的MySQL中还真是不能解决的。它直接返回字符串给外部系统。稍微新一点的MySQL和客户端驱动可以直接内部的本地类型而不再进行内部转换为字符串了。有了这个基础，就有解决的可能了。

#### Test-code

此处用query测试证明,prepare_excute二连也是一样的

```Php
$db = new \PDO('mysql:dbname='.$options['database'].';host='.$options['host'], $options['user'], $options['password']);
$db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);//关闭预处理语句模拟
$r = ($db->query('SELECT * FROM test WHERE `id`=1 LIMIT 1', \PDO::FETCH_ASSOC))->fetch();
var_dump($r);
```
#### $result

```Php
array(2) {
  [0]=>
  int(1)
  [1]=>
  string(64) "1dfd47ed5fb0183d05157f21cab0fd8c151379f407a173190445bbd82aa5aeaa"
}
```

此外,PDO为参数绑定也提供了强类型的设定,默认传给Mysql的是string,常用的类型如下:

```Php
$data_types = [
  'NULL'    => PDO::PARAM_NULL,
  'boolean' => PDO::PARAM_BOOL,
  'integer' => PDO::PARAM_INT,
  'string'  => PDO::PARAM_STR,
]
$this->sm->bindParam(':id', $id, $data_types[getType($id)]);
```

> data_type: 使用[*PDO :: PARAM_ \** 常量](http://php.net/manual/en/pdo.constants.php)来设定参数的显式数据类型。要从存储过程返回INOUT参数，请使用按位或运算符来设置`data_type`参数的PDO :: PARAM_INPUT_OUTPUT位。