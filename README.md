# think-querylist

基于 [QueryList](https://github.com/jae-jae/QueryList) 的 ThinkPHP 8 网页采集扩展。

在保留 QueryList 全部能力的基础上，提供多采集配置、默认采集规则、编码转换、响应缓存、日志、Facade、助手函数与容器注入。

## 环境要求

- PHP >= 8.1
- ThinkPHP >= 8.0
- `ext-dom`、`ext-mbstring`、`ext-iconv`

## 安装

```bash
composer require youvision/think-querylist
```

服务提供者会通过 Composer 的 `extra.think.services` 自动注册，无需手动配置。

## 快速开始

```php
use Youvision\ThinkQueryList\Facade\QueryList;

$data = QueryList::get('https://example.com/list')
    ->rules([
        'title' => ['h3 a', 'text'],
        'link'  => ['h3 a', 'attr(href)'],
        'date'  => ['.date', 'text'],
    ])
    ->range('.list-item')
    ->query()
    ->getData()
    ->all();
```

也可以使用助手函数：

```php
$html = querylist_get('https://example.com/list')->getHtml(false);

$data = querylist()->html($html)
    ->rules(['title' => ['h1', 'text']])
    ->query()
    ->getData()
    ->all();
```

## 配置

包内已提供默认配置 `config/querylist.php`，开箱即用。需要自定义时，将配置文件复制到项目 `config/querylist.php`：

```php
return [
    'default' => 'news',

    'defaults' => [
        'http' => [
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify' => false,
            'proxy' => '',
            'headers' => ['Accept-Language' => 'zh-CN,zh;q=0.9'],
            'user_agent' => 'Mozilla/5.0 ...',
            'referer' => '',
            'cookies' => [],
            'options' => [],
        ],
        'encoding' => [
            'auto' => true,
            'input' => '',
            'output' => 'UTF-8',
        ],
        'cache' => [
            'enabled' => true,
            'store' => '',
            'ttl' => 600,
            'prefix' => 'querylist',
        ],
        'log' => [
            'enabled' => true,
            'channel' => 'querylist',
        ],
        'plugins' => [],
    ],

    'profiles' => [
        'news' => [
            'range' => '.news-list li',
            'rules' => [
                'title' => ['h3 a', 'text'],
                'link'  => ['h3 a', 'attr(href)'],
                'date'  => ['.date', 'text'],
            ],
        ],
        'article' => [
            'rules' => [
                'title'   => ['h1', 'text'],
                'content' => ['.article-content', 'html'],
            ],
        ],
    ],
];
```

所有配置项均带有中文注释，说明类型、默认值与生效方式。

### 配置合并顺序

```text
defaults < profiles.{当前配置} < 调用时传入的 $options
```

也支持顶层直写（不写 `defaults` / `profiles`），此时整个配置会作为 `default` 采集配置的覆盖项。

## 多采集配置

```php
use Youvision\ThinkQueryList\Facade\QueryList;

$data = QueryList::profile('news')
    ->get('https://example.com/news')
    ->query()
    ->getData()
    ->all();

$article = QueryList::profile('article')
    ->html($html)
    ->query()
    ->getData()
    ->first();
```

直接使用某个配置：

```php
$data = querylist('news')
    ->get('https://example.com/news')
    ->query()
    ->getData()
    ->all();
```

## 依赖注入

```php
use Youvision\ThinkQueryList\QueryListManager;

class News
{
    public function __construct(protected QueryListManager $queryList)
    {
    }

    public function index()
    {
        return $this->queryList->profile('news')
            ->get('https://example.com/news')
            ->query()
            ->getData()
            ->all();
    }
}
```

## 抓取与解析

```php
// GET
$ql = QueryList::get('https://example.com/list', ['page' => 2]);
$ql = QueryList::get('https://example.com/list?page=2');

// POST
$ql = QueryList::post('https://example.com/search', ['keyword' => 'thinkphp']);

// 只取原始响应 HTML
$html = QueryList::fetch('https://example.com/list');

// 解析已有 HTML
$ql = QueryList::html($html);

// 对已有 QueryList 实例应用指定配置
$ql = QueryList::applyProfile($ql, 'news');
```

`get()` 与 `post()` 的第三个参数为 Guzzle 请求选项，会覆盖配置文件中的同名项：

```php
$ql = QueryList::get('https://example.com/list', [], [
    'timeout' => 3,
    'headers' => ['User-Agent' => 'Custom-UA'],
]);
```

## 缓存

启用后，相同采集配置、URL、查询参数与请求选项会直接使用缓存响应，不再发起真实请求：

```php
'cache' => [
    'enabled' => true,
    'store' => '',      // 留空使用默认缓存驱动，也可指定 config/cache.php 中的驱动
    'ttl' => 600,       // 秒，0 表示永久
    'prefix' => 'querylist',
],
```

缓存只对 `get()` 生效，`post()` 始终发起真实请求。

## 编码转换

```php
'encoding' => [
    'auto' => true,
    'input' => '',       // 留空自动检测 GB2312 / GBK / UTF-8
    'output' => 'UTF-8',
],
```

编码转换在 HTML 交给 phpQuery 解析之前完成，避免 GBK 页面出现乱码。

## 插件

```php
'plugins' => [
    \Vendor\QueryListPlugin\SomePlugin::class,
    [\Vendor\QueryListPlugin\AnotherPlugin::class, ['option']],
],
```

每次创建 QueryList 实例时自动安装，不会污染全局 `QueryList::config()`。

## 日志

开启后通过 ThinkPHP 日志组件记录采集配置、URL、耗时、缓存命中状态和异常信息，不记录 Cookie、Authorization 等敏感内容：

```php
'log' => [
    'enabled' => true,
    'channel' => 'querylist',
],
```

## 与原生 QueryList 的关系

`QL\QueryList` 的全部方法仍然可用。本扩展只是在其外层增加了配置解析、HTTP 选项注入、缓存、日志、编码与 ThinkPHP 容器集成：

```php
$ql = querylist()->make();
$ql->get('https://example.com');
$ql->find('.title')->text();
```

需要缓存、日志与编码转换时，请使用 `querylist()->get()`、`querylist('配置名')->get()` 或 `querylist_get()`，不要直接对 `make()` 返回的实例调用 `get()`。

## 测试

```bash
composer test
```

## License

MIT