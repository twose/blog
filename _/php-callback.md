---
title: "[转] 4种PHP回调函数"
date: 2018-01-04 05:47:33
tags: php
---

> 以Swoole服务事件回调为例

## 匿名函数

```Php
$server->on('Request', function ($req, $resp) {
    echo "hello world";
});
```

## 类静态方法

```Php
class A {
    static function test($req, $resp){
        echo "hello world";
    }
}
$server->on('Request', 'A::Test');
$server->on('Request', array('A', 'Test'));
```

## 函数

```php
function my_onRequest($req, $resp){
    echo "hello world";
}
$server->on('Request', 'my_onRequest');
```

## 对象方法

```PHP
class A {
    function test($req, $resp){
        echo "hello world";
    }
}

$object = new A();
$server->on('Request', array($object, 'test'));
```