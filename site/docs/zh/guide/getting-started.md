# 快速开始

**前置**：无；**本页**：安装 PurePHP，跑通第一个组件。

本指南帮助你安装 PurePHP 并创建第一个应用：一个**组件**——一个文件里放调用函数与一棵
不含数据的模板，动态值是 **Slot** 占位符，类型化契约写在 `prepare()` 钩子里。PurePHP 中
一切数据驱动的输出都用这种方式构建；代码片段也可以像下面的安装验证那样即时渲染。

## 环境要求

- PHP 8.1 或更高版本
- Composer

## 安装

### 使用 Composer

在你的项目目录中运行以下命令：

```bash
composer require yonld/purephp
```

### 验证安装

创建一个简单的测试文件 `test.php`：

```php [test.php]
<?php

require_once __DIR__ . '/vendor/autoload.php';

use function Pure\HTML\{div, h1, p};

div(
    h1('PurePHP Installation Successful'),
    p('Congratulations! PurePHP is correctly installed.')
)->print();
```

运行测试文件：

```bash
php test.php
```

如果看到 HTML 输出，说明安装成功。注意这里使用的是即时渲染——它适合快速检查；下面的应用
以组件渲染。

## 创建第一个应用

### 1. 创建项目目录

```bash
mkdir my-purephp-app
cd my-purephp-app
composer require yonld/purephp
```

### 2. 创建第一个组件

创建 `components/Card.cmp.php`。模板是一棵不含数据的树：动态值是 `Slot` 占位符，由
`prepare()` 钩子的类型化 props 绑定：

```php [components/Card.cmp.php]
<?php

use Pure\Component\Call;
use Pure\Core\Slot;

use function Pure\Component\{component, register};
use function Pure\HTML\{div, h2, p};

function Card(mixed ...$children): Call
{
    return component(__FUNCTION__, ...$children);
}

register(Card(...),
    factory: static fn () => div(
        h2(Slot::value('title')),
        p(Slot::value('content'))
    )->class('card'),
    prepare: static function (string $title, string $content): array {
        return ['title' => $title, 'content' => $content];
    }
);

echo Card()->title('Title')->content('Content');
```

- `Slot::value('title')` 是占位符：渲染时从同名 prop 取值，转义后填入该位置；
- `register(Card(...))` 从调用函数派生名字与文件、只惰性保存工厂，不构建任何东西；
- `prepare()` 是类型化 prop 契约：PHP 强制参数类型，返回的数组绑定模板。

### 3. 运行应用

```bash
php components/Card.cmp.php
```

输出：

```html
<div class="card"><h2>Title</h2><p>Content</p></div>
```

真实应用中单元放在自己的文件里，控制器 `require` 它并用请求数据调用组件——见
[组件](/zh/guide/components)。

### 4. 下一步：走向生产

上面的写法每次都会重新构建模板，适合学习与试验。生产环境还需要三件事：

- **磁盘缓存与预编译产物**——`Compile::cachePath()` 与 `pure compile`，见
  [产物与部署](/zh/guide/artifacts)；
- **开发期 guard**——报告每请求重建、拼错的 binding 等问题，见
  [编译渲染](/zh/guide/compiled#缓存)；
- **静态契约检查**——`pure check` 在 CI 中校验 props 与 Slot，见
  [契约检查](/zh/guide/artifacts#契约检查)。

## 下一步

按顺序学习：

- [基本用法](/zh/guide/basic-usage) - 标签 API：片段、原型与调试
- [基本概念](/zh/guide/concepts) - Tag、Shape、Slot 与组件
- [Props 与 Slot](/zh/guide/props) - Slot 类型与数据绑定参考
- [组件](/zh/guide/components) - 组合、prop 契约与页面
- [编译渲染](/zh/guide/compiled) - 组件模板如何编译
- [产物与部署](/zh/guide/artifacts) - `pure compile` 产物与生产部署
