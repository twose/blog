---
title: "PHP内核浅析: zend_read_property在键值不存在的时候究竟返回了什么?"
date: 2018-09-23 21:06:26
tags: [php, zend, swoole]
---

> 2020更新：扩展对象使用“属性”来存储东西不是一个好的行为，我们可能需要花费很大代价来阻止来自PHP用户的破坏，至于更好的存储方法，我会在未来的文章中讲到

`zend_read_property`返回了什么, 其实我从前也未深究, 它的返回值类型是一个`zval *`, 所以很理所当然的, 大家都会认为如果获取了一个不存在的属性, 它的返回值就是`NULL`.

比如`zend_hash_str_find`这个API, 它会从`HashTable`里寻找对应的bucket, 然后获取它的值, 如果这个值不存在, 就返回NULL.

而且我们清楚, 不管是`array`, 还是`object`的`properties`, 都是用`HashTable`来存储的, 那么不存在的时候返回`NULL`, 也是理所当然.

这里还要注意一点, 我所指的不存在, 是在`HashTable`里没有这个bucket, 举个例子:

```php
$foo = ['bar' => null];
var_dump(isset($foo['bar'])); // false
var_dump(array_key_exists('bar', $foo)); // true
unset($foo['bar']);
var_dump(array_key_exists('bar', $foo)); // false
```

这样可以很清楚的发现区别了, 在置一个键为`null`的时候, 实际上是在这个`bucket`上放了一个`type = null`的`zval`,  而当使用`unset`的时候, 才是真正的把这个`bucket`从`HashTable`上删去了, 也就是说这个键和存储键值的容器都不存在了. 所以`unset`真是个很暴力的连根拔除的操作.

`unset`的开销会比赋值`null`更大, 因为它删去属性的同时, 可能会触发数组结构重置, 这个问题在用`SplQueue`和`array_push/pop`对比的时候显而易见.

<!--more-->

### 错误案例

出于安全性考虑, 我曾经写过一个函数, 犯了愚蠢的错误:

```C
static sw_inline zval* sw_zend_read_property_array(zend_class_entry *class_ptr, zval *obj, const char *s, int len, int silent)
{
    zval rv, *property = zend_read_property(class_ptr, obj, s, len, silent, &rv);
    zend_uchar ztype = Z_TYPE_P(property);
    if (ztype != IS_ARRAY)
    {
        zval temp_array;
        array_init(&temp_array);
        zend_update_property(class_ptr, obj, s, len, &temp_array TSRMLS_CC);
        zval_ptr_dtor(&temp_array);
        // NOTICE: if user unset the property, this pointer will be changed
        // some objects such as `swoole_http2_request` always be writable
        if (ztype == IS_UNDEF)
        {
            property = zend_read_property(class_ptr, obj, s, len, silent, &rv);
        }
    }

    return property;
}
```

首先这个函数是用来安全地从一个object上获取一个array类型的属性, 在该属性不为array类型的时候, 更新为一个空数组, 然后再返回该属性的指针.

因为在底层常常会有类似这样的操作

```C
zval *property = zend_read_property(ce, object, ZEND_STRL("headers"), 1);
add_assoc_string(property, "foo", "bar");
```

一般属性都是被定义好的且初始化好的, 但难免有开发者会在PHP代码中改变它, 比如我自己就这么做了, 在某个清理方法中把`$request->headers = null`, 然后底层读取出了一个null的zval, 调用`add_assoc_string`的时候, 把这个属性当做了array, 就产生了coredump. 所以弄一个包含检查的内联函数来安全的获取指定类型的属性, 还是很有必要的.

在这个函数中, 我为了节省一次`zend_read_property`的开销, 判断了前一次读出属性的类型, 在我的潜意识里, 获取到了标记为UNDEF的zval, 前后指针会变化, 所以我判断了它是IS_UNDEF的时候才重新读一次属性. 因为已存在的属性, 就算更新它的值, 它的指针(即bucket的位置)也不会改变.

我常常是一个实战派, 当时我用LLDB跟踪验证了一下, 不论在何种情况, 前后指针都没有变化, 这是一个安全的方式, 于是我就放心的这么写了.

后来, 我接二连三在书写极端单元测试的时候遇到问题, 所谓极端单元测试, 是指我时不时的`unset`掉测试用例里的某个本应该为null的属性, 看看会不会出现问题, 结果产生了一系列coredump.

后来我发现了, 是因为我写操作了获取到的null zval, 产生了内存错误, 但是为什么不能操作它呢?

这时候我终于知道去看一眼PHP源码了...马上翻到`zend_std_read_property`这个标准的handler看一眼:

入眼就能看到一个:

```php
if (Z_TYPE_P(rv) != IS_UNDEF) {
    retval = rv;
    if (!Z_ISREF_P(rv) &&
        (type == BP_VAR_W || type == BP_VAR_RW  || type == BP_VAR_UNSET)) {
        if (UNEXPECTED(Z_TYPE_P(rv) != IS_OBJECT)) {
            zend_error(E_NOTICE, "Indirect modification of overloaded property %s::$%s has no effect", ZSTR_VAL(zobj->ce->name), ZSTR_VAL(name));
        }
    }
} else {
    retval = &EG(uninitialized_zval);
}
```

潜意识是没错了...在property的unset操作中, unset一个属性, 应该是有可能会将它标记为UNDEF的, 因为一般一个类的实例对象的HashTable是不变动的, unset其实是破坏了其结构的, 标记为UNDEF应该是一种优化.

但是zend_std_read_property对其进行了包装了, 返回了一个`EG(uninitialized_zval)`的指针, 这是个什么东西?

这其实就是个`type = null`的zval, 比较秀的是, 它是一个挂在`executor_globals`上的全局量, 便于随时取用作为返回值, 它被设计为只读的, 所以我们的千万不能操作它...

比如mysqli扩展中就用到了它来判断, 规避了非法的写操作:

```C
if (value != &EG(uninitialized_zval)) {
    convert_to_boolean(value);
    ret = Z_TYPE_P(value) == IS_TRUE ? 1 : 0;
}
```

所以我们应该纠正为(注释是美德)

```C
// NOTICE: if user unset the property, zend_read_property will return uninitialized_zval instead of NULL pointer
if (unlikely(property == &EG(uninitialized_zval)))
{
    property = zend_read_property(class_ptr, obj, s, len, silent, &rv);
}
```

这个包装是很好的, 保证了API返回的一定是一个**可读的zval**, 但是PHP底层的文档实在是太少了, 尤其是中国的开发者, 很难在网上找到任何有价值的东西, 需要一定的源码阅读能力和耐心才行, 否则经常会遇上这种非直觉的设计, 就是地狱难度的开发.



> 该API起码自PHP7起就一直如此设计, 使用`git blame`来查看API变动也是良好的习惯之一, 因为ZendVM经常会有一些你意想不到的API改动...
>
> https://github.com/php/php-src/blob/2bf8f29d16e6875e65eaa538a9740aac31ce650a/Zend/zend_object_handlers.c