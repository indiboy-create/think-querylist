<?php

/**
 * think-querylist 配置文件
 *
 * 配置合并顺序：
 *   defaults（全局默认） < profiles.{当前采集配置} < 调用时传入的 $options。
 *
 * 顶层直写兼容：如果没有 profiles / defaults 键，则会把整个配置视为 default
 * 采集配置的覆盖项，便于简单项目快速使用。
 */
return [
    /*
     * 默认采集配置名称。
     * 类型：string
     * 默认：default
     * 生效：Facade、助手函数或依赖注入未指定采集配置时，使用该名称对应的 profiles 配置。
     */
    'default' => 'default',

    /*
     * 所有采集配置共享的默认值。
     * 类型：array
     * 说明：profiles 中的每个采集配置都会与 defaults 深度合并，采集配置优先。
     */
    'defaults' => [
        /*
         * HTTP 请求配置。
         * 类型：array
         * 说明：最终会转换为 GuzzleHttp 请求选项，由 QueryList 内置的 GHttp 执行请求。
         */
        'http' => [
            /*
             * 请求总超时时间，单位：秒。
             * 类型：int|float|null
             * 默认：10
             * 生效：非 null 时作为 Guzzle 的 timeout 选项；0 表示不限制超时。
             */
            'timeout' => 10,

            /*
             * 连接超时时间，单位：秒。
             * 类型：int|float|null
             * 默认：5
             * 生效：非 null 时作为 Guzzle 的 connect_timeout 选项；0 表示不限制连接超时。
             */
            'connect_timeout' => 5,

            /*
             * 是否验证 SSL 证书。
             * 类型：bool|string
             * 默认：false
             * 生效：false 关闭证书验证（QueryList 默认行为，适合采集自签名站点）；
             *       true 使用系统 CA 验证；字符串表示自定义 CA 证书文件路径。
             */
            'verify' => false,

            /*
             * 代理配置。
             * 类型：string|array
             * 默认：空字符串
             * 生效：非空时作为 Guzzle 的 proxy 选项。
             *       字符串示例：http://user:pass@127.0.0.1:8080
             *       数组示例：['http' => 'http://127.0.0.1:8080', 'https' => 'http://127.0.0.1:8080']
             */
            'proxy' => '',

            /*
             * 默认请求头。
             * 类型：array
             * 默认：[]
             * 生效：非空时作为 Guzzle 的 headers 选项，会整体覆盖 GHttp 内置的
             *       User-Agent 和 Referer；如需保留，请在本配置中显式设置 user_agent / referer。
             * 示例：['Accept-Language' => 'zh-CN,zh;q=0.9']
             */
            'headers' => [],

            /*
             * User-Agent。
             * 类型：string
             * 默认：空字符串
             * 生效：非空时写入请求头 User-Agent；为空时沿用 GHttp 内置的桌面浏览器 UA。
             */
            'user_agent' => '',

            /*
             * Referer。
             * 类型：string
             * 默认：空字符串
             * 生效：非空时写入请求头 Referer；为空且未设置 headers 时沿用 GHttp 内置行为
             *       （Referer 自动取当前请求 URL）。
             */
            'referer' => '',

            /*
             * Cookie 配置。
             * 类型：array|\GuzzleHttp\Cookie\CookieJarInterface
             * 默认：[]
             * 生效：数组会自动转换为 Guzzle CookieJar（domain 取当前 URL 主机），
             *       每个请求创建新的 CookieJar，不跨请求保持会话；
             *       传入 CookieJarInterface 实例可复用同一个会话。
             *       为空时不传该选项，沿用 QueryList 进程内共享的 CookieJar，
             *       同一次请求生命周期内可自动保持登录态。
             * 示例：['session_id' => 'abc123']
             */
            'cookies' => [],

            /*
             * 额外 Guzzle 请求选项。
             * 类型：array
             * 默认：[]
             * 生效：作为 Guzzle 选项的基础值，会被上面的 timeout / proxy / verify 等具体项覆盖，
             *       调用时传入的 $options 优先级最高。
             * 示例：[CURLOPT_FOLLOWLOCATION => true, 'http_errors' => false]
             */
            'options' => [],
        ],

        /*
         * 页面编码转换配置。
         * 类型：array
         * 说明：依赖 mbstring 与 iconv 扩展；仅对 GET / POST 抓取结果生效。
         */
        'encoding' => [
            /*
             * 是否自动转换页面编码。
             * 类型：bool
             * 默认：false
             * 生效：true 时在抓取完成后检测页面编码，并统一转换为 output 指定的编码。
             *       已经是 output 编码的页面会跳过转换。
             */
            'auto' => false,

            /*
             * 源页面编码。
             * 类型：string
             * 默认：空字符串
             * 生效：为空时自动检测（依次尝试 ASCII、GB2312、GBK、UTF-8）；
             *       无法检测时跳过转换并记录 warning 日志。
             */
            'input' => '',

            /*
             * 目标编码。
             * 类型：string
             * 默认：UTF-8
             * 生效：作为转换后的页面编码，建议保持 UTF-8。
             */
            'output' => 'UTF-8',
        ],

        /*
         * 响应缓存配置。
         * 类型：array
         * 说明：使用 ThinkPHP 缓存组件保存原始响应 HTML，仅对 GET 抓取生效。
         */
        'cache' => [
            /*
             * 是否启用响应缓存。
             * 类型：bool
             * 默认：false
             * 生效：true 时相同采集配置、URL、查询参数与请求选项会命中缓存，
             *       不再发起真实 HTTP 请求。
             * 注意：缓存键只区分标量 / 数组选项，CookieJar 等对象仅按类名区分；
             *       需要按登录态区分响应时，请关闭缓存或在 URL / 查询参数中体现会话标识。
             */
            'enabled' => false,

            /*
             * 缓存驱动名称。
             * 类型：string
             * 默认：空字符串
             * 生效：为空时使用 config/cache.php 中的默认缓存驱动；
             *       非空时调用 Cache::store() 使用指定驱动，驱动需已在 config/cache.php 中定义。
             */
            'store' => '',

            /*
             * 缓存有效期，单位：秒。
             * 类型：int
             * 默认：3600
             * 生效：大于 0 时作为缓存过期时间；0 表示永久有效，直到手动清除。
             */
            'ttl' => 3600,

            /*
             * 缓存键前缀。
             * 类型：string
             * 默认：querylist
             * 生效：最终缓存键格式为 {prefix}:{md5}，便于在缓存中识别和批量清理。
             */
            'prefix' => 'querylist',
        ],

        /*
         * 日志配置。
         * 类型：array
         * 说明：通过 ThinkPHP 日志组件记录抓取耗时、缓存命中和失败信息，
         *       不会记录 Cookie、Authorization 等敏感内容。
         */
        'log' => [
            /*
             * 是否记录抓取日志。
             * 类型：bool
             * 默认：false
             * 生效：true 时记录每次抓取的采集配置、URL、耗时、缓存命中状态和异常信息。
             */
            'enabled' => false,

            /*
             * 日志通道名称。
             * 类型：string
             * 默认：querylist
             * 生效：非空时调用 Log::channel() 使用指定通道，通道需在 config/log.php 中配置；
             *       为空时使用默认日志通道。
             */
            'channel' => 'querylist',
        ],

        /*
         * 默认采集规则。
         * 类型：array
         * 默认：[]
         * 生效：每次创建 QueryList 实例后自动调用 rules()，之后可直接 query() / getData()。
         * 规则格式：[选择器, 属性, 标签过滤列表, 回调]
         *   - 选择器：CSS3 选择器，例如 '.list-item h3 a'；
         *   - 属性：text、html、htmlOuter、attr(href)、attrs(src) 等；
         *   - 标签过滤列表：多个标签名以空格分隔，'-' 前缀表示移除，例如 'a -script'；
         *   - 回调：function ($content, $ruleName) {}，可选。
         * 示例：
         * 'rules' => [
         *     'title' => ['.article h1', 'text'],
         *     'link'  => ['.article a', 'attr(href)'],
         *     'cover' => ['.article img', 'attr(src)'],
         * ],
         */
        'rules' => [],

        /*
         * 默认采集范围。
         * 类型：string
         * 默认：空字符串
         * 生效：非空时自动调用 range()，用于列表页逐条提取数据；
         *       为空时 rules 直接作用于整个文档。
         * 示例：'.list-item'
         */
        'range' => '',

        /*
         * QueryList 插件列表。
         * 类型：array
         * 默认：[]
         * 生效：每次创建 QueryList 实例后自动调用 use() 安装插件。
         * 格式：
         *   - 字符串：插件类名，调用 use(Plugin::class)；
         *   - 数组：[插件类名, [参数1, 参数2]]，调用 use(Plugin::class, 参数1, 参数2)。
         * 示例：
         * 'plugins' => [
         *     \Vendor\QueryListPlugin\SomePlugin::class,
         *     [\Vendor\QueryListPlugin\AnotherPlugin::class, ['option']],
         * ],
         */
        'plugins' => [],
    ],

    /*
     * 采集配置列表。
     * 类型：array
     * 说明：每个键为采集配置名称，值为覆盖 defaults 的配置数组。
     *       可通过 querylist('news')、querylist_profile('news')、Facade::profile('news')
     *       或依赖注入 QueryListManager 后调用 profile('news') 使用。
     */
    'profiles' => [
        'default' => [],

        // 示例：新闻列表采集配置
        // 'news' => [
        //     'http' => [
        //         'timeout' => 15,
        //         'headers' => ['Accept-Language' => 'zh-CN,zh;q=0.9'],
        //     ],
        //     'range' => '.news-list li',
        //     'rules' => [
        //         'title' => ['h3 a', 'text'],
        //         'link'  => ['h3 a', 'attr(href)'],
        //         'date'  => ['.date', 'text'],
        //     ],
        //     'encoding' => ['auto' => true],
        //     'cache' => ['enabled' => true, 'ttl' => 600],
        // ],
    ],
];