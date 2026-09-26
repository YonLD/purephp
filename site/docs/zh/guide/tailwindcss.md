# PurePHP 与 Tailwind CSS 集成

**前置**：[组件](/zh/guide/components)；**本页**：锁定 Tailwind CSS v4 版本并编写可访问的组件示例。

PurePHP 负责 HTML 结构，Tailwind CSS 负责工具类。本页明确选择一个主版本：**Tailwind CSS
4.3.3**，并使用专用的 `@tailwindcss/cli` 包。这里采用 v4 的 CSS-first 流程，不在同一项目
中混用 v3 配置或 `@tailwind` 指令。

## 快速开始

### 1. 安装锁定的工具链

安装 PurePHP 和精确版本的 Tailwind 包：

```bash
composer require yonld/purephp
npm install --save-dev --save-exact tailwindcss@4.3.3 @tailwindcss/cli@4.3.3
```

提交 `package-lock.json`，可重复构建时使用 `npm ci`。如果需要升级 Tailwind，应同时明确修改
两个包并重新构建 CSS；不要让其中一个包在无意中自动跨到另一个主版本。

### 2. 创建 CSS 入口

创建 `src/input.css`。普通的 CSS import 是 v4 入口。`@source` 路径相对于这个文件，指向包含
类名字符串的 PHP 源码目录：

```css [src/input.css]
@import "tailwindcss";
@source "../components";
@source "../views";
```

如果应用还有其他包含 PHP 模板的目录，就为每个目录增加一条 `@source`。这个流程不要求
`tailwind.config.js`，也不使用 v3 的 `@tailwind base`、`@tailwind components` 和
`@tailwind utilities` 指令。

### 3. 构建样式表

在项目根目录运行以下命令，使用专用 CLI 并保持输入和输出路径一致：

```bash
npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --watch
```

生产构建使用同一个文件和锁定依赖，并压缩输出：

```bash
npm ci
npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --minify
```

对应的 `package.json` scripts 是：

```json
{
  "scripts": {
    "css:watch": "npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --watch",
    "css:build": "npx @tailwindcss/cli -i ./src/input.css -o ./public/app.css --minify"
  }
}
```

页面应加载生成的文件，而不是输入文件：

```html
<link rel="stylesheet" href="/app.css">
```

## 基础用法

下面的 PHP 示例假定文件位于项目根目录。如果把某个文件移动到嵌套的组件目录，请相应调整
其中的 `vendor/autoload.php` 路径。

### 简单组件

静态类字符串属于数据无关的标签树；标题和内容是请求值，通过 Slot 传入：

```php [card.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h1, p};

function Card(string $title, string $content, string $variant = 'default'): string
{
    static $renders = [];

    $baseClasses = 'rounded-lg shadow-md p-6 bg-white';
    $variantClasses = match ($variant) {
        'primary' => 'border-l-4 border-blue-500',
        'success' => 'border-l-4 border-green-500',
        'warning' => 'border-l-4 border-yellow-500',
        'danger' => 'border-l-4 border-red-500',
        default => 'border border-gray-200',
    };

    $render = $renders[$variant] ??= Compile::shape(
        div(
            h1(Slot::value('title'))->class('text-xl font-bold text-gray-900 mb-2'),
            p(Slot::value('content'))->class('text-gray-600 leading-relaxed')
        )->class("{$baseClasses} {$variantClasses}")
    );

    return $render([
        'title' => $title,
        'content' => $content,
    ]);
}

echo Card('Welcome to PurePHP', 'Styled with Tailwind CSS', 'primary');
```

### 响应式布局

网格是一个 Shape，已经渲染的项目卡片通过 raw slot 注入。图片 URL 是 value slot，因此会
作为属性被转义：

