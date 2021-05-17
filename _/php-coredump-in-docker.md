---
title: 在Docker中处理coredump && PHP-coredump与gdb使用
date: 2018-03-04 21:03:01
tags: [coredump,docker,php]
---

前几天在计划写一个爬虫脚本时, 由于涉及到html的浏览器渲染, 干脆用就用浏览器和控制台运行js脚本来作为爬虫工具, chrome支持ES6语法(有些需要在dev设置中开启), 写起来也是十分舒服, 爬完数据并处理过后通过xhr扔给后端服务器即可, 后端是用Swoole负责接收并向数据库进行大文本插入, 不幸的是在这时候错误出现了.

在数千个请求后nginx代理的后端挂掉了,返回了502BadGateWay,肯定要去上游找原因了,由于swoole是跑在docker容器中的, 于是马上查看容器日志

```Bash
$ docker logs custed_swoole_1 --tail 100
```

可以看到如下报错

```bash
$ WARNING	swProcessPool_wait: worker#0 abnormal exit, status=0, signal=11
```

google了一下没找到相关问题, 只能请教rango, 说是signal11是coredump了, 让我抓一下core文件

然后就开始踩坑了, 我的服务是运行在docker中的, docker里要抓core文件需要一波操作了...

废话不多说直接总结一下坑

#### 1. 开启容器特权

没有特权模式, 容器里就无法使用gdb调试

我用的是docker-compose 所以配置里需要加这么一行

```yaml
privileged: true
```

如果是run的话, 加:

```Bash
--privileged
```

<!--more-->

#### 2.开启coredump文件配置

```Yaml
ulimits:
      core: -1 # core_dump debug
```

```Bash
--ulimit core=-1
```



#### 3. 在容器里安装GDB

重新做镜像是不可能的了, 临时装一个吧(ps: 如果你不想在配置文件里开启core可以在这里临时设置)

```bash
ulimit -c unlimited
apt-get install -y gdb
```



#### 4. 触发coredump测试

我们可以用一段c代码死循环来尝试触发一个coredump

使用`g++ -g`编译, 加-g选项是为了保证debug信息生成在应用程序当中.

```c
#include <stdio.h>  
int main(int argc, char** argv) {  
  int* p = NULL;  
  *p = 10;  
}
```

然后

```Bash
gdb a.out core
```



#### 5. 修改core文件命名

坑爹的是, 项目里根目录恰好有个Core文件夹,我的mac硬盘分区给的又是大小写不敏感, GG, 改一波命名..

```bash
echo 'core.%e.%p' > /proc/sys/kernel/core_pattern
```

