---
title: "[译] PHPDoc类型提示数组的对象"
date: 2018-01-28 21:28:29
tags: [php,phpstorm,ide]
---

## 问题描述

因此，在PHPDoc中，可以在成员变量声明之上指定`@var`来提示其类型。然后一个IDE，例如PHPEd将知道它正在使用什么类型的对象，并且能够为该变量提供代码洞察。

```php
<?php
 class Test
 {
 /** @var SomeObj */
 private $someObjInstance;
 }
```

这样做很好，直到我需要对一组对象做同样的事情，以便在以后迭代这些对象时才能获得正确的提示。

那么，有没有办法声明一个PHPDoc标签来指定成员变量是`SomeObj`的数组？ `@var`数组是不够的，例如`@var array(SomeObj)`似乎没有效果。

## 最佳解决方案
在JetBrains的PhpStorm IDE中，您可以使用`/** @var SomeObj[] */`，例如：

```php
/**
 * @return SomeObj[]
 */
function getSomeObjects() {...}
```

[phpdoc documentation](https://segmentfault.com/link.php?target=http%3A//phpdoc.org/docs/latest/references/phpdoc/types.html%23arrays)推荐这种方法：

> specified containing a single type, the Type definition informs the reader of the type of each array element. Only one Type is then expected as element for a given array.
>
> Example: `@return int[]`

<!--more-->

## 次佳解决方案


```php
foreach ($Objs as $Obj)
{
 /* @var $Obj Test */
 // You should be able to get hinting after the preceding line if you type $Obj->
}
```

我在Zend Studio中做了很多工作。不知道其他编辑，但它应该工作。



## 第三种解决方案

Netbeans提示：

您可以在`$users[0]->`和`$this->`上获取用户类数组的代码完成。

```php
/**
 * @var User[]
 */
var $users = array();
```

当您完成`$this->...`时，您还可以看到类成员列表中的数组类型

## 第四种方案

指定变量是一个对象数组：

```php
$needles = getAllNeedles();
/* @var $needles Needle[] */
$needles[1]->... //codehinting works
```

这在Netbeans 7.2(我正在使用它)

还有：

```php
$needles = getAllNeedles();
/* @var $needles Needle[] */
foreach ($needles as $needle) {
 $needle->... //codehinting works
}
```

因此在`foreach`中使用声明是不必要的。

## 第五种方案

我更喜欢阅读和编写干净的代码 – 如Robert C. Martin的”Clean Code”所述。当遵循他的信条时，您不应要求开发人员(作为您的API的用户)知道数组的(内部)结构。

API用户可能会问：这是仅一维的数组吗？物体是否在多维数组的各个层次上传播？我需要访问所有对象有多少个嵌套循环(foreach等)？什么类型的对象是该数组中的”stored”？

如您所概述的，您希望将该数组(其中包含对象)用作一维数组。

正如Nishi所概述的，你可以使用：

```php
/**
 * @return SomeObj[]
 */
```

为了那个原因。

但再次：请注意 – 这不是一个标准的docblock符号。这种符号是由一些IDE生产者引入的。

好的，作为一名开发人员，您知道”[]”与PHP中的数组绑定。但是在正常的PHP上下文中”something[]”是什么意思？ “[]”意味着：在”something”中创建新元素。新的元素可以是一切。但是你想表达的是：具有相同类型的对象的数组，它的确切类型。您可以看到，IDE生产者引入了一个新的上下文。你必须学习的一个新的背景。 PHP开发人员必须学习的新环境(了解您的docblocks)。坏风格(！)。

因为你的数组确实有一个维度，你可能想要将这个“数组的对象”称为”list”。请注意，”list”在其他编程语言中具有非常特殊的意义。它会被更好地称为”collection”为例。

记住：您使用一种编程语言，可以为您提供OOP的所有选项。使用类而不是数组，并使类像数组一样遍历。例如。：

```php
class orderCollection implements ArrayIterator
```

或者如果要将内部对象存储在多维数组/对象结构中的不同级别上：

```php
class orderCollection implements RecursiveArrayIterator
```

此解决方案将使用”orderCollection”类型的对象替换您的数组，但是到目前为止，不能在IDE中启用代码完成。好的。下一步：

实现与docblock接口引入的方法 – 特别是：

```php
/**
 * [...]
 * @return Order
 */
orderCollection::current()

/**
 * [...]
 * @return integer E.g. database identifier of the order
 */
orderCollection::key()

/**
 * [...]
 * @return Order
 */
orderCollection::offsetGet()
```

不要忘记使用类型提示：

```php
orderCollection::append(Order $order)
orderCollection::offsetSet(Order $order)
```

这个解决方案停止引入很多：

```php
/** @var $key ... */
/** @var $value ... */
```

所有的代码文件(例如循环内)，因为Zahymaka证实了他/他的答案。您的API用户不会被迫引用该文档块，以使代码完成。要只在一个地方有@return可以减少冗余(@var)作为mutch尽可能的。使用@var“docBlocks”会使您的代码变得最不可读。

最后你完成了看起来很难看看起来像大锤打破一个坚果？不是真的，因为你熟悉那些接口和干净的代码。记住：你的源代码是一次写/读很多。

如果IDE的代码完成无法使用此方法，请切换到更好的(例如IntelliJ IDEA，PhpStorm，Netbeans)或在IDE生成器的问题跟踪器上提交功能请求。

感谢Christian Weiss(来自德国)担任我的教练，并教我如此伟大的东西。 PS：在邢会见我和他

## 第六种方案

[PSR-5: PHPDoc](https://segmentfault.com/link.php?target=https%3A//github.com/php-fig/fig-standards/blob/211063eed7f4d9b4514b728d7b1810d9b3379dd1/proposed/phpdoc.md%23collections)提出了一种形式的Generics-style表示法。

### Syntax

```php
Type[]
Type<Type>
Type<Type[, Type]...>
Type<Type[|Type]...>
```

集合中的值可能甚至是另一个数组，甚至另一个集合。

```php
Type<Type<Type>>
Type<Type<Type[, Type]...>>
Type<Type<Type[|Type]...>>
```

### 例子

```php
<?php

$x = [new Name()];
/* @var $x Name[] */

$y = new Collection([new Name()]);
/* @var $y Collection<Name> */

$a = new Collection(); 
$a[] = new Model_User(); 
$a->resetChanges(); 
$a[0]->name = "George"; 
$a->echoChanges();
/* @var $a Collection<Model_User> */
```

注意：如果您期望IDE执行代码辅助，那么另一个问题是IDE是否支持PHPDoc Generic-style集合符号。

从我的答案到[this question](https://segmentfault.com/link.php?target=https%3A//stackoverflow.com/a/39384337/934739)。

## 第七种方案

在NetBeans 7.0(也可能较低)中，您可以声明返回类型“具有文本对象的数组”，就像`@return Text`一样，并且代码提示将起作用：

编辑：使用@Bob Fanger建议更新示例

```php
/**
 * get all Tests
 *
 * @return Test|Array $tests
 */
public function getAllTexts(){
 return array(new Test(), new Test());
}
```

只需使用它：

```php
$tests = $controller->getAllTests();
//$tests-> //codehinting works!
//$tests[0]-> //codehinting works!

foreach($tests as $text){
 //$test-> //codehinting works!
}
```

它不是完美的，但最好只是离开它只是”mixed”，女巫没有带来价值。

CONS是你被允许以数组为背景，因为文本对象将会抛出错误。

## 第八种方案

**在Zend Studio中使用array[type]。**

在Zend Studio中，`array[MyClass]`或`array[int]`甚至`array[array[MyClass]]`都很棒。

## 第九种方案

正如DanielaWaranie在答案中提到的那样 – 当您在$ collectionObject中迭代$ items时，有一种方法来指定$ item的类型：将`@return MyEntitiesClassName`添加到`current()`以及返回值的`Iterator`和`Iterator`和`ArrayAccess`方法的其余部分。

繁荣！ `/** @var SomeObj[] $collectionObj */`不需要`foreach`，并且与收藏对象一起使用，无需以`@return SomeObj[]`描述的特定方法返回收藏。

我怀疑并不是所有的IDE都支持它，但它在PhpStorm中工作得很好，这让我更开心。

**例：**

```php
Class MyCollection implements Countable, Iterator, ArrayAccess {

 /**
 * @return User
 */
 public function current() {
 return $this->items[$this->cursor];
 }

 //... implement rest of the required `interface` methods and your custom
}
```

### 有什么有用的我会添加发布这个答案

在我的情况下，`current()`和`interface`方法的其余部分在`Abstract` -collection类中实现，我不知道最终将在集合中存储什么样的实体。

所以这里是窍门：不要在抽象类中指定返回类型，而是在特定的集合类的描述中使用PhpDoc instuction `@method`。

**例：**

```php
Class User {
 function printLogin() {
 echo $this->login;
 }
}

Abstract Class MyCollection implements Countable, Iterator, ArrayAccess {

 protected $items = [];

 public function current() {
 return $this->items[$this->cursor];
 }

 //... implement rest of the required `interface` methods and your custom
 //... abstract methods which will be shared among child-classes
}

/**
 * @method User current()
 * ...rest of methods (for ArrayAccess) if needed
 */
Class UserCollection extends MyCollection {

 function add(User $user) {
 $this->items[] = $user;
 }

 // User collection specific methods...

}
```

现在，使用类：

```php
$collection = new UserCollection();
$collection->add(new User(1));
$collection->add(new User(2));
$collection->add(new User(3));

foreach ($collection as $user) {
 // IDE should `recognize` method `printLogin()` here!
 $user->printLogin();
}
```

再次：我怀疑并不是所有的IDE都支持它，而PhpStorm则是这样。尝试你的，发表评论结果！



## 参考文献

- [PHPDoc type hinting for array of objects?](https://stackoverflow.com/questions/778564/phpdoc-type-hinting-for-array-of-objects%3Fanswertab%3Dvotes)