```php [responsive-grid.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{div, h2, img, p};

function ProjectCard(string $title, string $description, string $image): string
{
    static $render = null;

    $render ??= Compile::shape(
        div(
            img()->src(Slot::value('image'))->alt(Slot::value('title'))
                ->class('w-full h-48 object-cover rounded-t-lg'),
            div(
                h2(Slot::value('title'))->class('text-lg font-semibold mb-2'),
                p(Slot::value('description'))->class('text-gray-600 text-sm')
            )->class('p-4')
        )->class('bg-white rounded-lg shadow-md overflow-hidden')
    );

    return $render([
        'title' => $title,
        'description' => $description,
        'image' => $image,
    ]);
}

function ResponsiveGrid(array $items): string
{
    static $render = null;

    $render ??= Compile::shape(
        div(Slot::raw('items'))
            ->class('grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6')
    );

    $cards = [];

    foreach ($items as $item) {
        $cards[] = ProjectCard(
            $item['title'],
            $item['description'],
            $item['image']
        );
    }

    return $render(['items' => implode('', $cards)]);
}

echo ResponsiveGrid([
    ['title' => 'Project 1', 'description' => 'Description 1', 'image' => 'image1.jpg'],
    ['title' => 'Project 2', 'description' => 'Description 2', 'image' => 'image2.jpg'],
    ['title' => 'Project 3', 'description' => 'Description 3', 'image' => 'image3.jpg'],
]);
```

### 可访问的表单字段

`FormField()` 会按请求的类型分派。`textarea` 的值是 `textarea()` 的子节点，而不是
`type="textarea"` 的 input。label 通过 `for` 关联控件，控件公开 `aria-invalid`，只有存在
错误时 `aria-describedby` 才指向错误消息。

```php [contact-form.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{button, div, form, input, label, span, textarea};

const INPUT_CLASS = 'w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 border-gray-300';

function FormField(
    string $labelText,
    string $name,
    string $type = 'text',
    string $placeholder = '',
    string $value = '',
    bool $required = false,
    string $inputClass = INPUT_CLASS,
    string $error = ''
): string {
    static $renders = [];

    $key = "{$labelText}|{$name}|{$type}|{$placeholder}|" . (int)$required;
    $control = $type === 'textarea'
        ? textarea(Slot::value('value'))
            ->name($name)
            ->id($name)
            ->placeholder($placeholder)
            ->required($required)
            ->class(Slot::value('inputClass'))
            ->aria_invalid(Slot::value('ariaInvalid'))
            ->aria_describedby(Slot::value('describedBy')->default(null))
        : input()
            ->type($type)
            ->name($name)
            ->id($name)
            ->placeholder($placeholder)
            ->required($required)
            ->value(Slot::value('value'))
            ->class(Slot::value('inputClass'))
            ->aria_invalid(Slot::value('ariaInvalid'))
            ->aria_describedby(Slot::value('describedBy')->default(null));

    $render = $renders[$key] ??= Compile::shape(
        div(
            label($labelText)
                ->for($name)
                ->class('block text-sm font-medium text-gray-700 mb-1'),
            $control,
            Slot::if(
                'error',
                span(Slot::value('error'))
                    ->id("{$name}-error")
                    ->class('text-red-500 text-sm mt-1')
                    ->role('alert')
            )
        )->class('mb-4')
    );

    return $render([
        'value' => $value,
        'inputClass' => $inputClass,
        'ariaInvalid' => $error !== '' ? 'true' : 'false',
        'describedBy' => $error !== '' ? "{$name}-error" : null,
        'error' => $error,
    ]);
}

function ContactForm(array $fields): string
{
    static $render = null;

    $render ??= Compile::shape(
        form(
            Slot::raw('fields'),
            button('Submit')
                ->type('submit')
                ->class('w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200')
        )->class('max-w-md mx-auto bg-white p-6 rounded-lg shadow-md')
    );

    $html = '';

    foreach ($fields as $field) {
        $html .= FormField(
            labelText: $field['label'],
            name: $field['name'],
            type: $field['type'] ?? 'text',
            placeholder: $field['placeholder'] ?? '',
            value: $field['value'] ?? '',
            required: $field['required'] ?? false,
            inputClass: $field['inputClass'] ?? INPUT_CLASS,
            error: $field['error'] ?? ''
        );
    }

    return $render(['fields' => $html]);
}

echo ContactForm([
    [
        'label' => 'Name',
        'name' => 'name',
        'placeholder' => 'Enter your name',
        'required' => true,
    ],
    [
        'label' => 'Email',
        'name' => 'email',
        'type' => 'email',
        'placeholder' => 'Enter your email',
        'value' => 'not-an-email',
        'required' => true,
        'error' => 'Enter a valid email address',
    ],
    [
        'label' => 'Message',
        'name' => 'message',
        'type' => 'textarea',
        'placeholder' => 'Enter your message',
        'value' => 'Tell us what you need.',
    ],
]);
```

