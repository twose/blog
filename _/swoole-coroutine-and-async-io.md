---
title: 漫谈Swoole协程与异步IO
date: 2020-06-12 18:02:07
tags: [swoole, coroutine, async]
---

初次接触Swoole的PHP开发者多少都会有点雾里看花的感觉，看不清本质。一部分PHP开发者并不清楚Swoole是什么，只是觉得很牛掰就想用了，这种行为无异于写作文的时候总想堆砌一些华丽的辞藻或是引经据典来提升文章逼格，却背离了文章的主题，本末倒置，每一种技术的诞生都有它的原因，异步或是协程不是万能的银弹，你需要它的时候再去用它，而不是想用它而用它，毕竟编程世界的惯性是巨大的，这天下还是同步阻塞的天下。还有一部分开发者是对Swoole有了一些自己的见解，但对错参半，写出来的程序能跑，甚至也能上生产，但不是最优的，其中大部分问题都源于开发者无法将惯有的思维方式灵活转变。

<!--more-->

协程
---

首先协程的最简定义是**用户态线程**，它不由操作系统而是由用户创建，跑在单个线程（核心）上，比进程或是线程都更加轻量化，通常创建它只有内存消耗：假如你的配置允许你开几千个进程或线程，那么开几万个几十万个协程也是很轻松的事情，**只要内存足够大，你可以几乎无止境地创建新的协程**。在Swoole下，协程的切换实现是依靠双栈切换，即C栈和PHP栈同时切换，由于有栈协程的上下文总是足够的小，且**在用户态便能完成切换**，它的切换速度也总是远快于进程、线程，一般**只需要纳秒级的CPU时间**，对于实际运行的逻辑代码来说这点开销总是可以忽略不计（尤其是在一个重IO的程序中，通过调用分析可以发现协程切换所占的CPU时间非常之低）。

对于Swoole这样的有栈协程，你完全可以简单地将其看做是一个栈切换器，你可以在运行的子程序中随意切换到另一个子程序，底层会保存好被切走的协程的执行位置，回来时可以从原先的位置继续往下运行。

