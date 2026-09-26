# XML 类

`Pure\Core\XML` 继承自 Tag 类，用于创建 XML 元素。

## 创建 XML 元素

XML 标签名是任意的，因此元素通过魔术静态接口创建：

```php
<?php

use Pure\Core\XML;

// 使用魔术方法创建 XML 元素
$customer = XML::customer(
    XML::name('客户名称'),
    XML::address(
        XML::street('街道地址'),
        XML::city('城市'),
        XML::zip('邮编')
    )
)->id('123');
```

## 保存方法

### `save(string $path, ?string $header = null): int|false`

将 XML 元素保存到文件。省略 `$header` 时会先写入 `<?xml version="1.0"?>`。

```php
<?php

use Pure\Core\XML;

$xml = XML::root(
    XML::item('内容1'),
    XML::item('内容2')
);

$result = $xml->save('output.xml');
if ($result !== false) {
    echo "XML 文件保存成功";
}
```

## 文档头

### `documentHeader(): string`

返回默认 XML 声明 `<?xml version="1.0"?>`。`save()` 默认使用它，除非显式传入
header；`render()` 不会自动添加文档头。

## 示例

### 配置文件

```php
<?php

use Pure\Core\XML;

$config = XML::configuration(
    XML::database(
        XML::host('localhost'),
        XML::port('3306'),
        XML::name('myapp'),
        XML::username('user'),
        XML::password('pass')
    ),
    XML::cache(
        XML::enabled('true'),
        XML::ttl('3600')
    ),
    XML::logging(
        XML::level('info'),
        XML::file('/var/log/app.log')
    )
)->version('1.0');

$config->save('config.xml');
```

### 数据导出

```php
<?php

use Pure\Core\XML;

function exportUsers(array $users): XML
{
    $userElements = [];

    foreach ($users as $userData) {
        $addressElements = [];
        foreach ($userData['addresses'] ?? [] as $address) {
            $addressElements[] = XML::address(
                XML::street($address['street']),
                XML::city($address['city']),
                XML::state($address['state']),
                XML::zip($address['zip'])
            )->type($address['type']);
        }

        $userElements[] = XML::user(
            XML::name($userData['name']),
            XML::email($userData['email']),
            XML::role($userData['role']),
            XML::created($userData['created_at']),
            $addressElements === [] ? null : XML::addresses(...$addressElements)
        )->id($userData['id']);
    }

    return XML::users(...$userElements);
}

$users = [
    [
        'id' => '1',
        'name' => '张三',
        'email' => 'zhangsan@example.com',
        'role' => 'admin',
        'created_at' => '2024-01-01',
        'addresses' => [
            [
                'type' => 'home',
                'street' => '中山路 1 号',
                'city' => '上海',
                'state' => '上海',
                'zip' => '200000'
            ]
        ]
    ]
];

$xml = exportUsers($users);
$xml->save('users.xml');
```

### RSS 订阅

```php
<?php

use Pure\Core\XML;

function createRSSFeed(array $items): XML
{
    return XML::rss(
        XML::channel(
            XML::title('我的博客'),
            XML::link('https://myblog.com'),
            XML::description('我的博客最新文章'),
            XML::language('zh-cn'),
            XML::pubDate(date('r')),
            ...array_map(function($item) {
                return XML::item(
                    XML::title($item['title']),
                    XML::link($item['url']),
                    XML::description($item['description']),
                    XML::pubDate($item['date']),
                    XML::guid($item['url'])
                );
            }, $items)
        )
    )->version('2.0');
}

$posts = [
    [
        'title' => '第一篇文章',
        'url' => 'https://myblog.com/first-post',
        'description' => '这是我的第一篇博客文章',
        'date' => 'Mon, 01 Jan 2024 12:00:00 +0000'
    ]
];

$rss = createRSSFeed($posts);
$rss->save('feed.xml');
```

RSS 的 `pubDate` 必须使用 RFC 2822 日期，示例采用了这种格式。XML 构建器会转义元素文本
和属性值，但不会替你验证 feed 是否符合 RSS schema。

### SOAP 信封

```php
<?php

use Pure\Core\XML;

$soapNamespace = 'http://schemas.xmlsoap.org/soap/envelope/';
$soapEnvelope = XML::{'soap:Envelope'}(
    XML::{'soap:Header'}(
        XML::{'soap:Authentication'}(
            XML::username('user'),
            XML::password('pass')
        )
    ),
    XML::{'soap:Body'}(
        XML::{'soap:GetUserRequest'}(
            XML::userId('123')
        )
    )
)->setAttrs(['xmlns:soap' => $soapNamespace]);

echo $soapEnvelope;
```

PHP 方法名不能包含冒号，因此示例用动态静态方法语法创建带前缀的元素名。`setAttrs()`
是公开方法，会保留 `xmlns:soap` 的拼写；`->xmlns_soap(...)` 会把下划线归一化为
`xmlns-soap`，并不是 SOAP 命名空间。

### 大型文档

```php
<?php

use Pure\Core\XML;

$items = [];
for ($i = 1; $i <= 10000; $i++) {
    $items[] = XML::item("项目 $i")->id((string)$i);
}

$largeXml = XML::root(...$items);
$largeXml->save('large.xml');
```