示例对 input 属性和 textarea 子节点都使用 `Slot::value('value')`，因此提交值会被转义。
类名应像示例一样来自应用代码或配置：`Slot::value()` 只做转义、不校验 class token，
绝不要把原始用户输入当作 Tailwind class。

## 高级用法

### 动态类名

从固定映射中选择 variant、尺寸和状态。只有标签是动态的：

```php [action-button.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\button;

function ActionButton(
    string $text,
    string $variant = 'primary',
    string $size = 'md',
    bool $disabled = false,
    bool $fullWidth = false
): string {
    static $renders = [];

    $baseClasses = 'font-medium rounded-md transition duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2';
    $variantClasses = match ($variant) {
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
        'success' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'outline' => 'border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-blue-500',
        default => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
    };
    $sizeClasses = match ($size) {
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
        default => 'px-4 py-2 text-base',
    };
    $widthClasses = $fullWidth ? 'w-full' : '';
    $disabledClasses = $disabled ? 'opacity-50 cursor-not-allowed' : '';
    $allClasses = trim("{$baseClasses} {$variantClasses} {$sizeClasses} {$widthClasses} {$disabledClasses}");
    $key = "{$variant}|{$size}|" . (int)$disabled . (int)$fullWidth;

    $render = $renders[$key] ??= Compile::shape(
        button(Slot::value('text'))
            ->class($allClasses)
            ->disabled($disabled)
    );

    return $render(['text' => $text]);
}

echo ActionButton('Primary Button', 'primary', 'lg');
```

### 主题切换

按钮把下一个主题放在 data 属性中。应用的小型 JavaScript 监听器可以切换 document class；
PHP 侧只渲染可信标记和静态类列表。

```php [theme.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Pure\Compile\Compile;
use Pure\Core\Slot;

use function Pure\HTML\{button, div, h1, main};

function Page(string $title): string
{
    static $render = null;

    $render ??= Compile::shape(
        main(h1(Slot::value('title')))->class('container mx-auto p-6')
    );

    return $render(['title' => $title]);
}

function ThemeToggle(string $currentTheme = 'light'): string
{
    static $renders = [];

    $nextTheme = $currentTheme === 'light' ? 'dark' : 'light';
    $render = $renders[$currentTheme] ??= Compile::shape(
        button('Toggle theme')
            ->type('button')
            ->data_theme($nextTheme)
            ->class('fixed top-4 right-4 px-4 py-2 rounded-md bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200')
    );

    return $render([]);
}

function ThemeProvider(string $theme, string $toggle, string $page): string
{
    static $renders = [];

    $themeClasses = match ($theme) {
        'dark' => 'bg-gray-900 text-white',
        'light' => 'bg-white text-gray-900',
        default => 'bg-white text-gray-900',
    };
    $render = $renders[$theme] ??= Compile::shape(
        div(
            Slot::raw('toggle'),
            Slot::raw('page')
        )->class("min-h-screen {$themeClasses}")
    );

    return $render(['toggle' => $toggle, 'page' => $page]);
}

echo ThemeProvider('dark', ThemeToggle('dark'), Page('Dashboard'));
```

### 合并类名

用内置 `clx()` 处理小而明确的类列表：

```php [class-list.php]
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use function Pure\Utils\clx;

$classes = clx(
    'base-class',
    'another-class',
    [
        'active' => true,
        'error' => false,
        'text-red-500' => false,
    ]
);

echo $classes;
```

## 下一步

1. [基本用法](/zh/guide/basic-usage)——检查生成的标签树和 fragment。
2. [组件](/zh/guide/components)——把静态 Shape 移入注册单元。
3. [编译渲染](/zh/guide/compiled)——理解记忆化和 Slot 绑定。
4. [HTMX](/zh/guide/htmx)——让服务端端点返回带样式的 fragment。
5. [产物与部署](/zh/guide/artifacts)——在 CI 中运行 CSS 构建和 PHP 检查。

本页使用 Tailwind v4.3.3。升级工具链时，应一起维护包版本、`@source` 路径和 CLI 命令。
完整的工具类参考见 [Tailwind CSS 文档](https://tailwindcss.com/docs)。
