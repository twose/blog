---
title: "[转] 2018 PHP 应用程序安全设计指北"
date: 2018-01-06 01:33:18
tags: [php,security,hack]
---

> The 2018 Guide to Building Secure PHP Software！

## 前言

2018 年将至，一般程序员（特别是 Web 开发程序员）应当抛弃过去开发PHP程序的很多不好的习惯和观念了。虽然部分人不以为意，但是这确实是事实。

这个指南应该以重点部分作为 [PHP: The Right Way](https://link.juejin.im/?target=http%3A%2F%2Fwww.phptherightway.com%2F) 安全章节的补充，而不是以一般的 PHP 编程话题。

## 正文

## PHP 版本

> 请在 2018 年使用 PHP 7.2, 并且计划 2019 年初切换到 PHP 7.3。

PHP 7.2 已于 2017 年 11 月 30 日发布。

写这篇文章的时候，只有 7.1 和 7.2 版本还在被 PHP 官方积极维护，而 5.6 和 7.0 只在大概1年内提供安全补丁更新。

对于其他官方不维护的 PHP 版本，虽然某些操作系统会提供长期支持和维护，但这其实通常是有害的。尤其是他们提供安全支持补丁却没有版本号，这使得很难解释系统的安全性（仅仅知道 PHP 版本）。

因此，无论其他供应商提出了什么承诺，如果可以，你就应该在任何时候都坚决地使用[官方提供支持的 PHP 版本](https://link.juejin.im/?target=http%3A%2F%2Fphp.net%2Fsupported-versions.php)。这样，尽管最终是一个短暂的安全版本，但一个不断致力于升级的版本，总会让你收获一些意外的惊喜。

<!--more-->

## 依赖管理

> 人生苦短，我用 Composer

在 PHP 生态中，[Composer](https://link.juejin.im/?target=https%3A%2F%2Fgetcomposer.org%2F) 是最先进的依赖管理方案。我们推荐 PHP: The Right Way 中关于[依赖管理](https://link.juejin.im/?target=http%3A%2F%2Fwww.phptherightway.com%2F%23dependency_management)的完整章节。

如果你没有使用 Composer 来管理应用的依赖，最终（hopefully later but most likely sooner）会导致应用里某个依赖会严重过时，然后老旧版本中的漏洞会被利用于计算机犯罪。

**重要**： 开发软件时，时常记得[保持依赖的更新](https://link.juejin.im/?target=http%3A%2F%2Fwww.phptherightway.com%2F%23updating-your-dependencies)。幸运地，这只需一行命令：

```Bash
composer update
```

如果你正在使用某些专业的，需要使用 PHP 扩展（C 语言编写），那你不能使用 Composer 管理，而需要 PECL 。

### 推荐扩展

不管你正在编写什么，你总会受益于这些依赖。这是除了大多数 PHP 程序员的推荐（PHPUnit, PHP-CS-Fixer, ...）外的补充。

**roave/security-advisories**

[Roave's security-advisories](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2FRoave%2FSecurityAdvisories) 使用 [Friends of PHP repository](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2FFriendsOfPHP%2Fsecurity-advisories) 确保你的项目没有依赖一些已知易受攻击的依赖。

```Bash
composer require roave/security-advisories:dev-master
```

或者，你可以[上传你的`composer.lock`文件到 Sensio Labs ](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2FFriendsOfPHP%2Fsecurity-advisories%23checking-for-vulnerabilities)，作为例行自动化漏洞评估工作流的一部分，以提醒发现任何过时的软件包。

**vimeo/psalm**

[Psalm ](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fvimeo%2Fpsalm)是一个帮助你识别代码里可能存在 bugs 的静态分析工具。还有其他很好的静态分析工具（例如 [Phan](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fphan%2Fphan) 和 [PHPStan](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fphpstan%2Fphpstan) 都很棒），但当你发现你需要支持 PHP 5，Psalm 将是 PHP 5.4+ 的首选。

使用 Psalm 挺简单：

```Bash
# Version 1 doesn't exist yet, but it will one day:
composer require --dev vimeo/psalm:^0

# Only do this once:
vendor/bin/psalm --init

# Do this as often as you need:
vendor/bin/psalm
```

如果你是第一次在现有代码库运行，可能会看到很多红色错误。但除非你在构建像 WordPress 那么大的程序，否则努力通过所有测试绝不是艰巨的。

无论使用哪种静态分析工具，我们都推荐你能将他加入到[持续集成工作流](https://link.juejin.im/?target=https%3A%2F%2Fzh.wikipedia.org%2Fwiki%2F%25E6%258C%2581%25E7%25BA%258C%25E6%2595%25B4%25E5%2590%2588)（Continuous Integration workflow）中，以便在每次更改代码中运行。

## HTTPS 和浏览器安全

> HTTPS, [which should be tested](https://link.juejin.im/?target=https%3A%2F%2Fwww.ssllabs.com%2F), and [security headers](https://link.juejin.im/?target=https%3A%2F%2Fsecurityheaders.io%2F) .

2018 年，不安全的 HTTP 网站将不再被接受。幸运的是，由于 ACME 协议 和 [Let's Encrypt certificate authority](https://link.juejin.im/?target=https%3A%2F%2Fletsencrypt.org%2F)，免费的 TLS 证书成为了可能。

将 ACME 集成到你的服务器，小菜一碟。

- [Caddy](https://link.juejin.im/?target=https%3A%2F%2Fcaddyserver.com%2F): 自动加入。
- [Apache](https://link.juejin.im/?target=https%3A%2F%2Fletsencrypt.org%2F2017%2F10%2F17%2Facme-support-in-apache-httpd.html): 很快作为`mod_md`可用。在此之前，[网上很多高质量教程](https://link.juejin.im/?target=https%3A%2F%2Fwww.digitalocean.com%2Fcommunity%2Ftutorials%2Fhow-to-secure-apache-with-let-s-encrypt-on-ubuntu-16-04)。
- [Nginx](https://link.juejin.im/?target=https%3A%2F%2Fwww.nginx.com%2Fblog%2Fusing-free-ssltls-certificates-from-lets-encrypt-with-nginx%2F): 相对简单。

你也许会想，“好，我已经有 TLS 证书了，为了网站变得安全和快速，得花些时间折腾配置信息。”

**不！**[Mozilla做了件好事情！](https://link.juejin.im/?target=https%3A%2F%2Fmozilla.github.io%2Fserver-side-tls%2Fssl-config-generator%2F)。你可以根据网站的目标受众，使用配置生成器生成[推荐套件](https://link.juejin.im/?target=https%3A%2F%2Fwiki.mozilla.org%2FSecurity%2FServer_Side_TLS)。

如果你希望网站安全，HTTPS ( HTTP over TLS ) 是[绝对不能妥协](https://link.juejin.im/?target=https%3A%2F%2Fstackoverflow.com%2Fa%2F2336738%2F2224584)的。使用 HTTPS 立刻就能消除多种攻击（中间人攻击、窃听、重放攻击以及若干允许用户模仿的会话形式的攻击）。

### 安全头

在服务器使用 HTTPS 确实为用户提供了许多安全性和性能方面的好处，但也还能通过利用某些浏览器的安全功能来进一步提升安全性。而这大部分会涉及到响应内容的安全头。

- ```http
  Content-Security-Policy
  ```

  - 你需要该 Header ，因为它提供了对于浏览器是否允许加载内部和外部资源的细化控制，从而为跨域脚本攻击漏洞提供了有效防御层。
  - 参阅 [CSP-Builder](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fcsp-builder)，以便快速简便地部署/管理内容安全策略（Content Security Policies）。
  - 为了更加深入的分析， Scott Helme's [introduction to Content-Security-Policy headers](https://link.juejin.im/?target=https%3A%2F%2Fscotthelme.co.uk%2Fcontent-security-policy-an-introduction%2F)，会是一个很好的引导。

- ```http
  Expect-CT
  ```

  - 你需要该 Header ，因为它能通过强制某些不良行为者将其错误证书的证据颁发到可公开验证的仅可追加的数据结构，从而针对流氓/受损的证书颁发机构增加一层防护。
  - 优先设置为`enforce,max-age=30`。只要你有足够的自信该 Header 不会造成服务中断，增加`max-age`吧。

- ```http
  Referrer-Policy
  ```

  - 你需要该 Header ，因为它允许你控制用户的行为信息是否泄露给第三方。
  - 同样地，Scott Helme 提供了[一篇关于Referrer-Policy Header 介绍好文](https://link.juejin.im/?target=https%3A%2F%2Fscotthelme.co.uk%2Fa-new-security-header-referrer-policy%2F)。
  - 除非有理由允许更加宽松的设置，否则请设置为`same-origin`或`no-referrer`。

- ```http
  Strict-Transport-Security
  ```

  - 你需要该 Header ，因为它告诉浏览器通过 HTTPS 而不是不安全的 HTTP ，将 future requests 设为同源。
  - 在第一次部署时，将其设置为`max-age = 30`，然后当你确信没有任何内容会中断时，将此值增加到某个较大的值（例如 31536000）。

- ```http
  X-Content-Type-Options
  ```

  - 你需要该 Header ，因为 MIME 类型的混淆可能会导致不可预知的结果，包括奇怪的允许 XSS 漏洞的边缘情况。这最好伴随着一个标准的 Content-Type Header 。
  - 除非需要默认的行为（例如文件的下载），否则请设置为`nosniff`。

- ```http
  X-Frame-Options
  ```

  - 你需要该 Header ，因为它允许你防止点击劫持。
  - 设置为`DENY` (或者`SAMEORIGIN`, 但仅仅当你使用`<frame>`元素的时候)。

- ```http
  X-XSS-Protection
  ```

  - 你需要该 Header ，因为它启用了一些默认情况下未启用的浏览器反 XSS 功能。
  - 设置为`1; mode=block`。

同样，如果你使用 PHP 的内置会话管理功能（建议使用），则可能需要这样调用`session_start()`：

```Php
<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true
]);
```

这会强制你的应用在发送会话标识符时使用 HTTP-Only 和 Secure 标志，从而防止 XSS 攻击窃取用户的 Cookie ，并强制它们分别通过 HTTPS 发送。 我们之前在 2015 年的博客文章中介绍了[安全的 PHP 会话](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F04%2Ffast-track-safe-and-secure-php-sessions)。

### 子资源完整性

在将来的某个时候，你也许会使用 CDN 来加载网站的公共 JavaScript/CSS 库。安全工程师已经遇见了这存在一个明显的风险，如果很多网站使用 CDN 提供内容，Hack 和替换 CDN（获得了 CDN 的控制权）就可以注入（恶意）代码到成千上万的网站。

查阅[子资源完整性](https://link.juejin.im/?target=https%3A%2F%2Fdeveloper.mozilla.org%2Fzh-CN%2Fdocs%2FWeb%2FSecurity%2F%25E5%25AD%2590%25E8%25B5%2584%25E6%25BA%2590%25E5%25AE%258C%25E6%2595%25B4%25E6%2580%25A7)吧。

子资源完整性（SRI，Subresource integrity）允许你将希望 CDN 服务的文件的内容进行哈希处理。目前实行的 SRI 只允许使用安全的密码散列函数，这意味着攻击者不可能生成与原始文件哈希相同的恶意版本资源。

一个真实例子: [Bootstrap v4-alpha uses SRI in their CDN example snippet](https://link.juejin.im/?target=https%3A%2F%2Fv4-alpha.getbootstrap.com%2F)

```html
<link
    rel="stylesheet"
    href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css"
    integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ"
    crossorigin="anonymous"
/>
<script
    src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"
    integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn"
    crossorigin="anonymous"
></script>
```

### 文档关系

Web 开发人员经常在超链接上设置目标属性（例如，`target ="_ blank"`在新窗口中打开链接）。但是，如果你没有传递`rel ="noopener"`标签，则可以[允许目标页面控制当前页面](https://link.juejin.im/?target=https%3A%2F%2Fmathiasbynens.github.io%2Frel-noopener%2F)。

不要这样做：

```HTML
<a href="http://example.com" target="_blank">Click here</a>
```

这会让`http://example.com`页面能控制当前页面。

而应该这样做：

```HTML
<a href="https://example.com" target="_blank" rel="noopener noreferrer">Click here</a>
```

通过这样在新窗口打开`https://example.com`，当前窗口的控制权也不会授予可能的恶意第三方。

可以更加[深入研究](https://link.juejin.im/?target=https%3A%2F%2Fwww.jitbit.com%2Falexblog%2F256-targetblank---the-most-underestimated-vulnerability-ever)。

## 开发安全的 PHP 程序

如果应用程序安全性对你来说是一个新话题，请从[应用程序安全性简介](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F08%2Fgentle-introduction-application-security)开始吧。

大多数安全专家指出，开发者可以使用 [OWASP Top 10](https://link.juejin.im/?target=https%3A%2F%2Fwww.owasp.org%2Findex.php%2FTop_10_2017-Top_10) 等资源开始着手。

但是，大多数常见的漏洞也可以是相同高等级的安全问题（例如代码和数据没有完全分离、逻辑不严谨和健全、操作环境不安全或是可破译的密码协议等）。

我们的假设是，应该授予安全新手知道一些更简单、基础的安全知识和问题，并如何解决这些问题，应该是一个更好的、长远的安全工程。

因此，[我们避免推荐十大或二十大安全清单](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F04%2Fchecklist-driven-security-considered-harmful)。

### 数据库注入

> [避免 PHP 程序存在 SQL 注入。](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F05%2Fpreventing-sql-injection-in-php-applications-easy-and-definitive-guide)

如果你是自己编写 SQL 代码，请确保使用`prepared`语句，并且从网络或文件系统提供的信息都作为参数传递，而不是字符串拼接的形式。此外，确保你[没有使用模拟的prepared语句](https://link.juejin.im/?target=https%3A%2F%2Fstackoverflow.com%2Fa%2F12202218)。

为了达到好的效果，可以使用 [EasyDB](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Feasydb) 。

不要这样做：

```php
<?php
/* Insecure code: */
$query = $pdo->query("SELECT * FROM users WHERE username = '" . $_GET['username'] . "'");
```

应该这样做：

```php
<?php
/* Secure against SQL injection: */
$results = $easydb->row("SELECT * FROM users WHERE username = ?", $_GET['username']);
```

还有其他数据库抽象层提供了相同的安全性（EasyDB实际上是在使用 PDO ，但在实际的`prepare`语句前避免了`prepared`语句模拟）。 只要用户输入不会影响查询的结构，就很安全（包括存储过程）。

### 文件上传

> 深入：[如何安全地允许用户上传文件？](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F10%2Fhow-securely-allow-users-upload-files)

接受文件上传是一个冒险的提议，但只要采取一些基本的预防措施，是能保证安全的。也就是说，允许文件直接上传的话，这些文件可能会被意外的允许执行或解释。上传的文件应该是只读（read-only）或读写（read-write）的，永远不应该可执行（executable）。

如果你的网站根目录是`/var/www/example.com`，请不要保存上传文件在`/var/www/example.com/uploaded_files`。

而应该保存到一个不能直接访问的目录（例如：`/var/www/example.com-uploaded/`），以免意外地将其作为服务器端脚本执行，并获得执行远程代码的后门。

一个更加简洁的方法是将网站根目录往下移动一个层级（即：`/var/www/example.com/public`）。

如何安全地下载这些上传文件也是一个问题。

- 直接访问 SVG 图像类型时，将在用户浏览器执行 JavaScript 代码。尽管[它的MIME类型中的`image/`前缀具有误导性](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fw3c%2Fsvgwg%2Fissues%2F266)，但是这是正确的。
- 正如前面提及的，MIME 类型嗅探可能导致类型混淆攻击。请参阅[X-Content-Type-Options](https://juejin.im/post/5a4115cc518825256362e934?utm_source=coffeephp.com&from=singlemessage&isappinstalled=1#%E5%AE%89%E5%85%A8%E5%A4%B4)。
- 如果你放弃前面关于如何安全地存储上传文件的建议，攻击者就会通过上传 .php 或 .phtml 文件，直接在浏览器中访问文件来执行任意代码，从而完全控制服务器。

### 跨站脚本

> [关于 PHP 中的跨站脚本攻击，你想知道的都在这里](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F06%2Fpreventing-xss-vulnerabilities-in-php-everything-you-need-know)

同样地，预防 XSS 和 SQL 注入是一样简单的。我们有简单而易用的 API 来分离文档结构（structure of a document）和填充的数据。

然而，实际上还有很多 Web 开发程序员仍是通过生成一大串 HTML 代码作为响应的形式开发。并且，这不是 PHP 独有的现实，这是所有 Web 开发程序员都应该重视的。

减少 XSS 漏洞不失为一个好方法。总之，前面谈及的[浏览器安全的章节](https://juejin.im/post/5a4115cc518825256362e934?utm_source=coffeephp.com&from=singlemessage&isappinstalled=1#%E6%B5%8F%E8%A7%88%E5%99%A8%E5%AE%89%E5%85%A8)就显得十分相关了。简言之：

- 尽量避免输出和输入（`Always escape on output, never on input`）。如果你把已清洗的数据（sanitized data）保存在数据库，然后在其它地方被发现了 SQL 注入漏洞，攻击者将通过恶意程序污染这些受信任的已清洗数据（trusted-to-be-sanitized record），从而绕开 XSS 保护。
- 如果你的框架有一个提供自动上下文过滤的模板引擎，那就使用它吧。这些工作可由框架安全地做到。
- `echo htmlentities（$ string，ENT_QUOTES | ENT_HTML5，'UTF-8'）` 是一种安全、有效的方法阻止UTF-8编码的网页上的所有 XSS 攻击，但不是任何 HTML 都有效。
- 如果你的环境要求你使用 Markdown 而不是 HTML ，那就不要使用 HTML 了。
- 如果你需要使用原生 HTML（没有使用模板引擎），参阅第一点，并且使用 [HTML Purifier](https://link.juejin.im/?target=http%3A%2F%2Fhtmlpurifier.org%2F) 吧。HTML Purifier 不适合转义为 HTML 属性上下文（HTML attribute context）。

### 跨站请求伪造

跨站请求伪造（CSRF）是一种混淆的代理攻击，通过诱导用户的浏览器代表攻击者执行恶意的 HTTP 请求（使用的是该用户的权限）。

这在一般情况下是很容易解决的，只需两步：

- 使用 HTTPS 。这是先决条件。没有 HTTPS 的话，任何保护措施都是脆弱的，虽然 HTTPS 本身并不防御 CSRF 。
- 增加基本的 Challenge-response authentication。
  - 为每个表单添加一个隐藏的表单属性。
  - 填充一个密码安全的随机值（称为令牌）。
  - 验证是否提供了隐藏的表单属性，以及是否匹配上期望值。

我们写了一个名为 [Anti-CSRF](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fanti-csrf) 的库，并且：

- 你可以使每个令牌只能使用一次，以防止重放攻击。
  - 多个令牌存储在后端。
  - 一旦令牌获取完，令牌会循环使用。
- 每个令牌可以绑定特定的 URL 。
  - 如果某个令牌泄露了，它不能在不同的上下文使用。
- 令牌可以绑定特定的 IP 地址。
- v2.1 后，令牌可以重复使用（例如供 Ajax 使用）。

如果你没有使用防止 CSRF 漏洞的框架，请将 Anti-CSRF 放在一边。在不久的将来，[SameSite cookies将允许我们更简单地避免CSRF攻击](https://link.juejin.im/?target=https%3A%2F%2Fwww.sjoerdlangkemper.nl%2F2016%2F04%2F14%2Fpreventing-csrf-with-samesite-cookie-attribute%2F)。

### XML 攻击 (XXE, XPath Injection)

在处理大量 XML 的应用程序中存在两个主要的漏洞：

- XML External Entities (XXE)
- XPath 注入

[除此之外](https://link.juejin.im/?target=https%3A%2F%2Fwww.owasp.org%2Findex.php%2FXML_External_Entity_(XXE)_Processing)， XXE 攻击可用作包含攻击代码的本地/远程文件的启动器。

早期版本的 Google Docs 被着名于 XXE ，但除了在很大程度上使用 XML 的商业应用程序之外，基本闻所未闻。

针对 XXE 袭击的主要缓解措施:

```php
<?php
libxml_disable_entity_loader(true);
```

除 XML 文档外，[XPath注入](https://link.juejin.im/?target=https%3A%2F%2Fwww.owasp.org%2Findex.php%2FXPATH_Injection)与 SQL 注入非常相似。

幸运的是，将用户输入传递给 XPath 查询的情况在 PHP 生态中非常罕见。

而不幸的是，这也意味着 PHP 生态中不存在可用的最佳避免措施（预编译和参数化 XPath 查询）。最好的办法是在任何涉及 XPath 查询的数据上设置允许使用的字符白名单。

```php
<?php
declare(strict_types=1);

class SafeXPathEscaper
{
    /**
     * @param string $input
     * @return string
     */
    public static function allowAlphaNumeric(string $input): string
    {
        return \preg_replace('#[^A-Za-z0-9]#', '', $input);
    }
    
    /**
     * @param string $input
     * @return string
     */
    public static function allowNumeric(string $input): string
    {
        return \preg_replace('#[^0-9]#', '', $input);
    }
}

// Usage:
$selected = $xml->xpath(
    "/user/username/" . SafeXPathEscaper::allowAlphaNumeric(
        $_GET['username']
    )
);
```

白名单总会比黑名单更安全。

### 反序列化和 PHP 对象注入

> 深入： [在PHP中安全地实现（反）序列化](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2016%2F04%2Fsecurely-implementing-de-serialization-in-php)

如果你将不可信的数据传递给`unserialize()`，则通常是这两个结果之一：

- PHP 对象注入，它能用于启动 POP 链（POP chain）并触发其他误用对象的漏洞。
- PHP 解释器本身的内存损坏。

大多数开发人员更喜欢使用JSON序列化，这是对其软件安全状况的显著改进。但请记住，[`json_decode()`容易受到散列冲突拒绝服务（Hash-DoS）攻击](https://link.juejin.im/?target=http%3A%2F%2Flukasmartinelli.ch%2Fweb%2F2014%2F11%2F17%2Fphp-dos-attack-revisited.html)。不幸的是，[PHP的Hash-DOS问题还没有得到彻底解决](https://link.juejin.im/?target=https%3A%2F%2Fbugs.php.net%2Fbug.php%3Fid%3D70644)。

从`djb33`迁移到`Siphash`，对于字符串输入，哈希输出的最高位设置为 1 ，对于整数输入设置为 0 ，使用`CSPRNG`提供的请求密钥，将完全解决这些攻击。

不幸的是， PHP 团队还没有准备好放弃他们已经在 PHP 7 系列中取得的性能提升，所以很难说服他们放弃 djb33 （这是非常快但不安全的） 赞成 SipHash （这也是快速的，但不像 djb33 那么快，但更安全）。 如果性能受到重大影响，可能会阻碍未来版本的采用，但也影响了安全性。

因此，最好的办法是：

- 使用`JSON`，因为它比`unserialize()`更安全。

- 在任何可能的地方，确保输入在反序列化之前被认证。

  - 对于提供给用户的数据，通过一个只有服务器知道的秘钥使用`sodium_crypto_auth()`和`sodium_crypto_auth_verify()`验证。

  - 对于第三方提供的数据，让他们使用

    ```php
    sodium_crypto_sign()
    ```

    签名他们的 JSON 消息，然后使用

    ```php
    sodium_crypto_sign_open()
    ```

    和第三方公钥验证消息。

    - 如果你需要对传输的签名进行十六进制或 Base64 位编码，也可以使用分离的签名 API 。

- 如果你无法验证 JSON 字符串，请严格限制速度并阻止 IP 地址，以减轻重复的违规者。

### 密码散列

> 深入：[2016 年，如何安全地保存用户密码](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2016%2F02%2Fhow-safely-store-password-in-2016)

安全的密码存储曾经是一个激烈争论的话题，但现在实现起来相当微不足道，特别是在 PHP 中：

```php
<?php
$hash = \password_hash($password, PASSWORD_DEFAULT);

if (\password_verify($password, $hash)) {
    // Authenticated.
    if (\password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        // Rehash, update database.
    }
}
```

你甚至不需要知道在后台使用什么算法，因为如果你使用最新版本的 PHP ，你也将使用当前最新的技术，用户的密码将会自动进行升级（只要有新的默认算法可用）。

无论你做什么，都[不要做 WordPress 所做的事情](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2016%2F08%2Fon-insecurity-popular-open-source-php-cms-platforms%23wordpress-password-storage)。

从 PHP 5.5 到 7.2 ，默认算法都是 Bcrypt 。在未来，它可能会切换到获得[密码哈希大赛冠军](https://link.juejin.im/?target=https%3A%2F%2Fpassword-hashing.net%2F)的 Argon2 。

如果你以前没有使用`password_*` API ，那需要迁移遗留哈希，请确保[以这种方式进行](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2016%2F02%2Fhow-safely-store-password-in-2016%23legacy-hashes)。很多公司搞错了， 最有名的是[雅虎](https://link.juejin.im/?target=https%3A%2F%2Fwww.theregister.co.uk%2F2016%2F12%2F15%2Fyahoos_password_hash%2F)。 最近，错误地实施传统哈希升级似乎导致了[苹果的iamroot错误](https://link.juejin.im/?target=https%3A%2F%2Fobjective-see.com%2Fblog%2Fblog_0x24.html)。

### 通用加密

这是一些我们详细写了的话题：

- [Using Encryption and Authentication Correctly](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F05%2Fusing-encryption-and-authentication-correctly) (2015)
- Recommended: [Choosing the Right Cryptography Library for your PHP Project: A Guide](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F11%2Fchoosing-right-cryptography-library-for-your-php-project-guide) (2015)
- Recommended: [You Wouldn't Base64 a Password - Cryptography Decoded](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F08%2Fyou-wouldnt-base64-a-password-cryptography-decoded) (2015)
- [Cryptographically Secure PHP Development](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F02%2Fcryptographically-secure-php-development) (2017)
- Recommended: [Libsodium Quick Reference: Similarly-Named Functions and Their Use-Cases](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F06%2Flibsodium-quick-reference-quick-comparison-similar-functions-and-which-one-use) (2017)

一般来说，你总是希望使用 Sodium cryptography library（libsodium）进行应用层加密。如果你需要支持早于 7.2 的 PHP 版本（像 5.2.4），你可以使用[sodium_compat](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fsodium_compat)，基本上可以假设你的用户也是 7.2 。

在特定情况下，由于严格的算法选择和互操作性，你可能需要不同的库。如有疑问，请咨询密码专家和密码工程师，了解密码选择是否安全（[这是我们提供的服务之一](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fservices)）。

### 随机性

> 深入：[如何在 PHP 中生成安全的整数和字符串？](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F07%2Fhow-safely-generate-random-strings-and-integers-in-php)

如果你需要随机数字，请使用`random_int()`。如果你需要随机字节字符串，请使用`random_bytes()`。不要使用`mt_rand()`，`rand()`或`uniqid()`。

如果你需要从秘密种子（secret seed）生成伪随机数（pseudorandom），请使用[SeedSpring](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fseedspring)，而不是`srand()`或`mt_srand()`。

```php
<?php
use ParagonIE\SeedSpring\SeedSpring;

$seed = random_bytes(16);
$rng = new SeedSpring($seed);

$data = $rng->getBytes(1024);
$int = $rng->getInt(1, 100);
```

### 服务器端 HTTPS 请求

> 确保 TLS 证书验证没有被禁用

随意使用你已经熟悉的任何兼容 PSR-7 的 HTTP 客户端。 我们喜欢 Guzzle ，有些人喜欢直接使用 cURL 。

无论你最终使用什么，请确保使用的确定性，以[确保始终可以拥有最新的 CACert 软件包](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F10%2Fcertainty-automated-cacert-pem-management-for-php-software)，从而允许启用最严格的 TLS 证书验证设置并保护服务器的出站 HTTPS 请求。

安装 Certainty 很简单：

```php
composer require paragonie/certainty:^1
```

使用 Certainty 也很简单：

```php
<?php
    use ParagonIE\Certainty\RemoteFetch;

    $latestCACertBundle = (new RemoteFetch())->getLatestBundle();

    # cURL users:
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CAINFO, $latestCACertBundle->getFilePath());

    # Guzzle users:
    /** @var \GuzzleHttp\Client $http */
    $repsonse = $http->get(
        'https://example.com', 
        [
            'verify' => $latestCACertBundle->getFilePath()
        ]
    );
```

这样可以保护你免受网络服务器与集成的任何第三方 API 之间的中间人攻击。

**我们真的需要 Certainty 吗？**

保护你的系统， Certainty 并不是严格的要求。缺少它并不是什么漏洞。但如果没有 Certainty ，开源软件必须猜测操作系统的 CACert 软件包的存在位置，如果猜测错误，它往往会失败并导致可用性问题。从历史上看，这激励了许多开发人员只是禁用证书验证，以便他们的代码“正常工作”，却没有意识到他们只是将应用程序变成主动攻击。 Certainty 通过将 CACert 捆绑在最新的可预测位置来消除这种激励。 Certainty 还为希望[运行自己的内部 CA ](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fcertainty%2Fblob%2Fmaster%2Fdocs%2Ffeatures%2FLocalCACertBuilder.md)为企业提供大量的工具。

**谁禁用了证书验证？**

流行的内容管理系统（WordPress，Magento 等 CMS）的插件/扩展开发者！这是我们试图在生态系统层面上解决的一个巨大的问题。 它不是孤立的任何特定的 CMS ，你会发现这些不安全的插件等都是类似的。

如果使用了类似的 CMS ，请在插件中搜索`CURLOPT_SSL_VERIFYPEER`和`CURLOPT_SSL_VERIFYHOST`，你可能会发现有几个将这些值设置为`FALSE`。

### 避免的事情

- [不要使用`mcrypt`](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F05%2Fif-you-re-typing-word-mcrypt-into-your-code-you-re-doing-it-wrong)。这是一个十多年来没有开发出来的密码学库。如果你遵循我们的 PHP 版本建议，这应该是一个容易避免的错误，因为`mcrypt`不再被 PHP 7.2 和更新的版本支持。
- [配置驱动的安全建议](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F01%2Fconfiguration-driven-php-security-advice-considered-harmful)应该大部分地忽略。如果你正在阅读 PHP 安全性指南，并告诉你更改 php.ini 设置而不是编写更好的代码，那么你可能正在阅读过时的建议。关闭窗口并转到一些和`register_globals`无关的文章上吧。
- [不要使用 JOSE（JWT，JWS，JWE）](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F03%2Fjwt-json-web-tokens-is-bad-standard-that-everyone-should-avoid)，这是一套互联网标准，它编纂了一系列容易出错的密码设计。尽管由于某种原因，被写入了标准，也吸引了很多传道人。
- [加密 URL 参数](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2015%2F09%2Fcomprehensive-guide-url-parameter-encryption-in-php)是公司常用来模糊元数据的反模式（例如，我们有多少用户？）。 它带来了实施错误的高风险，也造成了错误的安全感。我们在链接的文章中提出了一个更安全的选择。
- 除非迫不得已，否则[不要提供“我忘记了我的密码”的功能](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2016%2F09%2Funtangling-forget-me-knot-secure-account-recovery-made-simple)。 不要讳言：密码重置功能是一个后门。 有一些方法可以实施以抵御合理的威胁模型，但高风险用户应该不被考虑。
- [避免使用 RSA](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2016%2F12%2Feverything-you-know-about-public-key-encryption-in-php-is-wrong)，改用 libsodium 。如果你必须使用 RSA ，请确保指定 OAEP 填充。

```php
 <?php

 openssl_private_decrypt(
    $ciphertext,
    $decrypted, // Plaintext gets written to this variable upon success,
    $privateKey,
    OPENSSL_PKCS1_OAEP_PADDING // Important: DO NOT OMIT THIS!
);
```

如果你不得不使用 PKCS＃1 v1.5 填充，那么无论你与哪个集成在一起，几乎肯定会受到 [ROBOT](https://link.juejin.im/?target=https%3A%2F%2Frobotattack.org%2F) 的影响，请以允许明文泄露和签名伪造的漏洞将其报告给相应的供应商（或 US-CERT ）。

## 专业用法

现在你已经掌握了在 2018 年及以后构建安全 PHP 应用程序的基础知识，接下来我们来看一些更专业的用法。

### 可搜索的加密

> 深入：[使用PHP和SQL构建可搜索的加密数据库](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F05%2Fbuilding-searchable-encrypted-databases-with-php-and-sql)

可搜索的加密数据库是可取的，但被广泛认为是不太可能实现的。上面链接的博客文章试图通过改进我们解决方案来实现，但本质上是这样的：

- 设计你的架构，以便数据库（database compromise）不会让攻击者访问你的加密密钥。
- 用一个密钥加密数据。
- 基于 HMAC 或具有静态盐的安全 KDF （secure KDF with a static salt）创建多个索引（具有自己独特的密钥）
- 可选：截断步骤3的输出，将其用作布隆过滤器（Bloom filter）
- 在 SELECT 查询中使用步骤3或4的输出
- 解密结果。

在这个过程中的任何一步，你都可以根据实际使用情况进行不同的权衡。

### 没有 Side-Channels 的基于令牌的身份验证

> 深入： [Split Tokens: Token-Based Authentication Protocols without Side-Channels](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F02%2Fsplit-tokens-token-based-authentication-protocols-without-side-channels)

说到数据库（上一节），你是否知道 SELECT 查询理论上可能是定时信息泄漏的来源？

简单的缓解措施：

- 把你的认证令牌分为两半
- 一半在 SELECT 查询中使用
- 后一半在恒定的时间（constant-time）验证
  - 可以选择将后半部分的散列存储在数据库中。这对于只能使用一次的令牌是有意义的，例如 密码重置或“在此计算机上记住我”的令牌

即使可以使用定时泄漏来窃取一半的令牌，剩下的也需要暴力破解才能成功。

### 开发安全的API

> 深入： [Hardening Your PHP-Powered APIs with Sapient](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F06%2Fhardening-your-php-powered-apis-with-sapient)

我们写了 [SAPIENT](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fsapient) （the **S**ecure **API** **EN**gineering **T**oolkit），让服务器到服务器验证的消息传递变得简单易行。除了 HTTPS 提供的安全性之外，`Sapient`允许你使用共享密钥或公钥来加密和验证消息。 这使得即使存在中间攻击者，并设有流氓证书颁发机构，你也可以使用`Ed25519`对 API 请求和响应进行身份验证，或者将消息加密到只能由接收方服务器的密钥解密的目标服务器。 由于每个 HTTP 消息体都通过安全密码进行身份验证，所以可以安全地使用它来代替`stateful token juggling protocols`（例如 OAuth）。但是，在密码学方面，在做任何不规范的事情之前，总要确保他们的实现是由专家研究的。

所有`Sapient`使用的密码算法都由`Sodium cryptography library`提供。

进一步阅读：

- [Sapient Documentation](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fsapient%2Ftree%2Fmaster%2Fdocs)
- [Sapient Tutorial](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fsapient%2Fblob%2Fmaster%2Fdocs%2FTutorial.md)
- [Sapient Specification](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fsapient%2Fblob%2Fmaster%2Fdocs%2FSpecification.md)

`Paragon Initiative Enterprises`已经在其许多产品（包括许多开源软件项目）中使用了`Sapient`， 并将继续添加软件项目到`Sapient`用户群中。

### 使用Chronicle记录安全事件

> 深入： [Chronicle Will Make You Question the Need for Blockchain Technology](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F07%2Fchronicle-will-make-you-question-need-for-blockchain-technology)

[Chronicle](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fchronicle)是一个基于散列链数据结构的仅追加密码分类账（append-only cryptographic ledger），具有很多吸引公司“区块链”技术的属性，而不会过分矫枉过正。

除了仅追加密码分类账（append-only cryptographic ledger）这个具有创造性的用例之外，`Chronicle`集成到SIEM中时，也可以十分有亮点，因为你可以将安全关键事件发送到私人`Chronicle`中，并且它们是不能被改变的。

如果你的`Chronicle`设置为将其摘要散列交叉签名到其他`Chronicle`实例，或者如果有其他实例配置为复制你的`Chronicle`内容，攻击者就很难篡改你的安全事件日志。

在`Chronicle`的帮助下，你可以获得区块链所承诺的弹性特性（resilience），而没有任何隐私，性能或可伸缩性问题。

要将数据发布到本地`Chronicle`，你可以使用任何与[Sapient-compatible API](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F12%2F2018-guide-building-secure-php-software%23secure-api-sapient)，但最简单的解决方案称为[Quill](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fparagonie%2Fquill)。

## 作者的一些话

一些聪明的读者可能注意到我们引用了很多我们自己的工作，包括博客文章和开源软件。（当然也不仅仅引用了我们自己的工作）

这绝不是偶然的。

自从我们在 2015 年初成立以来，一直在编写安全库并参与提高 PHP 生态系统安全性的工作。我们已经涉足了很多领域，而且我们的安全工程师（他们最近推动了更安全的加密技术加入 PHP 核心，就在最近的 PHP 7.2 中）自我担保地说，并不擅长自我炒作，或是对已经做过的工作持续热情。但你很可能没有听说我们多年来开发的工具或库。对于这个，深感抱歉。

不论如何，我们也不可能成为各方面的先行者，所以我们尽可能地选择与重视公共利益而不是贪图小利的行业专家工作。 这也是为什么浏览器安全的许多章节都参考了 [Scott Helme](https://link.juejin.im/?target=https%3A%2F%2Fscotthelme.co.uk%2F) 和他公司的工作，他们在为开发人员提供这些新的安全功能方面具有可访问性和可理解性。

本指南当然不会是详尽的。编写不安全代码的方法几乎和编写代码的方法一样多。 **安全是一种心态，而不是目的地。** 随着上面所写的一切，以及后面涉及的资源，我们希望这将有助于全世界的开发人员，从今天开始用 PHP 编写安全的软件。

## 资源

如果你已经按照本页上的所有内容进行了操作，并且需要更多内容，则可能会对我们策划的阅读列表感兴趣，以便学习应用程序安全性。

如果你认为自己编写的代码足够安全，并希望我们从安全工程师的角度对其进行评判，这也是我们为客户提供的服务。

你如果为一家要进行合规性测试（PCI-DSS，ISO 27001等）的公司工作，可能还想聘请我们公司来审核你的源代码。我们的流程比其他安全咨询公司更适合开发者。

接下来是 PHP 和信息安全社区提供的资源列表，这些资源帮助互联网更加安全。

- [PHP: The Right Way](https://link.juejin.im/?target=http%3A%2F%2Fwww.phptherightway.com%2F)：现代 PHP 开发的实用指南，免费在线。
- [Mozilla's SSL Config Generator](https://link.juejin.im/?target=https%3A%2F%2Fmozilla.github.io%2Fserver-side-tls%2Fssl-config-generator%2F)
- [Let's Encrypt](https://link.juejin.im/?target=https%3A%2F%2Fletsencrypt.org%2F)：证书颁发机构，通过提供免费 TLS 证书，为创建更安全的 Internet 做了很多。
- [Qualys SSL Labs](https://link.juejin.im/?target=https%3A%2F%2Fwww.ssllabs.com%2Fssltest)：为 TLS 配置提供了一个快速而简单的测试套件。几乎每个人都使用这个来解决他们的密码组和证书问题，理由很充分：**It does its job well.**
- [Security Headers](https://link.juejin.im/?target=https%3A%2F%2Fsecurityheaders.io%2F)：可以检验你的网站在使用浏览器安全功能来保护用户方面的表现如何。
- [Report-URI](https://link.juejin.im/?target=https%3A%2F%2Freport-uri.com%2F)：一个很好的免费资源，提供监控 CSP/HPKP 等安全策略的实时安全报告服务。他们给你一个 Report-URI，你可以传递给你的用户的浏览器，如果有什么事情发生或有人发现 XSS 攻击媒介，他们会投诉Report-URI。 Report-URI 会汇总这些错误，并允许你更好地对这些报告进行疑难解答和分类。
- [PHP Security Advent Calenda](https://link.juejin.im/?target=https%3A%2F%2Fwww.ripstech.com%2Fphp-security-calendar-2017)：[RIPSTech](https://link.juejin.im/?target=https%3A%2F%2Fwww.ripstech.com%2F)旗下的团队负责。
- [Snuffleupagus](https://link.juejin.im/?target=https%3A%2F%2Fsnuffleupagus.readthedocs.io%2F)：一个面向安全的 PHP 模块（[Suhosin](https://link.juejin.im/?target=https%3A%2F%2Fgithub.com%2Fsektioneins%2Fsuhosin)的精神继承者，似乎在很大程度上会被放弃）
- [PHP Delusions](https://link.juejin.im/?target=https%3A%2F%2Fphpdelusions.net%2F)：一个致力于更好地使用 PHP 的网站。大部分的口吻是非常有见地的，作者对技术的准确性和清晰度的奉献使得值得一读，特别是对于那些不太喜欢 PDO 功能的人来说。
- [Have I Been Pwned?](https://link.juejin.im/?target=https%3A%2F%2Fhaveibeenpwned.com%2F)：帮助用户发现他们的数据是否属于过时数据泄露。

## 结尾

原文地址：[The 2018 Guide to Building Secure PHP Software - P.I.E. Staff](https://link.juejin.im/?target=https%3A%2F%2Fparagonie.com%2Fblog%2F2017%2F12%2F2018-guide-building-secure-php-software)

最早是在Laravel China社区里帖子 - [The 2018 Guide to Building Secure PHP Software](https://link.juejin.im/?target=https%3A%2F%2Flaravel-china.org%2Ftopics%2F7080%2Fthe-2018-guide-to-building-secure-php-software)看到，一位同学只发了原链接，由于是全英，文章也比较长，就没有深读，但可以知道这是一篇很好的文章，值得学习，这几天花了时间翻译了全文。

为避免歧义，部分专业名词和语句保留了原文。翻译过程借助了 Google 和 Google 翻译，本人英文和相关专业水平有限，如有错误感谢指出修正。



> 翻译版权所有: 癞蛤蟆想吃炖大鹅

>  版权声明：自由转载-非商用-非衍生-保持署名（[创意共享3.0许可证](https://creativecommons.org/licenses/by-nc-nd/3.0/deed.zh)）