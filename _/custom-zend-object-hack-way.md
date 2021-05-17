---
title: "自定义zend_object的结构体的hack技巧"
date: 2018-07-17 00:08:00
tags: [php, zend]
---

研究这个主要是为了解决`swoole-socket`模块的一个coredump的bug, 之前swoole采用了`swoole_get/set_object`等做法来存取对应的对象, 只有socket模块使用了魔改zend_object的方法, 但是PHP7里用了比较hack的结构体技巧, 导致了一系列问题, 想魔改zend_object, 需要一番操作, 中文文档很难找到用法的, 都是一笔带过, 需要去看英文文档.

虽然只有一次提交, 但其实改了不下几十遍, 在此记录一下:

> 第一个参考文章: https://segmentfault.com/a/1190000004173452

Swoole在`socket coro`中使用了别的模块没有用到的自定义zend_object属性的技巧, 但是PHP7中它需要做额外的处理, 导致了一些问题.

### 坑1

因为 `zend_object` 在存储属性表时用了结构体 hack 的技巧，`zend_object` 尾部存储的 PHP 属性会覆盖掉后续添加进去的内部成员。所以 PHP7 的实现中必须把自己添加的成员添加到标准对象结构的前面：

```
struct custom_object {
    uint32_t something;
    // ...
    zend_object std;
};
```
不过这样也就意味着现在无法直接在 zend_object* 和 struct custom_object* 进行简单的转换了，因为两者都一个偏移分割开了。所以这个偏移量就需要被存储在对象 handler 表中的第一个元素中，这样在编译时通过 offsetof() 宏就能确定具体的偏移值

<!--more-->

----

但是现在仍不知道具体的操作方式, 只能去搜官网的英文文档等

官网有一篇从PHP5升级到PHPNG的文章中提到了这个坑

> ref: https://wiki.php.net/phpng-upgrading

Custom Objects 一节:

```C
zend_object * custom_object_new(zend_class_entry *ce TSRMLS_DC) {
     # Allocate sizeof(custom) + sizeof(properties table requirements)
     struct custom_object *intern = ecalloc(1, 
         sizeof(struct custom_object) + 
         zend_object_properties_size(ce));
     # Allocating:
     # struct custom_object {
     #    void *custom_data;
     #    zend_object std;
     # }
     # zval[ce->default_properties_count-1]
     zend_object_std_init(&intern->std, ce TSRMLS_CC);
     ...
     custom_object_handlers.offset = XtOffsetOf(struct custom_obj, std);
     custom_object_handlers.free_obj = custom_free_storage;
 
     intern->std.handlers = custom_object_handlers;
 
     return &intern->std;
}
```

对应的是swoole中的

```C
swoole_socket_coro_class_entry_ptr->create_object = swoole_socket_coro_create;

static zend_object *swoole_socket_coro_create(zend_class_entry *ce TSRMLS_DC)
{
    socket_coro *sock = ecalloc(1, sizeof(socket_coro) + zend_object_properties_size(ce));
    // 这里要给properties_size额外分配内存
    zend_object_std_init(&sock->std, ce TSRMLS_CC);
    object_properties_init(&sock->std, ce); //这是坑2加的
    sock->std.handlers = &swoole_socket_coro_handlers;

    return &sock->std;
}
```

然后我们得做一个方法和一个**`Z_SOCKET_CORO_OBJ_P`**宏来从zval或zend_object获取socket_coro

```C
static inline socket_coro * sw_socket_coro_fetch_object(zend_object *obj)
{
    return (socket_coro *) ((char *) obj - XtOffsetOf(socket_coro, std));
}

#define Z_SOCKET_CORO_OBJ_P(zv) sw_socket_coro_fetch_object(Z_OBJ_P(zv));
```

在方法里这么用

```C
socket_coro *sock = (socket_coro *) Z_SOCKET_CORO_OBJ_P(getThis());
```


### 坑2

但是这里又踩了个坑...使用自定义的create_object之后…对象属性并不会自己初始化

我发现之前的swoole socket coro压根没有errCode属性...

在zend_object里没有相关API, 好不容易又找到另一篇文章, 找到了API...

> ref: http://www.phpinternalsbook.com/classes_objects/custom_object_storage.html

在Overriding create_object一节...

```C
object_properties_init(&sock->std, ce);
```

### 坑3

之前没用过socket组件, accept会返回一个socket coro对象, 以为修好了, server端又coredump了

因为: 

在创建对象的时候，Zend并不会帮我们调用构造函数，需要我们自己显式的在object上调用__construct方法

或者做和__construct方法一样的事情

在onReadable事件里这样改

```C
if (conn >= 0)
{
    zend_object *client;
    client = swoole_socket_coro_create(swoole_socket_coro_class_entry_ptr);
    socket_coro *client_sock = (socket_coro *) sw_socket_coro_fetch_object(client);
    ZVAL_OBJ(&result, &client_sock->std);
    client_sock->fd = conn;
    client_sock->domain = sock->domain;
    client_sock->object = result;
}
```
