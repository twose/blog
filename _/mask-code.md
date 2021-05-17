---
title: "[整理]【位运算经典应用】 标志位与掩码"
date: 2018-04-06 23:03:01
tags: mask
---

### [整理]【位运算经典应用】 标志位与掩码

> 本文原文来源自 http://www.cnblogs.com/zichi/p/4792589.html
>
> 相关内容经过整理, ABCD几个水果单词更加容易对应起来

前面我们已经了解了六大位操作符（`&` `|` `~` `^` `<<` `>>`)的用法（[javascript 位运算](http://www.cnblogs.com/zichi/p/4787145.html)），也整理了一些常用的位运算操作（[常用位运算整理](http://www.cnblogs.com/zichi/p/4789439.html)），本文我们继续深入位运算，来了解下二进制的经典应用-标志位与掩码。

位运算经常被用来创建、处理以及读取标志位序列——一种类似二进制的变量。虽然可以使用变量代替标志位序列，但是这样可以节省内存（1/32）。

例如有4个标志位：

1. 标志位A： 我们有 Apple
2. 标志位B： 我们有 Banana
3. 标志位C： 我们有 Cherry
4. 标志位D： 我们有 Dew

标志位通过位序列DCBA来表示，当一个位置被置为1时，表示有该项，置为0时，表示没有该项。例如一个变量flag=9，二进制表示为1001，就表示我们有D和A。

掩码 (bitmask) 是一个通过与/或来读取标志位的位序列。典型的定义每个标志位的原语掩码如下：

```javascript
var FLAG_A = 1; // 0001
var FLAG_B = 2; // 0010
var FLAG_C = 4; // 0100
var FLAG_D = 8; // 1000
```

<!--more-->

新的掩码可以在以上掩码上使用逻辑运算创建。例如，掩码 1011 可以通过 FLAG_A、FLAG_B 和 FLAG_D 逻辑或得到：

```javascript
var mask = FLAG_A | FLAG_B | FLAG_D; // 0001 | 0010 | 1000 => 1011
```

某个特定的位可以通过与掩码做逻辑与运算得到，通过与掩码的与运算可以去掉无关的位，得到特定的位。例如，掩码 0100 可以用来检查标志位 C 是否被置位：（**核心就是判断某位上的数** 参考[常用位运算整理](http://www.cnblogs.com/zichi/p/4789439.html) 下同）

```javascript
// 如果我们有 Cherry
if (flags & FLAG_C) { // 0101 & 0100 => 0100 => true
   // do stuff
}
```

一个有多个位被置位的掩码表达任一/或者的含义。例如，以下两个表达是等价的：

```javascript
// 如果我们有 Banana 或者 Cherry 至少一个
// (0101 & 0010) || (0101 & 0100) => 0000 || 0100 => true
if ((flags & FLAG_B) || (flags & FLAG_C)) {
   // do stuff
}

var mask = FLAG_B | FLAG_C; // 0010 | 0100 => 0110
if (flags & mask) { // 0101 & 0110 => 0100 => true
   // do stuff
}
```

可以通过与掩码做或运算设置标志位，掩码中为 1 的位可以设置对应的位。例如掩码 1100 可用来设置位 C 和 D：（**核心就是将某位变为1** ）

```javascript
// 我们有 Cherry 和 Dew
var mask = FLAG_C | FLAG_D; // 0100 | 1000 => 1100
flags |= mask;   // 0101 | 1100 => 1101
```

可以通过与掩码做与运算清除标志位，掩码中为 0 的位可以设置对应的位。掩码可以通过对原语掩码做非运算得到。例如，掩码 1010 可以用来清除标志位 A 和 C ：（**核心就是将某位变为0**）

```javascript
// 我们没有 Apple 也没有 Cherry
var mask = ~(FLAG_A | FLAG_C); // ~0101 => 1010
flags &= mask;   // 1101 & 1010 => 1000
```

如上的掩码同样可以通过 ~FLAG_A & ~FLAG_C 得到（德摩根定律）：

```javascript
// 我们没有 Apple 也没有 Cherry
var mask = ~FLAG_A & ~FLAG_C;
flags &= mask;   // 1101 & 1010 => 1000
```

标志位可以使用异或运算切换。所有值为 1 的为可以切换对应的位。例如，掩码 0110 可以用来切换标志位 B 和 C：（**核心就是将某位取反**）

```javascript
// 如果我们以前没有 Banana ，那么我们现在有 Banana
// 但是如果我们已经有了一个，那么现在没有了
// 对 Cherry 也是相同的情况
var mask = FLAG_B | FLAG_C;
flags = flags ^ mask;   // 1100 ^ 0110 => 1010
```

最后，所有标志位可以通过非运算翻转：

```javascript
// entering parallel universe...
flags = ~flags;    // ~1010 => 0101
```