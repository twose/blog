---
title: "[整理] MySQL协议分析"
date: 2018-05-15 16:05:26
tags: [mysql]
---

## 目录

[TOC]

## 1 交互过程

MySQL客户端与服务器的交互主要分为两个阶段：握手认证阶段和命令执行阶段。

### 1.1 握手认证阶段

握手认证阶段为客户端与服务器建立连接后进行，交互过程如下：

- 服务器 -> 客户端：握手初始化消息
- 客户端 -> 服务器：登陆认证消息
- 服务器 -> 客户端：认证结果消息

### 1.2 命令执行阶段

客户端认证成功后，会进入命令执行阶段，交互过程如下：

- 客户端 -> 服务器：执行命令消息
- 服务器 -> 客户端：命令执行结果

<!--more-->

**MySQL客户端与服务器的完整交互过程如下**：

![](https://ws1.sinaimg.cn/large/006DQdzWly1fsb7y00rsyj30cc0ddwew.jpg)

## 2 基本类型

### 2.1 整型值

MySQL报文中整型值分别有1、2、3、4、8字节长度，使用小字节序传输。

### 2.2 字符串（以NULL结尾）（Null-Terminated String）

字符串长度不固定，当遇到'NULL'（0x00）字符时结束。

### 2.3 二进制数据（长度编码）（Length Coded Binary）

数据长度不固定，长度值由数据前的1-9个字节决定，其中长度值所占的字节数不定，字节数由第1个字节决定，如下表：

| 第一个字节值 | 后续字节数 | 长度值说明                          |
| ------------ | ---------- | ----------------------------------- |
| 0-250        | 0          | 第一个字节值即为数据的真实长度      |
| 251          | 0          | 空数据，数据的真实长度为零          |
| 252          | 2          | 后续额外2个字节标识了数据的真实长度 |
| 253          | 3          | 后续额外3个字节标识了数据的真实长度 |
| 254          | 8          | 后续额外8个字节标识了数据的真实长度 |

### 2.4 字符串（长度编码）（Length Coded String）

字符串长度不固定，无'NULL'（0x00）结束符，编码方式与上面的 Length Coded Binary 相同。

## 3 报文结构

报文分为消息头和消息体两部分，其中消息头占用固定的4个字节，消息体长度由消息头中的长度字段决定，报文结构如下：

![MySQL报文结构](http://hutaow.com/images/articles/201311/mysql_protocol_struct.png)

### 3.1 消息头

#### 3.1.1 报文长度

用于标记当前请求消息的实际数据长度值，以字节为单位，占用3个字节，最大值为 0xFFFFFF，即接近 16 MB 大小（比16MB少1个字节）。

#### 3.1.2 序号

在一次完整的请求/响应交互过程中，用于保证消息顺序的正确，每次客户端发起请求时，序号值都会从0开始计算。

### 3.2 消息体

消息体用于存放请求的内容及响应的数据，长度由消息头中的长度值决定。

## 4 报文类型

### 4.1 登陆认证交互报文

#### 4.1.1 握手初始化报文（服务器 -> 客户端）

![MySQL握手初始化报文](http://hutaow.com/images/articles/201311/mysql_protocol_handshake.png)

**服务协议版本号**：该值由 PROTOCOL_VERSION 宏定义决定（参考MySQL源代码`/include/mysql_version.h`头文件定义）

**服务版本信息**：该值为字符串，由 MYSQL_SERVER_VERSION 宏定义决定（参考MySQL源代码`/include/mysql_version.h`头文件定义）

**服务器线程ID**：服务器为当前连接所创建的线程ID。

**挑战随机数**：MySQL数据库用户认证采用的是挑战/应答的方式，服务器生成该挑战数并发送给客户端，由客户端进行处理并返回相应结果，然后服务器检查是否与预期的结果相同，从而完成用户认证的过程。

**服务器权能标志**：用于与客户端协商通讯方式，各标志位含义如下（参考MySQL源代码`/include/mysql_com.h`中的宏定义）：

| 标志位名称               | 标志位      | 说明                               |
| ------------------------ | ----------- | ---------------------------------- |
| CLIENT_LONG_PASSWORD     | 0x0001      | new more secure passwords          |
| CLIENT_FOUND_ROWS        | 0x0002      | Found instead of affected rows     |
| CLIENT_LONG_FLAG         | 0x0004      | Get all column flags               |
| CLIENT_CONNECT_WITH_DB   | 0x0008      | One can specify db on connect      |
| CLIENT_NO_SCHEMA         | 0x0010      | Do not allow database.table.column |
| CLIENT_COMPRESS          | 0x0020      | Can use compression protocol       |
| CLIENT_ODBC              | 0x0040      | Odbc client                        |
| CLIENT_LOCAL_FILES       | 0x0080      | Can use LOAD DATA LOCAL            |
| CLIENT_IGNORE_SPACE      | 0x0100      | Ignore spaces before '('           |
| CLIENT_PROTOCOL_41       | 0x0200      | New 4.1 protocol                   |
| CLIENT_INTERACTIVE       | 0x0400      | This is an interactive client      |
| CLIENT_SSL               | 0x0800      | Switch to SSL after handshake      |
| CLIENT_IGNORE_SIGPIPE    | 0x1000      | IGNORE sigpipes                    |
| CLIENT_TRANSACTIONS      | 0x2000      | Client knows about transactions    |
| CLIENT_RESERVED          | 0x4000      | Old flag for 4.1 protocol          |
| CLIENT_SECURE_CONNECTION | 0x8000      | New 4.1 authentication             |
| CLIENT_MULTI_STATEMENTS  | 0x0001 0000 | Enable/disable multi-stmt support  |
| CLIENT_MULTI_RESULTS     | 0x0002 0000 | Enable/disable multi-results       |

**字符编码**：标识服务器所使用的字符集。

**服务器状态**：状态值定义如下（参考MySQL源代码`/include/mysql_com.h`中的宏定义）：

| 状态名称                           | 状态值 |
| ---------------------------------- | ------ |
| SERVER_STATUS_IN_TRANS             | 0x0001 |
| SERVER_STATUS_AUTOCOMMIT           | 0x0002 |
| SERVER_STATUS_CURSOR_EXISTS        | 0x0040 |
| SERVER_STATUS_LAST_ROW_SENT        | 0x0080 |
| SERVER_STATUS_DB_DROPPED           | 0x0100 |
| SERVER_STATUS_NO_BACKSLASH_ESCAPES | 0x0200 |
| SERVER_STATUS_METADATA_CHANGED     | 0x0400 |

#### 4.1.2 登陆认证报文（客户端 -> 服务器）

**MySQL 4.0 及之前的版本**

![MySQL登陆认证报文(4.0及之前的版本)](http://hutaow.com/images/articles/201311/mysql_protocol_auth_40.png)

**MySQL 4.1 及之后的版本**

![MySQL登陆认证报文(4.1及之后的版本)](http://hutaow.com/images/articles/201311/mysql_protocol_auth_41.png)

**客户端权能标志**：用于与客户端协商通讯方式，标志位含义与握手初始化报文中的相同。客户端收到服务器发来的初始化报文后，会对服务器发送的权能标志进行修改，保留自身所支持的功能，然后将权能标返回给服务器，从而保证服务器与客户端通讯的兼容性。

**最大消息长度**：客户端发送请求报文时所支持的最大消息长度值。

**字符编码**：标识通讯过程中使用的字符编码，与服务器在认证初始化报文中发送的相同。

**用户名**：客户端登陆用户的用户名称。

**挑战认证数据**：客户端用户密码使用服务器发送的挑战随机数进行加密后，生成挑战认证数据，然后返回给服务器，用于对用户身份的认证。

**数据库名称**：当客户端的权能标志位 CLIENT_CONNECT_WITH_DB 被置位时，该字段必须出现。

### 4.2 客户端命令请求报文（客户端 -> 服务器）

![MySQL客户端命令请求报文](http://hutaow.com/images/articles/201311/mysql_protocol_command.png)

**命令**：用于标识当前请求消息的类型，例如切换数据库（0x02）、查询命令（0x03）等。命令值的取值范围及说明如下表（参考MySQL源代码`/include/mysql_com.h`头文件中的定义）：

| 类型值 | 命令                    | 功能                       | 关联函数                  |
| ------ | ----------------------- | -------------------------- | ------------------------- |
| 0x00   | COM_SLEEP               | （内部线程状态）           | （无）                    |
| 0x01   | COM_QUIT                | 关闭连接                   | mysql_close               |
| 0x02   | COM_INIT_DB             | 切换数据库                 | mysql_select_db           |
| 0x03   | COM_QUERY               | SQL查询请求                | mysql_real_query          |
| 0x04   | COM_FIELD_LIST          | 获取数据表字段信息         | mysql_list_fields         |
| 0x05   | COM_CREATE_DB           | 创建数据库                 | mysql_create_db           |
| 0x06   | COM_DROP_DB             | 删除数据库                 | mysql_drop_db             |
| 0x07   | COM_REFRESH             | 清除缓存                   | mysql_refresh             |
| 0x08   | COM_SHUTDOWN            | 停止服务器                 | mysql_shutdown            |
| 0x09   | COM_STATISTICS          | 获取服务器统计信息         | mysql_stat                |
| 0x0A   | COM_PROCESS_INFO        | 获取当前连接的列表         | mysql_list_processes      |
| 0x0B   | COM_CONNECT             | （内部线程状态）           | （无）                    |
| 0x0C   | COM_PROCESS_KILL        | 中断某个连接               | mysql_kill                |
| 0x0D   | COM_DEBUG               | 保存服务器调试信息         | mysql_dump_debug_info     |
| 0x0E   | COM_PING                | 测试连通性                 | mysql_ping                |
| 0x0F   | COM_TIME                | （内部线程状态）           | （无）                    |
| 0x10   | COM_DELAYED_INSERT      | （内部线程状态）           | （无）                    |
| 0x11   | COM_CHANGE_USER         | 重新登陆（不断连接）       | mysql_change_user         |
| 0x12   | COM_BINLOG_DUMP         | 获取二进制日志信息         | （无）                    |
| 0x13   | COM_TABLE_DUMP          | 获取数据表结构信息         | （无）                    |
| 0x14   | COM_CONNECT_OUT         | （内部线程状态）           | （无）                    |
| 0x15   | COM_REGISTER_SLAVE      | 从服务器向主服务器进行注册 | （无）                    |
| 0x16   | COM_STMT_PREPARE        | 预处理SQL语句              | mysql_stmt_prepare        |
| 0x17   | COM_STMT_EXECUTE        | 执行预处理语句             | mysql_stmt_execute        |
| 0x18   | COM_STMT_SEND_LONG_DATA | 发送BLOB类型的数据         | mysql_stmt_send_long_data |
| 0x19   | COM_STMT_CLOSE          | 销毁预处理语句             | mysql_stmt_close          |
| 0x1A   | COM_STMT_RESET          | 清除预处理语句参数缓存     | mysql_stmt_reset          |
| 0x1B   | COM_SET_OPTION          | 设置语句选项               | mysql_set_server_option   |
| 0x1C   | COM_STMT_FETCH          | 获取预处理语句的执行结果   | mysql_stmt_fetch          |

**参数**：内容是用户在MySQL客户端输入的命令（不包括每行命令结尾的";"分号）。另外这个字段的字符串不是以NULL字符结尾，而是通过消息头中的长度值计算而来。

例如：当我们在MySQL客户端中执行`use hutaow;`命令时（切换到`hutaow`数据库），发送的请求报文数据会是下面的样子：

```
0x02 0x68 0x75 0x74 0x61 0x6f 0x77
```

其中，`0x02`为请求类型值`COM_INIT_DB`，后面的`0x68 0x75 0x74 0x61 0x6f 0x77`为ASCII字符`hutaow`。

#### 4.2.1 COM_QUIT 消息报文

**功能**：关闭当前连接（客户端退出），无参数。

#### 4.2.2 COM_INIT_DB 消息报文

**功能**：切换数据库，对应的SQL语句为`USE <database>`。

| 字节 | 说明                                             |
| ---- | ------------------------------------------------ |
| n    | 数据库名称（字符串到达消息尾部时结束，无结束符） |

#### 4.2.3 COM_QUERY 消息报文

**功能**：最常见的请求消息类型，当用户执行SQL语句时发送该消息。

| 字节 | 说明                                          |
| ---- | --------------------------------------------- |
| n    | SQL语句（字符串到达消息尾部时结束，无结束符） |

#### 4.2.4 COM_FIELD_LIST 消息报文

**功能**：查询某表的字段（列）信息，等同于SQL语句`SHOW [FULL] FIELDS FROM ...`。

| 字节 | 说明                               |
| ---- | ---------------------------------- |
| n    | 表格名称（Null-Terminated String） |
| n    | 字段（列）名称或通配符（可选）     |

#### 4.2.5 COM_CREATE_DB 消息报文

**功能**：创建数据库，该消息已过时，而被SQL语句`CREATE DATABASE`代替。

| 字节 | 说明                                             |
| ---- | ------------------------------------------------ |
| n    | 数据库名称（字符串到达消息尾部时结束，无结束符） |

#### 4.2.6 COM_DROP_DB 消息报文

**功能**：删除数据库，该消息已过时，而被SQL语句`DROP DATABASE`代替。

| 字节 | 说明                                             |
| ---- | ------------------------------------------------ |
| n    | 数据库名称（字符串到达消息尾部时结束，无结束符） |

#### 4.2.7 COM_REFRESH 消息报文

**功能**：清除缓存，等同于SQL语句`FLUSH`，或是执行`mysqladmin flush-foo`命令时发送该消息。

| 字节 | 说明                                           |
| ---- | ---------------------------------------------- |
| 1    | 清除缓存选项（位图方式存储，各标志位含义如下） |
|      | 0x01: REFRESH_GRANT                            |
|      | 0x02: REFRESH_LOG                              |
|      | 0x04: REFRESH_TABLES                           |
|      | 0x08: REFRESH_HOSTS                            |
|      | 0x10: REFRESH_STATUS                           |
|      | 0x20: REFRESH_THREADS                          |
|      | 0x40: REFRESH_SLAVE                            |
|      | 0x80: REFRESH_MASTER                           |

#### 4.2.8 COM_SHUTDOWN 消息报文

**功能**：停止MySQL服务。执行`mysqladmin shutdown`命令时发送该消息。

| 字节 | 说明                                 |
| ---- | ------------------------------------ |
| 1    | 停止服务选项                         |
|      | 0x00: SHUTDOWN_DEFAULT               |
|      | 0x01: SHUTDOWN_WAIT_CONNECTIONS      |
|      | 0x02: SHUTDOWN_WAIT_TRANSACTIONS     |
|      | 0x08: SHUTDOWN_WAIT_UPDATES          |
|      | 0x10: SHUTDOWN_WAIT_ALL_BUFFERS      |
|      | 0x11: SHUTDOWN_WAIT_CRITICAL_BUFFERS |
|      | 0xFE: KILL_QUERY                     |
|      | 0xFF: KILL_CONNECTION                |

#### 4.2.9 COM_STATISTICS 消息报文

**功能**：查看MySQL服务的统计信息（例如运行时间、每秒查询次数等）。执行`mysqladmin status`命令时发送该消息，无参数。

#### 4.2.10 COM_PROCESS_INFO 消息报文

**功能**：获取当前活动的线程（连接）列表。等同于SQL语句`SHOW PROCESSLIST`，或是执行`mysqladmin processlist`命令时发送该消息，无参数。

#### 4.2.11 COM_PROCESS_KILL 消息报文

**功能**：要求服务器中断某个连接。等同于SQL语句`KILL <id>`。

| 字节 | 说明                 |
| ---- | -------------------- |
| 4    | 连接ID号（小字节序） |

#### 4.2.12 COM_DEBUG 消息报文

**功能**：要求服务器将调试信息保存下来，保存的信息多少依赖于编译选项设置（debug=no|yes|full）。执行`mysqladmin debug`命令时发送该消息，无参数。

#### 4.2.13 COM_PING 消息报文

**功能**：该消息用来测试连通性，同时会将服务器的无效连接（超时）计数器清零。执行`mysqladmin ping`命令时发送该消息，无参数。

#### 4.2.14 COM_CHANGE_USER 消息报文

**功能**：在不断连接的情况下重新登陆，该操作会销毁MySQL服务器端的会话上下文（包括临时表、会话变量等）。有些连接池用这种方法实现清除会话上下文。

| 字节 | 说明                                                 |
| ---- | ---------------------------------------------------- |
| n    | 用户名（字符串以NULL结尾）                           |
| n    | 密码（挑战数）                                       |
|      | MySQL 3.23 版本：Null-Terminated String（长度9字节） |
|      | MySQL 4.1 版本：Length Coded String（长度1+21字节）  |
| n    | 数据库名称（Null-Terminated String）                 |
| 2    | 字符编码                                             |

#### 4.2.15 COM_BINLOG_DUMP 消息报文

**功能**：该消息是备份连接时由从服务器向主服务器发送的最后一个请求，主服务器收到后，会响应一系列的报文，每个报文都包含一个二进制日志事件。如果主服务器出现故障时，会发送一个EOF报文。

| 字节 | 说明                                                         |
| ---- | ------------------------------------------------------------ |
| 4    | 二进制日志数据的起始位置（小字节序）                         |
| 4    | 二进制日志数据标志位（目前未使用，永远为0x00）               |
| 4    | 从服务器的服务器ID值（小字节序）                             |
| n    | 二进制日志的文件名称（可选，默认值为主服务器上第一个有效的文件名） |

#### 4.2.16 COM_TABLE_DUMP 消息报文

**功能**：将数据表从主服务器复制到从服务器中，执行SQL语句`LOAD TABLE ... FROM MASTER`时发送该消息。目前该消息已过时，不再使用。

| 字节 | 说明                              |
| ---- | --------------------------------- |
| n    | 数据库名称（Length Coded String） |
| n    | 数据表名称（Length Coded String） |

#### 4.2.17 COM_REGISTER_SLAVE 消息报文

**功能**：在从服务器`report_host`变量设置的情况下，当备份连接时向主服务器发送的注册消息。

| 字节 | 说明                                                         |
| ---- | ------------------------------------------------------------ |
| 4    | 从服务器ID值（小字节序）                                     |
| n    | 主服务器IP地址（Length Coded String）                        |
| n    | 主服务器用户名（Length Coded String）                        |
| n    | 主服务器密码（Length Coded String）                          |
| 2    | 主服务器端口号                                               |
| 4    | 安全备份级别（由MySQL服务器`rpl_recovery_rank`变量设置，暂时未使用） |
| 4    | 主服务器ID值（值恒为0x00）                                   |

#### 4.2.18 COM_PREPARE 消息报文

**功能**：预处理SQL语句，使用带有"?"占位符的SQL语句时发送该消息。

| 字节 | 说明                                                         |
| ---- | ------------------------------------------------------------ |
| n    | 带有"?"占位符的SQL语句（字符串到达消息尾部时结束，无结束符） |

#### 4.2.19 COM_EXECUTE 消息报文

**功能**：执行预处理语句。

| 字节                  | 说明                                                  |
| --------------------- | ----------------------------------------------------- |
| 4                     | 预处理语句的ID值                                      |
| 1                     | 标志位                                                |
|                       | 0x00: CURSOR_TYPE_NO_CURSOR                           |
|                       | 0x01: CURSOR_TYPE_READ_ONLY                           |
|                       | 0x02: CURSOR_TYPE_FOR_UPDATE                          |
|                       | 0x04: CURSOR_TYPE_SCROLLABLE                          |
| 4                     | 保留（值恒为0x01）                                    |
| 如果参数数量大于0     |                                                       |
| n                     | 空位图（Null-Bitmap，长度 = (参数数量 + 7) / 8 字节） |
| 1                     | 参数分隔标志                                          |
| 如果参数分隔标志值为1 |                                                       |
| n                     | 每个参数的类型值（长度 = 参数数量 * 2 字节）          |
| n                     | 每个参数的值                                          |

#### 4.2.20 COM_LONG_DATA 消息报文

该消息报文有两种形式，一种用于发送二进制数据，另一种用于发送文本数据。

**功能**：用于发送二进制（BLOB）类型的数据（调用`mysql_stmt_send_long_data`函数）。

| 字节 | 说明                                         |
| ---- | -------------------------------------------- |
| 4    | 预处理语句的ID值（小字节序）                 |
| 2    | 参数序号（小字节序）                         |
| n    | 数据负载（数据到达消息尾部时结束，无结束符） |

**功能**：用于发送超长字符串类型的数据（调用`mysql_send_long_data`函数）

| 字节 | 说明                                         |
| ---- | -------------------------------------------- |
| 4    | 预处理语句的ID值（小字节序）                 |
| 2    | 参数序号（小字节序）                         |
| 2    | 数据类型（未使用）                           |
| n    | 数据负载（数据到达消息尾部时结束，无结束符） |

#### 4.2.21 COM_CLOSE_STMT 消息报文

**功能**：销毁预处理语句。

| 字节 | 说明                         |
| ---- | ---------------------------- |
| 4    | 预处理语句的ID值（小字节序） |

#### 4.2.22 COM_RESET_STMT 消息报文

**功能**：将预处理语句的参数缓存清空。多数情况和`COM_LONG_DATA`一起使用。

| 字节 | 说明                         |
| ---- | ---------------------------- |
| 4    | 预处理语句的ID值（小字节序） |

#### 4.2.23 COM_SET_OPTION 消息报文

**功能**：设置语句选项，选项值为`/include/mysql_com.h`头文件中定义的`enum_mysql_set_option`枚举类型：

- MYSQL_OPTION_MULTI_STATEMENTS_ON
- MYSQL_OPTION_MULTI_STATEMENTS_OFF

| 字节 | 说明               |
| ---- | ------------------ |
| 2    | 选项值（小字节序） |

#### 4.2.24 COM_FETCH_STMT 消息报文

**功能**：获取预处理语句的执行结果（一次可以获取多行数据）。

| 字节 | 说明                         |
| ---- | ---------------------------- |
| 4    | 预处理语句的ID值（小字节序） |
| 4    | 数据的行数（小字节序）       |

### 4.3 服务器响应报文（服务器 -> 客户端）

当客户端发起认证请求或命令请求后，服务器会返回相应的执行结果给客户端。客户端在收到响应报文后，需要首先检查第1个字节的值，来区分响应报文的类型。

| 响应报文类型    | 第1个字节取值范围 |
| --------------- | ----------------- |
| OK 响应报文     | 0x00              |
| Error 响应报文  | 0xFF              |
| Result Set 报文 | 0x01 - 0xFA       |
| Field 报文      | 0x01 - 0xFA       |
| Row Data 报文   | 0x01 - 0xFA       |
| EOF 报文        | 0xFE              |

注：响应报文的第1个字节在不同类型中含义不同，比如在OK报文中，该字节并没有实际意义，值恒为0x00；而在Result Set报文中，该字节又是长度编码的二进制数据结构（Length Coded Binary）中的第1字节。

#### 4.3.1 OK 响应报文

客户端的命令执行正确时，服务器会返回OK响应报文。

**MySQL 4.0 及之前的版本**

| 字节 | 说明                                             |
| ---- | ------------------------------------------------ |
| 1    | OK报文，值恒为0x00                               |
| 1-9  | 受影响行数（Length Coded Binary）                |
| 1-9  | 索引ID值（Length Coded Binary）                  |
| 2    | 服务器状态                                       |
| n    | 服务器消息（字符串到达消息尾部时结束，无结束符） |

**MySQL 4.1 及之后的版本**

| 字节 | 说明                                                   |
| ---- | ------------------------------------------------------ |
| 1    | OK报文，值恒为0x00                                     |
| 1-9  | 受影响行数（Length Coded Binary）                      |
| 1-9  | 索引ID值（Length Coded Binary）                        |
| 2    | 服务器状态                                             |
| 2    | 告警计数                                               |
| n    | 服务器消息（字符串到达消息尾部时结束，无结束符，可选） |

**受影响行数**：当执行`INSERT`/`UPDATE`/`DELETE`语句时所影响的数据行数。

**索引ID值**：该值为`AUTO_INCREMENT`索引字段生成，如果没有索引字段，则为0x00。注意：当`INSERT`插入语句为多行数据时，该索引ID值为第一个插入的数据行索引值，而非最后一个。

**服务器状态**：客户端可以通过该值检查命令是否在事务处理中。

**告警计数**：告警发生的次数。

**服务器消息**：服务器返回给客户端的消息，一般为简单的描述性字符串，可选字段。

#### 4.3.2 Error 响应报文

**MySQL 4.0 及之前的版本**

| 字节 | 说明                  |
| ---- | --------------------- |
| 1    | Error报文，值恒为0xFF |
| 2    | 错误编号（小字节序）  |
| n    | 服务器消息            |

**MySQL 4.1 及之后的版本**

| 字节 | 说明                        |
| ---- | --------------------------- |
| 1    | Error报文，值恒为0xFF       |
| 2    | 错误编号（小字节序）        |
| 1    | 服务器状态标志，恒为'#'字符 |
| 5    | 服务器状态（5个字符）       |
| n    | 服务器消息                  |

**错误编号**：错误编号值定义在源代码`/include/mysqld_error.h`头文件中。

**服务器状态**：服务器将错误编号通过`mysql_errno_to_sqlstate`函数转换为状态值，状态值由5字节的ASCII字符组成，定义在源代码`/include/sql_state.h`头文件中。

**服务器消息**：错误消息字符串到达消息尾时结束，长度可以由消息头中的长度值计算得出。消息长度为0-512字节。

#### 4.3.3 Result Set 消息

当客户端发送查询请求后，在没有错误的情况下，服务器会返回结果集（Result Set）给客户端。

Result Set 消息分为五部分，结构如下：

| 结构                | 说明           |
| ------------------- | -------------- |
| [Result Set Header] | 列数量         |
| [Field]             | 列信息（多个） |
| [EOF]               | 列结束         |
| [Row Data]          | 行数据（多个） |
| [EOF]               | 数据结束       |

#### 4.3.4 Result Set Header 结构

| 字节 | 说明                                 |
| ---- | ------------------------------------ |
| 1-9  | Field结构计数（Length Coded Binary） |
| 1-9  | 额外信息（Length Coded Binary）      |

**Field结构计数**：用于标识Field结构的数量，取值范围0x00-0xFA。

**额外信息**：可选字段，一般情况下不应该出现。只有像`SHOW COLUMNS`这种语句的执行结果才会用到额外信息（标识表格的列数量）。

#### 4.3.5 Field 结构

Field为数据表的列信息，在Result Set中，Field会连续出现多次，次数由Result Set Header结构中的IField结构计数值决定。

**MySQL 4.0 及之前的版本**

| 字节 | 说明                                  |
| ---- | ------------------------------------- |
| n    | 数据表名称（Length Coded String）     |
| n    | 列（字段）名称（Length Coded String） |
| 4    | 列（字段）长度（Length Coded String） |
| 2    | 列（字段）类型（Length Coded String） |
| 2    | 列（字段）标志（Length Coded String） |
| 1    | 整型值精度                            |
| n    | 默认值（Length Coded String）         |

**MySQL 4.1 及之后的版本**

| 字节 | 说明                                      |
| ---- | ----------------------------------------- |
| n    | 目录名称（Length Coded String）           |
| n    | 数据库名称（Length Coded String）         |
| n    | 数据表名称（Length Coded String）         |
| n    | 数据表原始名称（Length Coded String）     |
| n    | 列（字段）名称（Length Coded String）     |
| 4    | 列（字段）原始名称（Length Coded String） |
| 1    | 填充值                                    |
| 2    | 字符编码                                  |
| 4    | 列（字段）长度                            |
| 1    | 列（字段）类型                            |
| 2    | 列（字段）标志                            |
| 1    | 整型值精度                                |
| 2    | 填充值（0x00）                            |
| n    | 默认值（Length Coded String）             |

**目录名称**：在4.1及之后的版本中，该字段值为"def"。

**数据库名称**：数据库名称标识。

**数据表名称**：数据表的别名（`AS`之后的名称）。

**数据表原始名称**：数据表的原始名称（`AS`之前的名称）。

**列（字段）名称**：列（字段）的别名（`AS`之后的名称）。

**列（字段）原始名称**：列（字段）的原始名称（`AS`之前的名称）。

**字符编码**：列（字段）的字符编码值。

**列（字段）长度**：列（字段）的长度值，真实长度可能小于该值，例如`VARCHAR(2)`类型的字段实际只能存储1个字符。

**列（字段）类型**：列（字段）的类型值，取值范围如下（参考源代码`/include/mysql_com.h`头文件中的`enum_field_type`枚举类型定义）：

| 类型值 | 名称                                     |
| ------ | ---------------------------------------- |
| 0x00   | FIELD_TYPE_DECIMAL                       |
| 0x01   | FIELD_TYPE_TINY                          |
| 0x02   | FIELD_TYPE_SHORT                         |
| 0x03   | FIELD_TYPE_LONG                          |
| 0x04   | FIELD_TYPE_FLOAT                         |
| 0x05   | FIELD_TYPE_DOUBLE                        |
| 0x06   | FIELD_TYPE_NULL                          |
| 0x07   | FIELD_TYPE_TIMESTAMP                     |
| 0x08   | FIELD_TYPE_LONGLONG                      |
| 0x09   | FIELD_TYPE_INT24                         |
| 0x0A   | FIELD_TYPE_DATE                          |
| 0x0B   | FIELD_TYPE_TIME                          |
| 0x0C   | FIELD_TYPE_DATETIME                      |
| 0x0D   | FIELD_TYPE_YEAR                          |
| 0x0E   | FIELD_TYPE_NEWDATE                       |
| 0x0F   | FIELD_TYPE_VARCHAR (new in MySQL 5.0)    |
| 0x10   | FIELD_TYPE_BIT (new in MySQL 5.0)        |
| 0xF6   | FIELD_TYPE_NEWDECIMAL (new in MYSQL 5.0) |
| 0xF7   | FIELD_TYPE_ENUM                          |
| 0xF8   | FIELD_TYPE_SET                           |
| 0xF9   | FIELD_TYPE_TINY_BLOB                     |
| 0xFA   | FIELD_TYPE_MEDIUM_BLOB                   |
| 0xFB   | FIELD_TYPE_LONG_BLOB                     |
| 0xFC   | FIELD_TYPE_BLOB                          |
| 0xFD   | FIELD_TYPE_VAR_STRING                    |
| 0xFE   | FIELD_TYPE_STRING                        |
| 0xFF   | FIELD_TYPE_GEOMETRY                      |

**列（字段）标志**：各标志位定义如下（参考源代码`/include/mysql_com.h`头文件中的宏定义）：

| 标志位 | 名称                |
| ------ | ------------------- |
| 0x0001 | NOT_NULL_FLAG       |
| 0x0002 | PRI_KEY_FLAG        |
| 0x0004 | UNIQUE_KEY_FLAG     |
| 0x0008 | MULTIPLE_KEY_FLAG   |
| 0x0010 | BLOB_FLAG           |
| 0x0020 | UNSIGNED_FLAG       |
| 0x0040 | ZEROFILL_FLAG       |
| 0x0080 | BINARY_FLAG         |
| 0x0100 | ENUM_FLAG           |
| 0x0200 | AUTO_INCREMENT_FLAG |
| 0x0400 | TIMESTAMP_FLAG      |
| 0x0800 | SET_FLAG            |

**数值精度**：该字段对`DECIMAL`和`NUMERIC`类型的数值字段有效，用于标识数值的精度（小数点位置）。

**默认值**：该字段用在数据表定义中，普通的查询结果中不会出现。

**附**：Field结构的相关处理函数：

- 客户端：`/client/client.c`源文件中的`unpack_fields`函数
- 服务器：`/sql/sql_base.cc`源文件中的`send_fields`函数

#### 4.3.6 EOF 结构

EOF结构用于标识Field和Row Data的结束，在预处理语句中，EOF也被用来标识参数的结束。

**MySQL 4.0 及之前的版本**

| 字节 | 说明          |
| ---- | ------------- |
| 1    | EOF值（0xFE） |

**MySQL 4.1 及之后的版本**

| 字节 | 说明          |
| ---- | ------------- |
| 1    | EOF值（0xFE） |
| 2    | 告警计数      |
| 2    | 状态标志位    |

**告警计数**：服务器告警数量，在所有数据都发送给客户端后该值才有效。

**状态标志位**：包含类似`SERVER_MORE_RESULTS_EXISTS`这样的标志位。

**注**：由于EOF值与其它Result Set结构共用1字节，所以在收到报文后需要对EOF包的真实性进行校验，校验条件为：

- 第1字节值为0xFE
- 包长度小于9字节

**附**：EOF结构的相关处理函数：

- 服务器：`protocol.cc`源文件中的`send_eof`函数

#### 4.3.7 Row Data 结构

在Result Set消息中，会包含多个Row Data结构，每个Row Data结构又包含多个字段值，这些字段值组成一行数据。

| 字节 | 说明                          |
| ---- | ----------------------------- |
| n    | 字段值（Length Coded String） |
| ...  | （一行数据中包含多个字段值）  |

**字段值**：行数据中的字段值，字符串形式。

**附**：Row Data结构的相关处理函数：

- 客户端：`/client/client.c`源文件中的`read_rows`函数

#### 4.3.8 Row Data 结构（二进制数据）

该结构用于传输二进制的字段值，既可以是服务器返回的结果，也可以是由客户端发送的（当执行预处理语句时，客户端使用Result Set消息来发送参数及数据）。

| 字节                 | 说明                         |
| -------------------- | ---------------------------- |
| 1                    | 结构头（0x00）               |
| (列数量 + 7 + 2) / 8 | 空位图                       |
| n                    | 字段值                       |
| ...                  | （一行数据中包含多个字段值） |

**空位图**：前2个比特位被保留，值分别为0和1，以保证不会和OK、Error包的首字节冲突。在MySQL 5.0及之后的版本中，这2个比特位的值都为0。

**字段值**：行数据中的字段值，二进制形式。

#### 4.3.9 PREPARE_OK 响应报文（Prepared Statement）

用于响应客户端发起的预处理语句报文，组成结构如下：

| 结构              | 说明                     |
| ----------------- | ------------------------ |
| [PREPARE_OK]      | PREPARE_OK结构           |
| 如果参数数量大于0 |                          |
| [Field]           | 与Result Set消息结构相同 |
| [EOF]             |                          |
| 如果列数大于0     |                          |
| [Field]           | 与Result Set消息结构相同 |
| [EOF]             |                          |

其中 PREPARD_OK 的结构如下：

| 字节 | 说明             |
| ---- | ---------------- |
| 1    | OK报文，值为0x00 |
| 4    | 预处理语句ID值   |
| 2    | 列数量           |
| 2    | 参数数量         |
| 1    | 填充值（0x00）   |
| 2    | 告警计数         |

#### 4.3.10 Parameter 响应报文（Prepared Statement）

预处理语句的值与参数正确对应后，服务器会返回 Parameter 报文。

| 字节 | 说明     |
| ---- | -------- |
| 2    | 类型     |
| 2    | 标志     |
| 1    | 数值精度 |
| 4    | 字段长度 |

**类型**：与 Field 结构中的字段类型相同。

**标志**：与 Field 结构中的字段标志相同。

**数值精度**：与 Field 结构中的数值精度相同。

**字段长度**：与 Field 结构中的字段长度相同。



##  5 参考资料

《[MySQL Internals Manual](http://dev.mysql.com/doc/internals/en/index.html): [MySQL Client/Server Protocol](http://dev.mysql.com/doc/internals/en/client-server-protocol.html)》

## 来源
http://hutaow.com/blog/2013/11/06/mysql-protocol-analysis/