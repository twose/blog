---
title: "PHP内核 - ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX 分析"
date: 2018-07-15 10:38:48
tags: PHP
---

前一部分内容抄自振宇哥的博客: [旷绝一世](http://kuangjue.com/article/70), 在此基础后续扩写一部分

我们在写扩展的时候很常见的这样的宏，就比如swoole扩展中：

```
ZEND_BEGIN_ARG_INFO_EX(arginfo_swoole_server_listen, 0, 0, 3)//名字,unused,引用返回,参数个数
  ZEND_ARG_INFO(0, host)
  ZEND_ARG_INFO(0, port)
  ZEND_ARG_INFO(0, sock_type)
ZEND_END_ARG_INFO()
```
这个宏组合是用来定义函数的参数，我们不妨去跟下`ZEND_BEGIN_ARG_INFO_EX` 与`ZEND_END_ARG_INFO`的定义。
定义在zend_API.h文件中，`ZEND_BEGIN_ARG_INFO_EX`的定义为：

```C
#define ZEND_BEGIN_ARG_INFO_EX(name, _unused, return_reference, required_num_args)  \
   static const zend_internal_arg_info name[] = { \
   {(const char*)(zend_uintptr_t)(required_num_args), 0, return_reference, 0 },
```
ZEND_END_ARG_INFO的定义为：

```C
#define ZEND_ARG_INFO(pass_by_ref, name){ #name, 0, pass_by_ref, 0},
```
那么组合起来变成c代码就是
```C
static const zend_internal_arg_info arginfo_swoole_server_listen[] = { \
   {3, 0, 0, 0 },
   { host, 0, 0, 0},
   { port, 0, 0, 0},
   { sock_type, 0, 0, 0},
}
```
<!--more-->

现在看来就是定义了一个zend_internal_arg_info结构数组，在zend/zend_compile.h文件中定义：

```C
typedef struct _zend_internal_arg_info {
    const char *name;      //参数名称
    const char *class_name;  //当参数类型为类时，指定类的名称
    zend_uchar type_hint;    //参数类型是否为数组
    zend_uchar pass_by_reference;  //是否设置为引用，即&
    zend_bool allow_null;   //是否允许设置为空
    zend_bool is_variadic;//**是否为可变参数**
} zend_internal_arg_info;
```
PHP7中还加入了返回值类型声明这一新特性, 但是到目前为止, 各种扩展几乎没有添加返回值声明的意思, 但是这一特性对于IDE提示的生成非常有帮助

```C
#define ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(name, return_reference, required_num_args, type, allow_null) \
	static const zend_internal_arg_info name[] = { \
		{ (const char*)(zend_uintptr_t)(required_num_args), ZEND_TYPE_ENCODE(type, allow_null), return_reference, 0 },

#define ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO(name, type, allow_null) \
	ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(name, 0, -1, type, allow_null)
```

在ZEND API头文件中我们可以看到新添加的宏` ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX`, 还有`ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX`等等

我们可以这样使用它

```C
ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_swoole_http2_client_coro_recv, 0, 1, Swoole\\Http2\\Response, 0)
ZEND_END_ARG_INFO()
    
ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO(arginfo_swoole_http2_client_coro_balabala, _IS_BOOL, 0)
ZEND_END_ARG_INFO()
```

这样就可以为这个方法声明返回值类型了

当然, 我实际并没有这么做, 因为好像`ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX`这个宏在PHP7最初版本有[BUG](https://github.com/php/php-src/commit/141d1ba9801f742dc5d9ccd06e02b94284c4deb7), 我们可以通过git blame看到几次修复, 而且并没有看到任何扩展使用了它, 如果要使用, 需要添加一些版本判断, 实在麻烦, 而且指不定会出什么问题, 这个需求也不是特别重要, 而且全部使用它工程量挺大的, 可能需要过一阵子再考虑统一添加一下