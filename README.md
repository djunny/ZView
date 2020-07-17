# ZView
a simple php template engine, support swoole/php-fpm, high performance, easy, flexible, single file.

### Installation

```
composer require zv/zview
```



### BASE

1. <!--{code}--> 默认注释语法
2. {code} html嵌入属性语法
3. {$variable} 输出变量
4. {CONSTANT} 输出常量
5. {$array['index']} 输出数组下标
6. {function(param1, param2, param3...)} 调用原生方法

模板中动态渲染内容，基本上由以上语法组成。

- 语法1 多用于 html 块级输出，例：
```
<!--{if 1==2}--><html></html><!--{/if}-->
```

- 语法2 多用于元素属性输出，例：
```
<form {if 1==2}method="post"{/if}>
```

- 语法6 调用原生方法，输出值必须为方法结果 return

### sub template

说明：

加载一个子模板。(子模板最多支持三层嵌套)

语法：

```
<!--{template header.htm}-->
<!-- 默认.htm 后缀-->
<!--{template header}-->
<!-- 支持目录-->
<!--{template global/header}-->
```

### logic if/else

```
<!--{if $a == $b && $b == 1}-->
    a = b AND b = $b
<!--{elseif $b == 2 || $a == 3}-->
    b = $b OR a = $a
<!--{elseif $a == 1}-->
    a = 1
<!--{else}-->
    a != 1
<!--{/if}-->
```


### loop 

循环输出列表，可嵌套输出

```
<!--{loop $categories $index $cates}-->
    <!--{loop $cates $sub_index $cate}-->
        categories[$index][$sub_index]= {$cate['name']}
    <!--{/loop}-->
<!--{/loop}-->
```

### eval 


```
<!--{eval 
$a = 1;
$b = array(
   'a' => 1,
   'b' => 2,
);

if($a == 1) {
   print_r($b);
}

}-->

<!--{eval echo $my_var;}-->
<!--{eval $my_arr = array(1, 2, 3);}-->
<!--{eval print_r($my_arr);}-->
<!--{eval exit();}-->
```

### method/function call

调用的方法并输出返回的内容。(方法必须以返回值)

```
{date('Y-m-d')}
{substr('123', 1)}
{print_r(array(1), 1)}
{spider::html2txt('<html></html>')}
{spider::GET('http://www.baidu.com/')}
```

### block 块声明与使用

声明一个代码块。

```
<!--声明一个 block（名字不能重复）-->
<!--{block test($a = array(1,2,3,4))}-->
    <!--{loop $a $value}-->
        $value
    <!--{/loop}-->
<!--{/block}-->

<!--调用-->
{block_test(array(4,3,2,1))}
```

更多 Demo，请参考 example 中例子。