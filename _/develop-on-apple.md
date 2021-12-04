---
title: 在MacOS平台上进行C开发的一些经验（Apple M1）
date: 2021-12-04 21:50:12
tags: ["macOS", "m1", "C", "PHP"]
---

## 前言

最近把从2017年就开始用的13寸乞丐版MacBookPro淘汰了。它是我打从学习编程开始就一直在用的电脑，到现在也有四年了，多少有点感情，但它实在是太卡了，因此，我不得不……<!--more-->

因为太卡了，放弃了eclipse转用vscode，但vscode的宏展开始终没有eclipse好用，不能无限层级往下展开，而是只能展开一层，对于看PHP内核或者PHP扩展这种宏孩儿少不了的代码来说不是很友好。 

因为太卡了，PhpStorm还得定期重启，键入代码有延迟，在PHP内核里checkout一个branch要花费甚至几分钟的时间，所以贡献也变少了… 有些patch写了以后也没提交。

因为太卡了，编译一次PHP可能得十来分钟，编译一次Swoole扩展得两三分钟，编译Swow和libcat稍微好一点，都是纯C项目，但如果是完全从头编译，也是分钟级的。

尤其是在虚拟机中编译，大概是文件系统慢的问题，make clean都要跑好一会，编译速度慢的那叫离谱他妈给离谱开门，离谱到家了。

那么为什么使用虚拟机呢？因为MacOS下无法使用valgrind，不能进行内存问题跟踪，没有strace，不能进行系统调用跟踪。

lldb倒是挺好用的，除了不支持source gdb脚本以外，几乎没有缺点，watchpoint功能也比gdb好用。

但是综上，很多时候问题都只能在Linux下调试，所以不得不上虚拟机。

## 一波三折

MacBook Pro 2021新款 带着 M1 Pro / Max横空出世，发布会当天因为一些事情累得不行，睡得很早，但是发布会开始的那个点，突然就从梦中惊醒，从床上垂死病中惊坐起，看完了发布会，感觉这新电脑真的是非常的amazing啊。而且我从19年开始一直在做等等党，本着永不言败的心态，结果输得彻彻底底，这次是非买不可了，第二天开始就一直蹲着等下单，然后第一时间购入了顶配，四年前是家里人赞助我买的，所以选择了最低配，但如今它已是我吃饭的家伙，性能就是生产力，这一次，顶配必须拿捏！

没想到，接下来竟是一个多月的苦等，64G全面缺货，大批订单延期，知乎上甚至还有一帮知友组了个维权群，加进去一看，受害者竟高达上百人……

期间公司还组织了团购，说是可能有85折优惠，听得我差点送去吸氧气瓶。

## 以下是正文 (或许)

一波三折还是到手了。

## 纯净迁移

首先，由于是intel转m1，x86转arm，所以用时间机器去转移老电脑的数据并不是一个明智的选择，而且原来的电脑积攒了四年的垃圾文件，有些初学时就深埋在我系统目录里的各种垃圾文件和奇怪配置也不好处理，所以我选择了重新捣鼓系统。

迁移比预想的要顺利很多，连起来算不到一天我就把所有东西都迁移完了，老电脑上看似不可或缺的东西很多，但其实常用的就那么一些。

顺便，有很多网站可以看M1上现在有哪些软件可用，哪些不可用，这里推荐一个：https://doesitarm.com/

目前我用到的除了luajit，没有不能用的，噢还有个pcre.jit，记得在编译PHP的时候用`--without-pcre-jit`选项关闭噢，不然会报warning，PHP内核本身的 `make install`里使用了PHP脚本，如果不关闭，安装都安装不了。

## Homebrew

homebrew在m1上的默认包安装路径从`/usr/local`变成了`/opt/homebrew`, blame了官方提交也没说是为什么，但README里说不按默认路径来可能会遇到奇怪的问题，所以还是老老实实转用`/opt/homebrew/`吧。

然后环境变量里需要配好多环境变量，那些C项目才能build起来，这里稍微分享下我当前的环境变量配置（放在`zshrc`里的）

```php
export PATH="\
$(brew --prefix openssl@1.1)/bin:\
$(brew --prefix libiconv)/bin:\
$(brew --prefix curl)/bin:\
$(brew --prefix bison)/bin:\
$PATH\
"
export LDFLAGS="$LDFLAGS \
-L$(brew --prefix openssl@1.1)/lib \
-L$(brew --prefix libiconv)/lib \
-L$(brew --prefix curl)/lib \
-L$(brew --prefix bison)/lib \
"

export LIBS="$LIBS -lssl -lcrypto"

export CFLAGS="$CFLAGS \
-I$(brew --prefix openssl@1.1)/include \
-I$(brew --prefix libiconv)/include \
-I$(brew --prefix curl)/include \
"

export CPPFLAGS="$CPPFLAGS \
-I$(brew --prefix openssl@1.1)/include \
-I$(brew --prefix libiconv)/include \
-I$(brew --prefix curl)/include \
"

export PKG_CONFIG_PATH="\
$(brew --prefix openssl@1.1)/lib/pkgconfig:\
$(brew --prefix curl)/lib/pkgconfig:\
$PKG_COFNIG_PATH\
"

export OPENSSL_ROOT_DIR="$(brew --prefix openssl@1.1)"
export OPENSSL_LIBS="-L$(brew --prefix openssl@1.1)/lib"
export OPENSSL_CFLAGS="-I$(brew --prefix openssl@1.1)/include"
```

