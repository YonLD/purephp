# Raw 类

`Pure\Core\Raw` 表示可信标记，会按原样输出，不会被转义。它是信任边界，不是清理器。

## Core，而非组件层

`Raw` 位于 `Pure\Core`，用于 Shape 树中的 verbatim 子节点（例如
`div(Raw::of('<b>x</b>'))`），以及把已渲染的标记传入 `Slot::raw()` Slot。
组件层本身（`Call`、调用函数）渲染出的是普通 `string`，组件 API 中没有
`Raw` 类型。

> **信任边界**——一次调用渲染成 `string` 之后，类型系统无法再区分那个字符串
> 是"可信的已渲染标记"还是普通文本。信任改由 raw Slot 约定承载：传入 raw Slot 的
> 值原样输出，传入 value Slot 的值必然转义。

## 为什么原始内容很重要

字符串内容一律会被转义，因此形似标记的文本会显示出来，而不会被解析：

```php
<?php

use function Pure\HTML\div;

// 字符串内容被转义
div('<p>你好 <strong>世界</strong></p>')->print();
// 输出: <div>&lt;p&gt;你好 &lt;strong&gt;世界&lt;/strong&gt;&lt;/p&gt;</div>
```

Raw 类用于在需要可信标记时按原样输出：

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\div;

// 原始内容保留标记
div(Raw::of('<p>你好 <strong>世界</strong></p>'))->print();
// 输出: <div><p>你好 <strong>世界</strong></p></div>
```

## 创建

### `Raw::of(string $value): self`

将可信标记包装为 Raw 对象。构造函数是私有的，这是唯一的创建入口；`value` 是 public readonly 属性：

```php
<?php

use Pure\Core\Raw;

$raw = Raw::of('<strong>粗体文本</strong>');

echo $raw->value; // <strong>粗体文本</strong>
```

## 输出方法

### `__toString(): string`

将 Raw 对象转换为字符串：

```php
<?php

use Pure\Core\Raw;

$raw = Raw::of('<em>斜体文本</em>');
echo $raw; // 输出: <em>斜体文本</em>
```

## 示例

### 嵌入原始 HTML

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\div;

// 嵌入预格式化的 HTML 内容
$content = div(
    Raw::of('<h2>原始 HTML 内容</h2>'),
    Raw::of('<p>这个内容<strong>不会</strong>被转义。</p>'),
    Raw::of('<script>console.log("JavaScript 可以工作!");</script>')
)->class('raw-content');

echo $content;
```

### 包含外部内容

只有在检查来源与完整性后，才把外部内容包装为 Raw。`Raw::of()` 不会清理 HTML、XML、
URL、脚本或样式：

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\{div, h1};

$externalHtml = file_get_contents('external-content.html');
if ($externalHtml === false) {
    throw new RuntimeException('无法加载外部内容。');
}

$page = div(
    h1('我的页面'),
    Raw::of($externalHtml)
)->class('page');

echo $page;
```

### 模板包含

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\{html, head, title, body};

function includeTemplate(string $templatePath): string
{
    ob_start();
    include $templatePath;
    return ob_get_clean();
}

$page = html(
    head(title('我的网站')),
    body(
        Raw::of(includeTemplate('header.php')),
        Raw::of(includeTemplate('content.php')),
        Raw::of(includeTemplate('footer.php'))
    )
);

echo $page;
```

### 带有原始内容的 XML

```php
<?php

use Pure\Core\Raw;

use Pure\Core\XML;

$document = XML::document(
    XML::metadata(
        XML::title('包含原始内容的文档')
    ),
    XML::content(
        Raw::of('<![CDATA[这是包含 <特殊> 字符的原始 XML 内容]]>')
    )
);

echo $document;
```

### 条件原始内容

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\div;

$isDevelopment = true;

$page = div(
    '这里是主要内容',
    $isDevelopment ? Raw::of('<div class="debug">调试信息</div>') : ''
)->class('page');

echo $page;
```

## 安全考虑

⚠️ **重要**：原始内容不会被转义，也不会被清理。永远不要把用户输入、未校验的 URL 响应，
或带事件/style 的片段传给 `Raw::of()`。转义保护的是普通文本和属性语法，不会校验
`javascript:` URL、内联脚本、事件处理器、CSS 或动态标签名。

```php
<?php

use Pure\Core\Raw;

use function Pure\HTML\div;

$userInput = $_POST['content'] ?? '';
$dangerous = div(Raw::of($userInput));
$safe = div($userInput);

$trustedHtml = '<strong>管理员消息</strong>';
$safe = div(Raw::of($trustedHtml));
```

第一棵树按设计是不安全的；第二棵会把值作为文本转义。第三棵只有在标记是经过审核的常量时才
安全。请在应用边界校验活动输入；页面有意包含脚本或样式时，发送限制性的浏览器 Content
Security Policy。
