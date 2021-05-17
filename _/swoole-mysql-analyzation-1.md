---
title: Swoole的Mysql模块浅析-1
date: 2018-05-11 20:48:00
tags: [swoole, mysql]
---

众所周知, PHP是由C语言编写的, 扩展也不例外, Swoole又是PHP扩展中发展的比较快且很权威的一个扩展, 对于MySQL这部分模块的浅析, 暂可不必了解Swoole底层的实现, 而先关注应用层面的实现.

## 基础要求

所以除了PHP我们仅需了解以下几个方面的知识:

1. MySQL基础
2. TCP网络协议基础(MySQL协议)
3. C语言基础及其简单调试

而使用过Swoole的同学一定对以下工具不陌生:

1. `GDB`(Mac下用`LLDB`)和`Valgrind`作为源码/内存分析
2. `Wireshark`或`TcpDump`作为网络分析

<!--more-->

## 分析流程

首先我们写一个简单的协程Mysql查询Demo

```php
go(function () {
    $db = new Swoole\Coroutine\Mysql;
    $server = [
        'host'     => '127.0.0.1',
        'user'     => 'root',
        'password' => 'root',
        'database' => 'test'
    ];
    $db->connect($server);
    $stmt = $db->prepare('SELECT * FROM `userinfo`');
    $ret = $stmt->execute([]);
    var_dump($ret);
});
```