我对于C构建系统也就是懂点皮毛，能写CMakeList和autoconf生态的m4，90%的构建问题都能自行解决但有时候也不知道原理是啥的那种程度，就我在m1构建C项目的体验上而言，我觉得对于完全不懂构建系统的小伙伴来说，想编译明白东西还是会挺痛苦的。

## All in MacOS 之 为啥不需要虚拟机了

我现在基本上all in macOS了，macOS成为了我的主力开发环境，没有虚拟机套娃肯定是性能最优的，但是调试的问题怎么解决呢。

### 内存跟踪问题

首先，我给PHP内核、Swow、libcat都加了ASan编译选项的支持，而且这玩意就算没有项目的编译选项支持，手动加个gcc编译参数也能搞定，ASan是个好东西，我觉得所有C/C++开发都需要深入了解下，包括它的原理。比较浅显的好处就是，ASan可以在几乎任何环境里跑，完美解决了macOS下对于内存问题跟踪的需求。

而且valgrind的性能比较捉急，有时候就难堪大用，它会使程序性能下降十倍以上，而有些对于时间、并发敏感的BUG，在valgrind下就复现不出来了，常常跟踪了个寂寞。而ASan在编译期就用了影子内存和hook一些内存函数的技巧，性能碾压valgrind。

### 系统调用跟踪问题

Intel的Mac开机时候按住command + R，而arm的Mac只需要开机时一直按住开机键，就能进到恢复模式。

进去以后菜单栏里打开终端，输入：

```
$ csrutil disable
$ csrutil enable --without debug --without dtrace
```

先关掉SIP再打开，但是排除掉我们需要的部分。

需要注意的是有些系统版本好像是`--without dtruss`，但是m1上只有dtrace好使，但我们实际用的又是`dtruss`，非常莫名其妙。

可以用`csrutil status`查看结果，平时不在恢复模式也可以看：

```
$ csrutil status
System Integrity Protection status: unknown (Custom Configuration).

Configuration:
	Apple Internal: disabled
	Kext Signing: enabled
	Filesystem Protections: enabled
	Debugging Restrictions: disabled
	DTrace Restrictions: disabled
	NVRAM Protections: enabled
	BaseSystem Verification: enabled
	Boot-arg Restrictions: enabled
	Kernel Integrity Protections: disabled
	Authenticated Root Requirement: enabled

This is an unsupported configuration, likely to break in the future and leave your machine in an unknown state.
```

虽然系统说好像自定义设置是unknown state，不过目前用下来没遇到啥问题。

毕竟都是个开发，有问题再想办法解决嘛。

然后我们就可以用`dtruss`来代替`strace`了。

此外，由于libcat是基于libuv的协程版libuv，所以不需要自己操心跨平台的问题，理论上一个系统下开发完了，各个系统都能跑。

当然，实际情况并不是100%这么理想的，还是会有那么一点边缘问题，但是我们有一大堆各种系统的CI去保证跨平台兼容性，甚至有什么龙芯、鲲鹏的机器（都是@dixyes搞的，我也不甚懂），似乎我们已经实现了好多个「第一个运行在XX平台的PHP协程库/框架」的成就，总之就是非常的牛啤。

## 编译性能

我习惯在编译的时候用`make > /dev/null`，把标准输出重定向吃掉，这样就可以只看warning了，比较清爽。

然后，在新电脑上第一次编译的时候，我靠，就顿了一小会，就结束退出了，我还以为坏了，遇到M1上的BUG了，编译都闪退了……

结果是编译太快了，原来我要几分钟的编译，不到五秒秒就完成了……

```
$ time make -j8 >/dev/null
make -j8 > /dev/null  11.13s user 15.77s system 614% cpu 4.375 total
```

原来编译的时间太长，电脑还卡的啥也干不了，风扇锁7200转声音巨大无比，所以一般编译一下就看会手机，精力很分散。

而现在，几乎是所见即所得，哪怕是从头开始编译，也只需要几秒钟，编程体验极大提升。

而且所有IDE现在都是丝滑流畅，写起代码来不要太爽，感觉被封印了很久的写代码的激情又回来了。

## Windows虚拟机下的游戏性能

其实有自己的Windows电脑，肯定是不会在这台Mac上玩的，但是出于好奇和执念，还是试了试。

执念是因为毕业前回学校那段时间，只带了Mac本回去，几个月没打游戏，有点难受，就搞了个虚拟机玩了一会命运石之门，结果这都卡的不行，就很气人。

而新Mac还有120hz高刷屏，听说显卡性能也还不错，似乎可以一战。但是又由于架构问题，加上虚拟机，可能就好几层套娃，那个性能可能也是没眼看。

最终尝试的结果是，命运石之门这种文字冒险游戏肯定是丝滑流畅。斗胆试了下CSGO，1080p 200帧无压力，也是非常的amazing，但是实际匹配的时候会出现顿卡，但也没有很影响游戏体验，就是会突然从120掉到40帧那么零点几秒，感觉可能和虚拟机或者转译有关？但是正经人谁会在Mac上打CSGO呢…… 玩了一把以后卸载了，毕竟我这个Windows虚拟机可能未来还是要作为Win下构建调试项目的用途。

## 后话

其实写这篇文章就是体验一下新电脑的丝滑的，没有什么别的意思，内容也比较水，纯当练练打字，有好多M1上的经验点一时半会也想不起来了，后面想到了再补吧。
