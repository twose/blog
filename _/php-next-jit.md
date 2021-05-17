---
title: "[转] PHP Next JIT"
date: 2018-01-04 05:28:57
tags: php
---

12月23日，由开源中国联合中国电子技术标准化研究院主办的2017源创会年终盛典在北京万豪酒店顺利举行。作为年末最受期待的开源技术分享盛会，国内顶尖技术大拿、知名技术团队、优秀开源项目作者，及近1000名技术爱好者共聚一堂，探讨最前沿、最流行的技术话题和方向，推动国内开源创新体系发展，共建国内开源生态标准。PHP7 已发布近两年, 大幅的性能提升使得 PHP 的应用场景更加广泛，刚刚发布的 PHP7.2 相比 PHP7.1 又有了近 10% 的提升。在本次大会上，链家集团技术副总裁、PHP 开发组核心成员鸟哥发表了以 “ PHP Next: JIT ”为主题的演讲，分享了 PHP 的下一个性能提升的主要举措：JIT 的进展, 以及下一个大版本的 PHP 可能的特性。他表示，JIT 相比 PHP7.2 ，在一些场景可以达到三倍，但由于 JIT 的核心前提是类型推断，得到的信息越多效果越好，因此也容易受到限制。 JIT 发布后，随着更优秀的代码出现，性能提升会更明显。



## 惠新宸

惠新宸 ，国内最有影响力的PHP技术专家， PHP开发组核心成员 , PECL开发者 , Zend公司外聘顾问, 曾供职于雅虎，百度，新浪。现任链家集团技术副总裁兼总架构师。PHP 7 的核心开发者，PHP5.4，5.5的主要开发者。也是Yaf (Yet another framework)，Yar(Yet another RPC framework) 以及Yac(Yet another Cache)、Taint等多个开源项目的作者，同时也是APC，Opcache ，Msgpack等项目的维护者。



## 演讲实录 

**PHP Next: JIT**

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626739-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626740.jpeg)

<!--more-->

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626740-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626741.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626742.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626742-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626743.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626743-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626744.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626744-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626745.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626745-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626746.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626746-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626747.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626747-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626747-2.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626748.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626748-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626749.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626749-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626750.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626750-1.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626750-2.jpeg)

![鸟哥：PHP Next: JIT](http://blog.p2hp.com/wp-content/uploads/2017/12/beepress-beepress-weixin-zhihu-jianshu-plugin-2-4-2-4899-1514626751.jpeg)