然后我们可以使用Wireshark对本地网络进行捕获![](https://ws1.sinaimg.cn/large/006DQdzWgy1fr7pj4z2djj30rs0m8jtr.jpg)


依托于功能强大的wireshark, 我们只需过滤器里输入`mysql`即可从繁忙的本地网络中筛选出mysql通信的数据

![](https://ws1.sinaimg.cn/large/006DQdzWgy1fr7ptebaaej30rk06x409.jpg)

我们可以看到MySQL通信**建立后**的部分(不包括前面TCP握手等部分)
1. Mysql服务器向客户端打招呼, 并携带了自身版本信息
2. 客户端收到后, 发起登录请求, 并携带了配置参数(用户名/密码/使用编码/选择进入的数据库等)
3. Mysql响应登录成功
4. 发出一个携带SQL语句的PREPARE请求来编译模板语句 [COM_STMT_PREPARE]
5. Mysql响应PREPARE_OK响应报文 (这里的返回报文比较复杂,在下一篇细讲)
6. 发出执行指定ID模板语句的请求, 并携带了参数数据  [COM_STMT_EXECUTE]
7. Mysql响应结果集(此处也很复杂)

## 问题发现: swoole的疏漏?

乍看之下这一套流程并没有什么问题, 但由于在此之前我是PDO的忠实粉丝(Swoole的Statement功能也是当初机缘巧合我建议Rango大佬考虑加入的), 所以我在阅读Swoole源码的同时也阅读了PDO源码并编写demo互作比对, 然后很快就发现了问题.
```php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=test;charset=utf8", "root", "root");
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$sql = "SELECT * FROM  userinfo WHERE `id`=:id";
$stmt = $pdo->prepare($sql);
$res = $stmt->execute(['id' => 1]);
```

### 缺失的流程
![](https://ws1.sinaimg.cn/large/006DQdzWgy1fr7qnai0mxj30rv05egn7.jpg)
很容易可以发现, PDO比Swoole多做了一些**善后处理**, 在statement对象销毁时, 触发了destruct主动通知mysql销毁了模板语句, 然后在pdo对象销毁时, 又主动通知了mysql该会话/连接退出.

---

马上我怀疑是我没有主动在swoole调用close关闭的缘故, 但是close应该是在destruct的时候自动触发的, 所以我们需要深入一波源码, 看看swoole是否有做收尾工作.

## 源码分析

直接通过文件名和关键字搜索来查看对应源码也是可以的, 但是用gdb调试来查看底层C内部运作的流程会更酷.

Mac下使用lldb工具更佳, 操作和gdb大同小异.

在终端中输入:

```shell
lldb php "/path/to/swoole-mysql.php"
```

就可以在lldb中设置调试程序和对应脚本(实际上是调试PHP这个C程序, 并添加了path作为第一个argument)

由于Swoole的协程运作机制异常复杂, PHP脚本并不是像代码那样按序从头到尾运行一遍那么简单, go函数会立即返回, Swoole会在脚本结尾注册shutdown-function, 然后进入事件循环, 这里我有空会写一篇新文章分析, 所以按照常规方式操作并不能分析该脚本的调用栈.

```shell
# b = breakpoint; r = run
# ==================
b "zim_swoole_mysql_coro___destruct"
r
```
此时可能会提示
```shell
Breakpoint 1: no locations (pending).
WARNING:  Unable to resolve breakpoint to any actual locations.
```
实际上是可以下断点的, 只是由于某些的缘故lldb找不到该位置, 有待分析

然后你就可以看到程序运行了并断在了这里, 你可以输入`list`来展开源码

```c
* thread #1, queue = 'com.apple.main-thread', stop reason = breakpoint 1.1
    frame #0: 0x00000001038aace3 swoole.so`zim_swoole_mysql_coro___destruct(execute_data=0x0000000101c85210, return_value=0x00007ffeefbfd998) at swoole_mysql_coro.c:1088
   1085
   1086	static PHP_METHOD(swoole_mysql_coro, __destruct)
   1087	{
-> 1088	    mysql_client *client = swoole_get_object(getThis());
   1089	    if (!client)
   1090	    {
   1091	        return;
Target 0: (php) stopped.
(lldb) list
   1092	    }
   1093	    if (client->state != SW_MYSQL_STATE_CLOSED && client->cli)
   1094	    {
   1095	        swoole_mysql_coro_close(getThis());
   1096	    }
   1097	    if (client->buffer)
   1098	    {
(lldb)
   1099	        swString_free(client->buffer);
   1100	    }
   1101	    efree(client);
   1102	    swoole_set_object(getThis(), NULL);
   1103
   1104	    php_context *context = swoole_get_property(getThis(), 0);
   1105	    if (!context)
(lldb)
   1106	    {
   1107	        return;
   1108	    }
   1109	    if (likely(context->state == SW_CORO_CONTEXT_RUNNING))
   1110	    {
   1111	        efree(context);
   1112	    }
(lldb)
   1113	    else
   1114	    {
   1115	        context->state = SW_CORO_CONTEXT_TERM;
   1116	    }
   1117	    swoole_set_property(getThis(), 0, NULL);
   1118	}
   1119
(lldb)
   1120	static PHP_METHOD(swoole_mysql_coro, close)
   1121	{
   1122	    if (swoole_mysql_coro_close(getThis()) == FAILURE)
   1123	    {
   1124	        RETURN_FALSE;
   1125	    }
   1126	#if PHP_MAJOR_VERSION < 7
(lldb)
   1127	    sw_zval_ptr_dtor(&getThis());
   1128	#endif
   1129		RETURN_TRUE;
   1130	}
```

在析构函数中的1095行, 和close函数中的1122行, 我们都可以看到调用了swoole_mysql_coro_close方法, 再次下断点调试

```c
* thread #1, queue = 'com.apple.main-thread', stop reason = breakpoint 2.1
    frame #0: 0x00000001030ae573 swoole.so`swoole_mysql_coro_close(this=0x0000000101c85230) at swoole_mysql_coro.c:180
   177 	static int swoole_mysql_coro_close(zval *this)
   178 	{
   179 	    SWOOLE_GET_TSRMLS;
-> 180 	    mysql_client *client = swoole_get_object(this);
   181 	    if (!client)
   182 	    {
   183 	        swoole_php_fatal_error(E_WARNING, "object is not instanceof swoole_mysql_coro.");
Target 0: (php) stopped.
(lldb) l
   184 	        return FAILURE;
   185 	    }
   186
   187 	    if (!client->cli)
   188 	    {
   189 	        return FAILURE;
   190 	    }
(lldb)
   191
   192 	    zend_update_property_bool(swoole_mysql_coro_class_entry_ptr, this, ZEND_STRL("connected"), 0 TSRMLS_CC);
   193 	    SwooleG.main_reactor->del(SwooleG.main_reactor, client->fd);
   194
   195 	    swConnection *_socket = swReactor_get(SwooleG.main_reactor, client->fd);
   196 	    _socket->object = NULL;
   197 	    _socket->active = 0;
(lldb)
   198
   199 	    if (client->timer)
   200 	    {
   201 	        swTimer_del(&SwooleG.timer, client->timer);
   202 	        client->timer = NULL;
   203 	    }
   204
(lldb)
   205 	    if (client->statement_list)
   206 	    {
   207 	        swLinkedList_node *node = client->statement_list->head;
   208 	        while (node)
   209 	        {
   210 	            mysql_statement *stmt = node->data;
   211 	            if (stmt->object)
(lldb)
   212 	            {
   213 	                swoole_set_object(stmt->object, NULL);
   214 	                efree(stmt->object);
   215 	            }
   216 	            efree(stmt);
   217 	            node = node->next;
   218 	        }
(lldb)
   219 	        swLinkedList_free(client->statement_list);
   220 	    }
   221
   222 	    client->cli->close(client->cli);
   223 	    swClient_free(client->cli);
   224 	    efree(client->cli);
   225 	    client->cli = NULL;
(lldb)
   226 	    client->state = SW_MYSQL_STATE_CLOSED;
   227 	    client->iowait = SW_MYSQL_CORO_STATUS_CLOSED;
   228
   229 	    return SUCCESS;
   230 	}
```

析构函数中可以看到一系列对自身的"清理操作", 因为对象要被销毁了.

而swoole_mysql_coro_close中可以看到一系列"关闭操作"和对该client所持有的statement们的清理操作, statement_list是一个链表, statement的标识ID是依赖于指定会话连接的, 索引ID从1开始, 连接关闭了所以statement必须在这时就销毁.

而222行的`client->cli->close(client->cli)`是用swoole的client进行了TCP连接关闭.

## 结论和进一步深思

所以我们可以发现, Swoole只对自己进行了清理, 并且关闭了TCP连接, 而没有在MySQL协议层面进行连接关闭, 这样会不会造成MySQL服务端还长期存在连接, 并没有销毁清理的情况呢?

首先, 在连接尚未关闭但是statement对象被销毁的时候, swoole并不会通知mysql去销毁语句模板, 所以要是长连接的时候有很多语句在swoole端一次性使用了的话, mysql那边应该会一直保存着那些语句模板, 等待这个连接下一次可能的使用.

### 验证: 查看未关闭的连接

而swoole端对tcp连接关闭后, mysql端没有收到mysql协议层面的关闭消息, 会不会还傻傻等着呢?

这时候我们可以运行一下脚本, 然后在mysql端使用`show full processlist`来查看连接:

```mysql
mysql> show full processlist;
+-----+------+-----------------+------+---------+------+----------+-----------------------+
| Id  | User | Host            | db   | Command | Time | State    | Info                  |
+-----+------+-----------------+------+---------+------+----------+-----------------------+
| 151 | root | localhost:58186 | NULL | Query   |    0 | starting | show full processlist |
+-----+------+-----------------+------+---------+------+----------+-----------------------+
1 row in set (0.00 sec)
```

Woo! 除了我们当前连接居然没有其他连接了, 说明MySQL在TCP连接关闭时就"智能"地清除了会话.

### 最后验证: 真的没有影响吗?

我们程序员要有刨根问底精神, 连接强制关闭了, 真的没有副作用吗?

```mysql
show status like '%Abort_%';
```

```mysq
+------------------+-------+
| Variable_name    | Value |
+------------------+-------+
| Aborted_clients  | 118   |
| Aborted_connects | 0     |
+------------------+-------+
2 rows in set (0.01 sec)
```

> Aborted_clients 由于客户没有正确关闭连接已经死掉，已经放弃的连接数量。
>
> Aborted_connects 尝试已经失败的MySQL服务器的连接的次数。 

可以看到, MySQL统计了异常中断的客户端和连接, 在我们近期的使用中, 没有正确关闭连接的客户端有118个

但是MySQL既然可以统计到该数据, 自然也可以对这些客户端连接进行正常清理, 比较还有一手TCP层面的逻辑在里头, 但是这样粗暴地关闭, 就像我们平时手机杀程序清内存或者强制关机的操作一样, 一般来说无甚危害, **但是万一哪天真的发生了异常, 客户端大量死掉, 我们也很难去发现了.**