![coroutine.jpg](https://i.loli.net/2021/05/17/oinSItwgVBhubyK.jpg)
<center>Swoole多进程模型下的进程、线程、协程关系图</center>



但这篇文章我们要谈的并不只是单单「协程」这一个概念，还隐含了关于异步网络IO一系列的东西，**光有协程是什么也做不了的**，因为Swoole的协程永远运行在一个线程中，想用它做并行计算是不可能的，运行速度只会因为创建开销而更慢，没有异步网络IO支持，你只能在不同协程间切来切去玩。

实际上PHP早就实现了协程，`yield`关键字就是允许你从一个函数中让出执行权，需要的时候能重新回到让出的位置继续往下执行，但它没有流行起来也有多种原因，一个是它的传染性，每一层调用都需要加关键字，另一个就是PHP没有高效可靠的异步IO支持，让其食之无味。

异步
---

> 注：本文中提到的异步IO并非全为严格定义上的异步IO，更多的是日常化的表达

简单了解了协程，再让我们来理解一下什么是异步IO吧。严格来说，在Unix下我们常说的异步并不是真异步，而是同步非阻塞，但是其效果和异步非常相近，所以我们日常中还是以异步相称。同步非阻塞和真异步区别在于：真异步是你提交读写请求后直接检查读写是否已完成即可，所以在Win下这样的技术被叫做「完成端口」，而同步非阻塞仅是操作不会长时间地陷入内核，但你需要在检查到可读或可写后，调用API同步地去拷贝数据，这会不可避免地陷入内核态，但read/write通常并不会阻塞太多的时间，从宏观上整个程序仍可以看作是全异步的。

|      |    阻塞     | 非阻塞                                       |
| :--: | :---------: | -------------------------------------------- |
| 同步 | write, read | read, write + poll / select / epoll / kqueue |
| 异步 |      -      | aio_read, aio_write, IOCP(windows)           |

在实际使用中，「伪异步」的Reactor模型并不比Windows下IOCP的Proactor逊色，并且我更喜欢Reactor的可控性，当然为了追求极致的性能和解决网络和文件异步IO统一的问题，未来Linux的io_uring可能会成为新的趋势。

![event_wait.jpg](https://i.loli.net/2021/05/17/GO9S61tWwxVdays.jpg)

<center>Reactor运行流程简图</center>

我们可以通过上面的图片简单理解Reactor模型的运行流程，所谓的「异步」不过是多路复用带来的观感效果，你的程序不会阻塞在一个IO上，而是在无事可干的时候再阻塞在一堆IO上，即**IO操作不在你需要CPU的时候阻塞你，你就不会感受到IO阻塞的存在**。

> 结合现实情景来说，以前你要买饭（IO操作），你得下楼去买，还得排队等饭店大厨做完才能取回家吃（IO阻塞），到了下一餐，你又得重复之前的操作，很是麻烦，而且越是繁忙的时候等的时间越长（慢速IO），你觉得一天到晚净排队了，极大地浪费了你写代码的时间（CPU时间）。现在有了外卖，你直接下单（异步请求）就可以继续专心写代码（非阻塞），你还可以一次定三份饭（多路IO），饭到了骑手打电话让你下楼取（事件触发），前后只花了不到几分钟（同步读写，如果是Proactor连取餐都省了，直接给你送上楼），周六晚上的九点，你终于合上电脑，觉得充实极了，因为你几乎一整周都在写代码（CPU利用率高）。



协程+异步=同步非阻塞编程
---

现在我们有了协程和异步，我们可以做什么呢？那就是异步的同步化。这时候有的开发者就会说了，诶呀好不容易习惯异步了，怎么又退回到同步了呢。这就是为什么有些开发者始终写不出最优的协程代码的原因，异步由于操作的完成不是立即的，所以我们需要回调，而回调总是反人类的，嵌套的回调更是如此。

而结合协程，消灭回调我们只需要两步：**在发出异步请求之后挂起协程，在异步回调触发时恢复协程**。

```php
Swoole\Coroutine\run(function(){
  	// 1. 创建定时器并挂起协程#1
    Swoole\Coroutine::sleep(1);
    // 3. 协程恢复，继续向下运行退出，再次让出
});
// 2. 协程#1让出，进入事件循环，等待1s后定时器回调触发，恢复协程#1
// 4. 协程#1退出并让出，没有更多事件，事件循环退出，进程结束
```

短短的一行协程sleep，使用时几乎与同步阻塞的sleep无异，却是异步的。

```php
for ($n = 10; $n--;) {
    Swoole\Coroutine::create(function(){
    	  Swoole\Coroutine::sleep(1);
    });
}
```

我们循环创建十个协程并各sleep一秒，但实际运行可以发现整个进程只阻塞了一秒，这就表明在Swoole提供的API下，阻塞操作都由进程级别的阻塞变为了协程级别的阻塞，这样我们可以以很小的开销在进程内通过创建大量协程来处理大量的IO任务。

协程代码编写思路
---

### 定时任务

当我们说到定时任务时，很多人第一时间都想到定时器，这没错，但是在协程世界，它不是最佳选择。

```php
$stopTimer = false;
$timerContext = [];
$timerId = Swoole\Timer::tick(1, function () {
    // do something
    global $timerContext;
    global $timerId;
    global $stopTimer;
    $timerContext[] = 'data';
    if ($stopTimer) {
        var_dump($timerContext);
        Swoole\Timer::clear($timerId);
    }
});
// if we want to stop it:
$stopTimer = true;
```

在异步回调下，我们需要以这样的方式来掌控定时器，每一次定时器回调都会创建一个新的协程，并且我们不得不通过全局变量来维护它的上下文。

如果是协程呢？

```php
Swoole\Coroutine\run(function() {
    $channel = new Swoole\Coroutine\Channel;
    Swoole\Coroutine::create(function () use ($channel) {
        $context = [];
        while (!$channel->pop(0.001)) {
            $context[] = 'data';
        }
        var_dump($context);
    });
    // if we want to stop it, just call:
    $channel->push(true);
});
```

完全同步的写法，从始至终只在一个协程里，不会丢失上下文，channel->pop在这里的效果相当于毫秒级sleep，并且我们可以通过push数据去停止这个定时器，非常的简单清晰。

### Task

由于开发者的强烈要求，Swoole官方曾经做了一个错误的决定，就是在Task进程中支持协程和异步IO。

![task.png](https://i.loli.net/2021/05/17/e9VMSchiN3Tr6ag.jpg)

正如图中所示，Task进程最初被设计为用来处理无法异步化的任务，充当类似于PHP-FPM的角色（半异步半同步模型），这样各司其职，能够将执行效率最大化。

最早期的Swoole开发者，甚至直接将Swoole的Worker进程用于执行同步阻塞任务，这种做法并非没有可取之处，它比PHP-FPM下的效率更高，因为程序是持续运行，常驻内存的，少了一些VM启动和销毁的开销，只是需要自己处理资源的生命周期等问题。

此外就是使用异步API的开发者，他们会开一堆Task进程，将一些暂时无法异步化的同步阻塞任务丢过去处理。

而以上两种都是历史条件下正确并合适的Swoole打开方式。

但是还有一小撮开发者，一股脑地把所有任务都投递给Task进程，以为这样就实现了任务异步化，Worker进程除了接收响应和投递任务什么也不干，殊不知这就相当于每一个任务的处理多了**两次数据序列化开销 + 两次数据反序列开销 + 两次IPC开销 + 进程切换开销**。

而当协程逐渐成为新的趋势后，又有越来越多的社区呼声要求Task进程也能支持协程和异步IO，这样他们就可以将协程方式编写的任务投递到Task中执行。但异步任务可以很轻量地在本进程被快速处理掉，对Worker整体性能并不会有太大影响，他们这样的行为，也是典型的舍近求远。

#### Task方式处理协程任务

```php
$server->on('Receive', function(Swoole\Server $server) {
    # 投递任务，序列化任务数据，通过IPC发送给Task进程
    $task_id = $server->task('foo'); 
});
# 切换到Task进程
# 接收并反序列化Worker通过IPC发送来的任务数据
$server->on('Task', function (Swoole\Server $server, $task_id, $from_id, $data) {
	  # 使用协程DNS查询
    $result = \Swoole\Coroutine::gethostbyname($data);
    # 序列化数据，通过IPC发送回Worker进程
    $server->finish($result);
});
# 回到Worker进程
# 接收并反序列化Task通过IPC发送来的结果数据
$server->on('Finish', function (Swoole\Server $server, int $task_id, $result) {
    # 需要通过任务id才能确认是哪个任务的结果
    echo "Task#{$task_id} finished";
    # 打印结果
    var_dump($result);
});
```

#### 协程方式写Task

> 注：batch方法由swoole/library提供，内置支持需要Swoole-v4.5.2及以上版本，低版本可以自己使用Channel来调度

```php
use Swoole\Coroutine;

Coroutine\run(function () {
    # 并发三个DNS查询任务
    $result = Coroutine\batch([
        '100tal' => function () {
            return Coroutine::gethostbyname('www.100tal.com');
        },
        'xueersi' => function () {
            return Coroutine::gethostbyname('www.xueersi.com');
        },
        'zhiyinlou' => function () {
            return Coroutine::gethostbyname('www.zhiyinlou.com');
        }
    ]);
    var_dump($result);
});
```

输出（API保证返回值顺序与输入顺序一致，不会因为异步而乱序）

```php
array(3) {
  ["100tal"]=>
  string(14) "203.107.33.189"
  ["xueersi"]=>
  string(12) "60.28.226.27"
  ["zhiyinlou"]=>
  string(14) "101.36.129.150"
}
```

非常的简单易懂，不存在任何序列化或者IPC开销，并且由于程序是完全非阻塞的，大量的Task任务也不会对整体性能造成影响，所以说Task进程中使用协程或异步完全就是个错误，作为一个程序员，思维的僵化是很可怕的。

---

读到这里大家应该也能明白，我们所谈论的协程化技术实际上可以看做传统同步阻塞和非阻塞技术的超集，非阻塞的技术让程序可以同时处理大量IO，协程技术则是实现了可调度的异步单元，它让异步程序的行为变得更加可控。如果你的程序只有一个协程，那么程序整体就是同步阻塞的；如果你的程序在创建某个协程以后不关心它的内部返回值，它就是异步的。

希望通过本文，大家能够加深对协程和异步IO的理解，写出高质量可维护性强的协程程序。
