---
title: "漫谈PHP8新特性：命名参数"
date: 2020-07-17 18:25:20
tags: [php, php8, rfc]
---

本文是对「[命名参数RFC](https://wiki.php.net/rfc/named_params)」的个人解读，先让我们来简单看下RFC的主要内容：

命名参数特性允许基于参数名称而不是参数位置来传递参数，这使得：

1. 可以跳过默认值
2. 参数的传递可与顺序无关
3. 参数的含义可以自我记录

<!--more-->

> 其实这个特性的RFC早在2013年和2016年就有人建立过了，但一直没有通过或是实施，直到PHP8版本，该RFC终于得到机会再次启用，并且发起人是PHP内核的核心开发者Nikita Popov（nikic），nikic对此做了非常详细的调研，RFC全文字数差不多有三万字（相比于PHP的其它RFC而言已经是相当的多了），该RFC刚开始投票的时候还有一定的悬念（PHP社区的元老级人物对于新特性总是给出反对票），但很快赞同数就远超了2/3多数，目前RFC已投票通过。

## 命名参数的好处

### 允许跳过默认值

最显著的例子就是：

```php
// before
htmlspecialchars($string, ENT_COMPAT | ENT_HTML401, ini_get('default_charset'), false);
// after
htmlspecialchars($string, double_encode: false);
```

在没有命名参数特性之前，我们为了设置第四个参数`double_encode`，不得不给出第二第三个可选参数的默认值，我们可能需要查询文档或是编写冗长的代码，而有了命名参数特性之后，一切都简单了，并且哪怕某个参数的默认值发生了变化，代码也不会受到影响（虽然几乎不存在这样的情况，但某种意义上也是消除了硬编码）。

### 参数含义的自我记录及传递顺序无关性

比如对于某个我们我们不熟的函数（当然实际上来说，array系列函数都不熟的话可能连面试都通不过...）：

```php
array_fill(value: 50, start_index: 0, num: 100);
```

代码已经包含了对每个入参的意义的表达，并且传参顺序也可以任意改变。

### 更简便的API调用

但我觉得这样的全命名写法一般来说是多此一举，容易造成书写风格的割裂，并且装了插件的编辑器或是IDE都能很好地显示出参数名。

所以这个特性最大的受益者应该是可选参数特别多或设计不合理的一些API，比如又臭又长的OpenSSL的API：

```php
function openssl_encrypt(string $data, string $method, string $password, int $options = 0, string $iv = '', &$tag = UNKNOWN, string $aad = '', int $tag_length = 16): string|false {}
```

### 更快捷的对象属性的初始化

此外有所受益的是对象属性的初始化：

其实在早前就有RFC探讨了如何更好地初始化对象属性，以使对象构造更符合人体工程学。写过C++的同学肯定很快就想到了「[初始化列表](https://zh.cppreference.com/w/cpp/language/list_initialization)」，PHP也有人专门为此建立了一个RFC「[对象初始化器](https://wiki.php.net/rfc/object-initializer)」，但是显然专门为此添加一个新语法并不那么值得，以反对票一边倒的结果被拒绝了。但现在我们有了命名参数以后，这个问题自然就解决了：

> 以下展示还包含了另一个已落地的PHP8新特性，[构造函数属性升级](https://wiki.php.net/rfc/constructor_promotion)，我们可以在声明构造函数的参数的同时将其声明为对象的属性：

```php
// Part of PHP AST representation
class ParamNode extends Node {
    public function __construct(
        public string $name,
        public ExprNode $default = null,
        public TypeNode $type = null,
        public bool $byRef = false,
        public bool $variadic = false,
        Location $startLoc = null,
        Location $endLoc = null
    ) {
        parent::__construct($startLoc, $endLoc);
    }
}

new ParamNode('test', variadic: true);
```

来看看没有这两个特性之前我们需要以怎样繁琐的方式写出同等的代码吧，我保证你肯定不想按以下方式写代码，除非你已经在用某种代码生成器来帮你完成这一工作：

```php
class ParamNode extends Node
{
    public string $name;
    public ?ExprNode $default;
    public ?TypeNode $type;
    public bool $byRef;
    public bool $variadic;

    public function __construct(
        string $name,
        ExprNode $default = null,
        TypeNode $type = null,
        bool $byRef = false,
        bool $variadic = false,
        Location $startLoc = null,
        Location $endLoc = null
    ) {
        $this->name = $name;
        $this->default = $default;
        $this->type = $type;
        $this->byRef = $byRef;
        $this->variadic = $variadic;
        parent::__construct($startLoc, $endLoc);
    }
}

new ParamNode('test', null, null, false, true);
```

或者有的人会选择用「数组」这个万金油来解决：

```php
class ParamNode extends Node {
    public string $name;
    public ExprNode $default;
    public TypeNode $type;
    public bool $byRef;
    public bool $variadic;
 
    public function __construct(string $name, array $options = []) {
        $this->name = $name;
        $this->default = $options['default'] ?? null;
        $this->type = $options['type'] ?? null;
        $this->byRef = $options['byRef'] ?? false;
        $this->variadic = $options['variadic'] ?? false;
 
        parent::__construct(
            $options['startLoc'] ?? null,
            $options['endLoc'] ?? null
        );
    }
}
 
// Usage:
new ParamNode($name, ['variadic' => true]);
```

有点小机灵，但是很遗憾，它的缺点更多：

1. 无法利用类型系统在传参时自动地检测（而是由于属性类型验证失败而报错）
2. 你必须查看实现或是文档，且文档无法很好地记录它（没有公认的规范）
3. 你可以悄无声息地传递未知选项而不会得到报错，这一错误非常普遍，曾经遇到有一个开发者将配置项名打错了一个字母，导致配置无法生效，却也没有得到任何报错，为此debug了一整天
4. 没法利用新特性「构造函数属性升级」
5. 如果你想将现有API切换到数组方式，你不得不破坏API兼容性，但命名参数不需要

nikic非常自信地认为，相比而言，**命名参数提供了同等便利，但没有任何缺点**。

此外，RFC还简单延伸了一个备选方案，探讨如何解决历史代码中使用数组的缺陷：

```php
class ParamNode extends Node {
    public string $name;
    public ExprNode $default;
    public TypeNode $type;
    public bool $byRef;
    public bool $variadic;

    public function __construct(
        string $name,
        array [
            'default' => ExprNode $default = null,
            'type' => TypeNode $type = null,
            'byRef' => bool $type = false,
            'variadic' => bool $variadic = false,
            'startLoc' => Location $startLoc = null,
            'endLoc' => Location $endLoc = null,
        ],
    ) {
        $this->name = $name;
        $this->default = $default;
        $this->type = $type;
        $this->byRef = $byRef;
        $this->variadic = $variadic;
        parent::__construct($startLoc, $endLoc);
    }
}
```

虽然解决了类型安全问题，但无法解决默默接受未知选项的问题，并且还有很多需要考虑的难题，但不值得继续展开讨论。

### 更好的注解兼容性

千呼万唤始出来，PHP8终于有了官方支持的注解特性，对于有些人来说这是比JIT还要让人激动的事情（因为对于他们来说JIT性能提升真的不是很大，PHP5到PHP7的跨越才是永远滴神），那么命名参数对注解又有什么好处呢？

曾经的路由注解可能是这样的（@Symfony Route）:

```php
/**
 * @Route("/api/posts/{id}", methods={"GET","HEAD"})
 */
public function show(int $id) { ... }
```

有了官方注解以后可能是这样的：

```php
<<Route("/api/posts/{id}", ["methods" => ["GET", "HEAD"]])>>
public function show(int $id) { ... }
```

那么势必造成API的向下不兼容，但有了命名参数以后，我们完全可以保持相同的API结构：

```php
<<Route("/api/posts/{id}", methods: ["GET", "HEAD"])>>
public function show(int $id) { ... }
```

> 由于缺乏对嵌套注释的支持，仍然需要进行一些更改，但这会使迁移更加顺畅。



## 思考

好了，看到这里很多人应该会觉得：命名参数真是个好东西！双脚赞成！
如果是，那么很巧，我也是这么想的，尤其是刚学编程，尝试用Python写一个WEB小程序的时候，我有被命名参数特性小小地惊艳到。
但是我们不得不知道的是，以上介绍「好处」的内容仅仅是RFC篇幅的小头部分，剩下的上万字内容也是大多数人所并不关心或不需要关心的实施细节。但我们必须以此思考获得的收益是否能弥补变动的成本，这也正是反对者所忧虑的部分。

我在这里简单罗列一下添加该特性需要考虑的问题们：

* 是否支持动态指定命名参数？如果是，如何支持？使用何种语法？和现有语法有何种冲突？可能影响到的未来语法？
* 约束条件：如命名参数必须在必选参数之后；不得传递相同的命名参数；不得以命名参数形式覆盖相同位置的参数；不得使用未知的命名参数
* 可变参函数和参数解压缩规则
* 受影响的API们（不完全）：`func_get_args`，`call_user_func`系列，`__invoke()`，`__call()`和`__callStatic()`等等
* 继承期间变量名的更改：是否将其视为错误？是，造成向下不兼容？否，违反里式替换原则怎么办？应遵循何种模型，其它哪些语言的实现值得参考？
* 对于内核实现的影响（太多了，不扩展）

有兴趣的同学可以自己阅读原版RFC，体会一下一个看似简单的新特性添加需要多么深入的考虑。最重要的是你还要将它们总结出来并说服绝大部分社区成员投赞成票，不同的人发起同样的主题的RFC也可能会有不同的结果。



## 命名参数的困境

### 修改参数名即是破坏向后兼容性

CS领域中头号难题：命名！

如果说命名空间、类名、函数方法名已经让我们痛苦不堪，那么现在我们获得了数倍于之前的痛苦，好好想想你的参数名吧，因为你以后不能随便改它了，并且这将是下划线派和驼峰派的又一个战争点，谁输谁赢，谁是新潮流？

> PS：PHP内核开发者们正在对成千上万个内置函数的参数命名进行梳理工作...

### 文档和实现中的参数名称不匹配

参数命名的梳理的工作量翻倍了。

### 继承的方法中不宜重命名参数名

该RFC建议遵循Python和Ruby的模型，在子方法中参数名如产生变动则默默接受，调用时使用不匹配的父方法的参数名可能会产生错误。

```php
interface I {
    public function test($foo, $bar);
}
 
class C implements I {
    public function test($a, $b) {}
}
 
$obj = new C;
 
// Pass params according to C::test() contract
$obj->test(a: "foo", b: "bar");     // Works!
// Pass params according to I::test() contract
$obj->test(foo: "foo", bar: "bar"); // Error!
```

通常来说这没什么问题，但对于某些抽象设计来说就很不好了，以下代码将无法正常运作：

```php
interface Handler {
    public function handle($message);
}
 
class RegistrationHandler implements Handler {
    public function handle($registrationCommand);
}
 
class ForgottenPasswordHandler implements Handler {
    public function handle($forgottenPasswordCommand);
}
 
class MessageBus {
    //...
    public function addHandler(string $message, Handler $handler) { //... }
    public function getHandler(string $messageType): Handler { //... }
    public function dispatch($message)
    {
      	// handler可能是RegistrationHandler或ForgottenPasswordHandler
        // 它们为了更好地表达参数的意义而改变了参数名, 但也导致了我们无法通过message这个名字来调用它了
        $this->getHandler(get_class($message))->handle(message: $message);
    }
}
```

因此已经有人提出了一个看起来更复杂的RFC：[Renamed Parameters](https://wiki.php.net/rfc/renamed_parameters)



## 未来方向

### 简写语法

我们常常会在栈上使用和参数名一样的变量名，那么我们可能可以简化这一行为：

```php
// before:
new ParamNode(name: $name, type: $type, default: $default, variadic: $variadic, byRef: $byRef);
// after:
new ParamNode(:$name, :$type, :$default, :$variadic, :$byRef);
```

也适用于数组的解构（比较实用）：

```php
// before
['x' => $x, 'y' => $y, 'z' => $z] = $point;
// after
[:$x, :$y, :$z] = $point;
```

这样我们可以废弃`compact`这种魔法一般的函数，刚学PHP的时候我好一会才理解这函数是干嘛的，作为函数，它的能力却和eval一样邪恶，这种特性应当是语法级别的。



## 结语

在我看来，这个特性的通过是必然的，这是一个迟早要实现的特性，对很多人来说更是一个姗姗来迟的特性。很多人不了解的是，PHP的RFC常常要求起草者自己想办法实现（包括找人代为实现），而不是直接进入投票环节通过后就强制要求PHP核心开发者实现（你行你上），因此有些RFC由于缺少靠谱的实施者所以就没有下文了。

PHP8这个大版本是去其糟粕、辞旧迎新的好契机，恰逢nikic这样年轻有为的改革派，一些本不可能落地的废弃项和新特性都已安全着陆（未来有空我会介绍一些PHP8中让人拍手称快的糟粕废弃项），PHP更加地「通用脚本语言」，而不再是「Personal Home Page」。

