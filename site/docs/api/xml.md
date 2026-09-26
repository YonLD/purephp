# XML Class

`Pure\Core\XML` extends the Tag class for creating XML elements.

## Creating XML Elements

XML tag names are arbitrary, so elements are created with the magic static
surface:

```php
<?php

use Pure\Core\XML;

// Create XML elements with magic methods
$customer = XML::customer(
    XML::name('Customer Name'),
    XML::address(
        XML::street('Street Address'),
        XML::city('City'),
        XML::zip('Zip Code')
    )
)->id('123');
```

## Save Methods

### `save(string $path, ?string $header = null): int|false`

Saves the XML element to a file. When `$header` is omitted,
`<?xml version="1.0"?>` is written first.

```php
<?php

use Pure\Core\XML;

$xml = XML::root(
    XML::item('Content 1'),
    XML::item('Content 2')
);

$result = $xml->save('output.xml');
if ($result !== false) {
    echo "XML file saved successfully";
}
```

## Document Header

### `documentHeader(): string`

Returns the default XML declaration, `<?xml version="1.0"?>`. `save()` uses it
unless an explicit header is supplied; `render()` never adds a header.

## Examples

### Configuration Files

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

### Data Export

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
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'role' => 'admin',
        'created_at' => '2024-01-01',
        'addresses' => [
            [
                'type' => 'home',
                'street' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'CA',
                'zip' => '12345'
            ]
        ]
    ]
];

$xml = exportUsers($users);
$xml->save('users.xml');
```

### RSS Feed

```php
<?php

use Pure\Core\XML;

function createRSSFeed(array $items): XML
{
    return XML::rss(
        XML::channel(
            XML::title('My Blog'),
            XML::link('https://myblog.com'),
            XML::description('Latest posts from my blog'),
            XML::language('en-us'),
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
        'title' => 'First Post',
        'url' => 'https://myblog.com/first-post',
        'description' => 'This is my first blog post',
        'date' => 'Mon, 01 Jan 2024 12:00:00 +0000'
    ]
];

$rss = createRSSFeed($posts);
$rss->save('feed.xml');
```

RSS `pubDate` values must be RFC 2822 dates, as in the example. The XML
builder escapes element text and attribute values, but it does not validate a
feed against the RSS schema.

### SOAP Envelope

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

A method name cannot contain a colon, so the example uses PHP's dynamic static
method syntax for the prefixed element names. `setAttrs()` is public and keeps
the `xmlns:soap` spelling; `->xmlns_soap(...)` would normalize the underscore to
`xmlns-soap` and is not a SOAP namespace.

### Large Documents

```php
<?php

use Pure\Core\XML;

$items = [];
for ($i = 1; $i <= 10000; $i++) {
    $items[] = XML::item("Item $i")->id((string)$i);
}

$largeXml = XML::root(...$items);
$largeXml->save('large.xml');
```
