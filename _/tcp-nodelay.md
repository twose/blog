---
title: 用0.04秒看出大佬的网络编程基本功素养
date: 2018-09-16 23:32:08
tags: ["swoole", tcp", "nodelay", "nagle"]
---

## 起因

  事情是这样的, 最近在做Swoole的Websocket的底层代码优化, 和编写更多的单元测试来保证代码正确和功能的稳定性, 写了很多高质量的"混沌"测试, 好吧, 其实并不是那么混沌, 只是这个词眼看起来很帅.
以往的unit tests更像是一些带着assert的examples, 加之phpt的测试风格, 顶多再来个EXPECT(F/REGEX)的预期输出对比, 只能测试出这个功能能否跑通, 并没有覆盖到功能的健壮性.而每当底层出现BUG接着我们很快就发现了原因时, 都会感叹单元测试不够全面和完善.
  所以在新写的测试中, 我尽量引入随机数据和一定量的并发压力来简单的模拟各种情况, 在自动化的单元测试中这样的做法已经是权衡了测试敏捷和健全的最优解了, 比如以下这个名为`websocket-fin`的测试:

```php
$count = 0;
$pm = new ProcessManager;
$pm->parentFunc = function (int $pid) use ($pm, &$count) {
    for ($c = MAX_CONCURRENCY; $c--;) {
        go(function () use ($pm, &$count) {
            $cli = new \Swoole\Coroutine\Http\Client('127.0.0.1', $pm->getFreePort());
            $cli->set(['timeout' => 5]);
            $ret = $cli->upgrade('/');
            assert($ret);
            $rand_list = [];
            $times = MAX_REQUESTS;
            for ($n = $times; $n--;) {
                $rand = openssl_random_pseudo_bytes(mt_rand(0, 1280));
                $rand_list[] = $rand;
                $opcode = $n === $times - 1 ? WEBSOCKET_OPCODE_TEXT : WEBSOCKET_OPCODE_CONTINUATION;
                $finish = $n === 0;
                if (mt_rand(0, 1)) {
                    $frame = new swoole_websocket_frame;
                    $frame->opcode = $opcode;
                    $frame->data = $rand;
                    $frame->finish = $finish;
                    $ret = $cli->push($frame);
                } else {
                    $ret = $cli->push($rand, $opcode, $finish);
                }
                assert($ret);
            }
            $frame = $cli->recv();
            if (assert($frame->data === implode('', $rand_list))) {
                $count++;
            }
        });
    }
    swoole_event_wait();
    assert($count === MAX_CONCURRENCY);
    $pm->kill();
};
$pm->childFunc = function () use ($pm) {
    $serv = new swoole_websocket_server('127.0.0.1', $pm->getFreePort(), mt_rand(0, 1) ? SWOOLE_BASE : SWOOLE_PROCESS);
    $serv->set([
        'log_file' => '/dev/null'
    ]);
    $serv->on('WorkerStart', function () use ($pm) {
        $pm->wakeup();
    });
    $serv->on('Message', function (swoole_websocket_server $serv, swoole_websocket_frame $frame) {
        if (mt_rand(0, 1)) {
            $serv->push($frame->fd, $frame);
        } else {
            $serv->push($frame->fd, $frame->data, $frame->opcode, true);
        }
    });
    $serv->start();
};
$pm->childFirst();
$pm->run();
```
<!--more-->

## 测试流程

  Swoole中涉及网络服务的测试模型一般都长这样, 一个PHP写的简易好用的`ProcessManager`来管理进程, 子进程(childFunc)一般为服务, 父进程(parentFunc)一般为客户端, 来测试收发处理是否正确.

  首先子进程会先运行(`childFirst`), 服务创建成功后, 会进入`onWorkerStart`回调, 此时服务已经能进行请求处理, 通过`wakeup`唤起父进程,父进程会顺序执行, 创建多个协程, 在`swoole_event_wait`处进入事件循环, 待所有协程运行完毕后, 断言执行成功次数是否正确, 然后kill掉进程退出测试.

  在这里我们并发了`MAX_CONCURRENCY`个数的协程来请求服务器(相当于`ab测试`的`-c`参数), 这里使用`MAX_CONCURRENCY`常量的原因是`TravisCI`(线上自动化集成测试)的配置并不是那么好, 不一定能承载住稍大的并发, 常量的值可以在不同环境下有所区别, 而积极使用常量也能让一个程序的可读性, 可移植性大大提升.

  每个协程里都创建一个HTTP客户端(连接), 连接建立后, 通过`upgrade`升级到websocket协议, 执行`MAX_REQUESTS`次(相当于`ab测试`的`-n`参数)的请求逻辑, 每一次都会通过`openssl_random_pseudo_bytes`来生成一串0~1280字节的随机字符串, 添加到`$rand_list`的同时向服务器发送.

```php
$opcode = $n === $times - 1 ? WEBSOCKET_OPCODE_TEXT : WEBSOCKET_OPCODE_CONTINUATION;
$finish = $n === 0;
```

  这两句代码的意思是, 在websocket中使用`分段发送帧`的时候, 第一帧的opcode是确切的帧类型(这里是TEXT), fin为0, 代表帧未结束, 后续帧的opcode都是`WEBSOCKET_OPCODE_CONTINUATION`, 表示这是一个连续帧, 直到最后一帧(n==0循环结束)fin变为1, 代表帧结束.

  这个连续帧最多有`MAX_REQUESTS`帧, 值一般为100, 1280字节*100次也就是最大128K左右, 这个测试量也就是稀松平常, 对于swoole来说并不算是有什么压力, 称不上压力测试, 只是通过随机数据来尽可能保证各种情况下的可用性.

## 蜜汁耗时

  而恰好我又在最近为自动化测试加上了一个耗时统计选项, 很奇怪的结果出现了, fin测试居然耗时超过20s, 这个问题在我的MacOS下并不存在, 但是却在Ubuntu复现了.

![](https://ws1.sinaimg.cn/large/006DQdzWgy1fveqbea7h9j31fs0h4qaw.jpg)

  同样出现问题的还有greeter测试, 它们都有一个共同的问题, 就是它们使用了**websocket通信单个连接多次发包.**

  BUG能在Ubuntu下复现是个好事, 因为MacOS除了`LLDB`根本没有好用的调试工具, `valgrind`不可用, 而`strace`的替代品`dtruss`也不甚好用, 在Ubuntu下使用`strace`跟踪, 很快就能看到以下日志:

![](https://ws1.sinaimg.cn/large/006DQdzWgy1fveu8j5bqcj31j616e1kx.jpg)

  如果是使用标准输出跟踪可以看到打印的信息非常正常, 由于数据量大屏幕会不断滚动, 但并没有出现卡顿, 数据传输也很均匀, 可以看到有很多`getpid`的系统调用, 第一反应是是不是这个的问题, 稍微确认一下就能发现这是`openssl_random_pseudo_bytes`的系统调用, 并没有什么关系.

## 前辈经验

  量大就慢是不可能的, 在MacOS下完成这个脚本只需眨眼之间, 且没有任何错误, 苦思了半天也不得解, 只能求助rango, rango刚开始看思路和我差不多, 也是先看到了大量的`getpid`, 稍加思索马上就排除了这个, 在标准输出中跟踪也发现非常正常, 然后觉得是不是数据量太大了, 但是稍加确认又马上排除.

  很快, 他就注意到了epoll_wait的等待时间格外的长, 虽然我也注意到了, 但我只注意到了格外的长, 并没有留意长出来的时间是多少, 数据是不间断连续发送的, 却有**40ms**的延迟, 这对于本机的两端互发数据来说是一个很大的值了.

  "0.04s, 不会是那个吧", 说罢rango马上**在配置项加上了一个`open_tcp_nodelay => false`, 再跑一次测试, 问题解决...**

  这就是名震江湖的**调参术**吗...像以前用windows的时候, 经常能看到一个水文, **`一招让你电脑网速提升20%`**  , 大概是通过配置关闭了TCP的**慢启动**, 让测速结果更加好看, 实际上可能并没有什么效果, 反而让这个优秀的设计在相关网络场景下失去效用, 造成**拥塞**.

  但是这个东西完全是关于**`基本功`和`经验`**, 我压根不知道这个东西, 看破脑袋也看不出这个关键的40ms, 而我没有相关的经验, 就算有相关的网络编程知识也一时很难联系起来.

##TCP_NOLAY 与 Nagle合并算法

  开启  `TCP_NOLAY`实际是关闭`Nagle合并算法`, 这个算法在网上的讲解有很多, 而且原理也非常简单, 写的肯定比我好多了, 如维基上的伪码:

```C
if there is new data to send
  if the window size >= MSS and available data is >= MSS
    send complete MSS segment now
  else
    if there is unconfirmed data still in the pipe
      enqueue data in the buffer until an acknowledge is received
    else
      send data immediately
    end if
  end if
end if
```

 而[Nagle算法是时代的产物，因为当时网络带宽有限](https://www.zhihu.com/question/42308970/answer/246334766), 于是我就把Swoole的`TCP_NODELAY`改为默认开启了, 不要急, [Nginx-tcp_nodelay](http://nginx.org/en/docs/http/ngx_http_core_module.html#tcp_nodelay)和php_stream等也是这么做的, 大家都有自己的缓冲区, 无需立即发送的小数据包是不会马上发出去的, 例如最重要的HTTP, 它是`读-写-读-写`模式的, 数据都是等请求`end`了之后才会一并发出(除非使用了chunk), 也就是说, 如果数据确实发出了, 那么它就有发出的必要性(哪怕它是个小数据包), 开发者希望它总是保持低延迟的, 而不是动不动就出来40ms, 若想要底层防止拥塞, 那么届时再手动开启`Nagle合并算法`.

  在我写完以上内容后, 我搜了一下, 发现这个问题有很多让我哭笑的标题:

- [神秘的40毫秒延迟与 TCP_NODELAY](https://blog.csdn.net/zheng0518/article/details/78561246)
- [写socket的“灵异事件”](https://blog.csdn.net/historyasamirror/article/details/6122284)
- [再说TCP神奇的40ms](https://cloud.tencent.com/developer/article/1004431)

  好吧, 肯来很多前人都被这个神奇的40ms困扰过, 说明写个博客还是很能造福后人的.