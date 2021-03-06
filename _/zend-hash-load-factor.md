---
title: 有人发现PHP-7.3后内存占用变大了吗
date: 2021-05-17 18:11:44
tags: [zend]
---

分享会上讲到了PHP的packed array 与 hash array 对比

```php
$array = [];
$mem = memory_get_usage();
for ($i = 0; $i < 10000; $i++) {
    $array[$i] = $i;
}
var_dump('mem+=' . ($packed_arr_size = (memory_get_usage() - $mem)));

$array = [];
$mem = memory_get_usage();
for ($i = 10000; $i >= 0; $i--) {
    $array[$i] = $i;
}
var_dump('mem+=' . ($hash_arr_size = (memory_get_usage() - $mem)));

var_dump((($hash_arr_size - $packed_arr_size) / 1024) . 'K');
```

output:

```
string(11) "mem+=528480"
string(11) "mem+=659552"
string(4) "128K"
```

<!--more-->

![哈希表.jpeg](https://ae03.alicdn.com/kf/H8a1991c6f3044993ab066ef38ae4992b1.png)

如图，根据理论，压缩数组和哈希数组应该只相差一个索引列表，索引列表每个元素都是uint32, 也就是4个字节, 10000个元素, 桶的个数是2的14次方也就是16384个桶, 那么多占用的就是`((4 *16384) / 1024) = 64K` ，但实际结果是128k，在课上这里的计算翻车了，算出来是错的。
这确实有点神奇，课后源码分析了一波，发现了原因，可以说是非常的amazing……
内核书的版本是PHP7.2，但在PHP7.3的时候，PHP内核的核心作者Dmitry在一个小小的提交中把HashTable的负载因子从1改成了0.5 (https://github.com/php/php-src/commit/34ed8e53fea63903f85326ea1d5bd91ece86b7ae)。

什么是负载因子呢，我们课上说了哈希冲突这个内容，显然，索引列表越大，哈希冲突率就越小，查找的速度相应就变快，但是与此同时占用的内存也会变多，在Java中，HashTable默认的负载因子是0.75，在时间和空间成本之间提供了很好的权衡。

PHP在7.3突然改成0.5，那么索引数组的体积就变为原先的两倍，也就是128k了，我倾向于PHP在时间和空间中再次选择了时间，因此我们可以在PHP7.2升级到PHP7.3后看到可观的性能提升，但也可能会发现应用的内存占用变大了一些...

___

后续补充（2021-12-04）：这里当时没有仔细想，由于Bucket结构已经很大了，所以尽管索引结构内存占用变大了，但在从整个HashTable视角来看，内存占用增加的比例其实不大。

简单计算`sizeof(Bucket) + sizeof(uint32_t) = 4byte * 9`，现在我们多了`sizeof(uint32_t) = 4 `, 所以每个HashTable的内存占用仅增加了10%。

