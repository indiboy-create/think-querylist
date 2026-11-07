<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use QL\QueryList as BaseQueryList;
use QL\Services\EncodeService;
use Throwable;

/**
 * QueryList 采集管理器
 *
 * 负责解析多采集配置、创建 QueryList 实例、应用默认规则 / 插件 / 编码，
 * 并在启用时复用 ThinkPHP 的缓存与日志组件。
 */
class QueryListManager
{
    protected array $config;

    protected ?LoggerInterface $logger;

    protected mixed $cache;

    /** @var array<string, Profile> */
    protected array $profiles = [];

    public function __construct(array $config = [], ?LoggerInterface $logger = null, mixed $cache = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * 创建应用了采集配置的全新 QueryList 实例。
     */
    public function make(?string $name = null): BaseQueryList
    {
        return $this->applyConfig(new BaseQueryList(), $this->resolveProfileConfig($name));
    }

    /**
     * 获取指定采集配置的包装对象。
     */
    public function profile(?string $name = null): Profile
    {
        $name = $this->resolveProfileName($name);

        if (!isset($this->profiles[$name])) {
            $this->profiles[$name] = new Profile($this, $name, $this->resolveProfileConfig($name));
        }

        return $this->profiles[$name];
    }

    /**
     * GET 抓取，默认启用响应缓存。
     */
    public function get(string $url, mixed $query = [], array $options = [], ?string $name = null): BaseQueryList
    {
        $name = $this->resolveProfileName($name);
        $config = $this->resolveProfileConfig($name);
        $ql = $this->applyConfig(new BaseQueryList(), $config);
        $started = microtime(true);
        $query = $this->resolveQuery($url, $query);
        $cacheKey = $this->cacheEnabled($config) ? $this->cacheKey($config, $name, $url, $query, $options) : null;

        if ($cacheKey !== null) {
            $cached = $this->cacheGet($config, $cacheKey);
            if ($cached !== null) {
                $ql->setHtml($this->encodeHtml($cached, $config));
                $this->log($config, 'info', 'QueryList 缓存命中', $this->logContext($name, $url, $started, true));

                return $ql;
            }
        }

        try {
            $ql->get($url, $query, $this->resolveHttpOptions($config, $options, $url));
            $html = (string) $ql->getHtml(false);

            if ($cacheKey !== null && $html !== '') {
                $this->cacheSet($config, $cacheKey, $html);
            }

            $encoded = $this->encodeHtml($html, $config);

            if ($encoded !== $html) {
                $ql->setHtml($encoded);
            }
        } catch (Throwable $e) {
            $this->log(
                $config,
                'error',
                'QueryList 抓取失败',
                $this->logContext($name, $url, $started, false) + ['error' => $e->getMessage()]
            );

            throw $e;
        }


        $this->log($config, 'info', 'QueryList 抓取完成', $this->logContext($name, $url, $started, false));

        return $ql;
    }

    /**
     * POST 抓取，不参与响应缓存。
     */
    public function post(string $url, mixed $data = [], array $options = [], ?string $name = null): BaseQueryList
    {
        $name = $this->resolveProfileName($name);
        $config = $this->resolveProfileConfig($name);
        $ql = $this->applyConfig(new BaseQueryList(), $config);
        $started = microtime(true);

        try {
            $ql->post($url, $data, $this->resolveHttpOptions($config, $options, $url));
            $html = (string) $ql->getHtml(false);
            $encoded = $this->encodeHtml($html, $config);

            if ($encoded !== $html) {
                $ql->setHtml($encoded);
            }
        } catch (Throwable $e) {
            $this->log(
                $config,
                'error',
                'QueryList 请求失败',
                $this->logContext($name, $url, $started, false) + ['error' => $e->getMessage()]
            );

            throw $e;
        }


        $this->log($config, 'info', 'QueryList 请求完成', $this->logContext($name, $url, $started, false));

        return $ql;
    }

    /**
     * 解析 HTML 字符串，不发起 HTTP 请求。
     */
    public function html(string $html, ?string $name = null): BaseQueryList
    {
        $config = $this->resolveProfileConfig($name);
        $ql = $this->applyConfig(new BaseQueryList(), $config);
        $ql->setHtml($this->encodeHtml($html, $config));

        return $ql;
    }

    /**
     * 只返回抓取到的原始响应 HTML。
     */
    public function fetch(string $url, mixed $query = [], array $options = [], ?string $name = null): string
    {
        return (string) $this->get($url, $query, $options, $name)->getHtml(false);
    }

    /**
     * 将指定采集配置应用到已有的 QueryList 实例。
     */
    public function applyProfile(BaseQueryList $ql, ?string $name = null): BaseQueryList
    {
        return $this->applyConfig($ql, $this->resolveProfileConfig($name));
    }

    public function getDefaultProfileName(): string
    {
        return $this->resolveProfileName();
    }

    /**
     * 解析采集配置名称对应的最终配置（defaults 与 profile 深度合并）。
     */
    public function resolveProfileConfig(?string $name = null): array
    {
        $name = $this->resolveProfileName($name);
        $defaults = is_array($this->config['defaults'] ?? null) ? $this->config['defaults'] : [];
        $profiles = is_array($this->config['profiles'] ?? null) ? $this->config['profiles'] : [];

        if (!array_key_exists($name, $profiles)) {
            throw new InvalidArgumentException(sprintf('QueryList 采集配置 [%s] 未定义', $name));
        }

        if (!is_array($profiles[$name])) {
            throw new InvalidArgumentException(sprintf('QueryList 采集配置 [%s] 必须是数组', $name));
        }

        return array_replace_recursive($defaults, $profiles[$name]);
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return $this->config;
        }

        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(?LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function getCache(): mixed
    {
        return $this->cache;
    }

    public function setCache(mixed $cache): static
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * 清除已缓存的采集配置包装对象。
     */
    public function forget(?string $name = null): static
    {
        if ($name === null) {
            $this->profiles = [];
        } else {
            unset($this->profiles[$this->resolveProfileName($name)]);
        }

        return $this;
    }

    protected function resolveProfileName(?string $name = null): string
    {
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $default = $this->config['default'] ?? 'default';

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /**
 * 未显式传入查询参数时，自动解析 URL 中的查询串，避免被 GHttp 的空 query 选项覆盖。
 */
protected function resolveQuery(string $url, mixed $query): mixed
{
    if ($query !== [] && $query !== null && $query !== '') {
        return $query;
    }

    $urlQuery = parse_url($url, PHP_URL_QUERY);

    if (is_string($urlQuery) && $urlQuery !== '') {
        $parsed = [];
        parse_str($urlQuery, $parsed);

        return $parsed;
    }

    return $query;
}

protected function applyConfig(BaseQueryList $ql, array $config): BaseQueryList
    {
        $this->installPlugins($ql, $config);
        $this->installRules($ql, $config);

        return $ql;
    }

    protected function installPlugins(BaseQueryList $ql, array $config): void
    {
        $plugins = $config['plugins'] ?? [];

        if (!is_array($plugins)) {
            return;
        }

        foreach ($plugins as $plugin) {
            if (is_string($plugin) && $plugin !== '') {
                $ql->use($plugin);

                continue;
            }

            if (is_array($plugin) && isset($plugin[0]) && is_string($plugin[0]) && $plugin[0] !== '') {
                $args = isset($plugin[1]) ? (array) $plugin[1] : [];
                $ql->use($plugin[0], ...array_values($args));
            }
        }
    }

    protected function installRules(BaseQueryList $ql, array $config): void
    {
        $rules = $config['rules'] ?? [];

        if (is_array($rules) && $rules !== []) {
            $ql->rules($rules);
        }

        $range = $config['range'] ?? '';

        if (is_string($range) && $range !== '') {
            $ql->range($range);
        }
    }

    /**
     * 将采集配置转换为 Guzzle 请求选项，请求级 $options 优先级最高。
     */
    protected function resolveHttpOptions(array $config, array $options, string $url = ''): array
    {
        $http = is_array($config['http'] ?? null) ? $config['http'] : [];
        $guzzle = is_array($http['options'] ?? null) ? $http['options'] : [];

        if (isset($http['timeout']) && $http['timeout'] !== '') {
            $guzzle['timeout'] = $http['timeout'];
        }

        if (isset($http['connect_timeout']) && $http['connect_timeout'] !== '') {
            $guzzle['connect_timeout'] = $http['connect_timeout'];
        }

        if (array_key_exists('verify', $http)) {
            $guzzle['verify'] = $http['verify'];
        }

        if (!empty($http['proxy'])) {
            $guzzle['proxy'] = $http['proxy'];
        }

        $headers = is_array($http['headers'] ?? null) ? $http['headers'] : [];

        if (!empty($http['user_agent'])) {
            $headers['User-Agent'] = (string) $http['user_agent'];
        }

        if (!empty($http['referer'])) {
            $headers['Referer'] = (string) $http['referer'];
        }

        if ($headers !== []) {
            $extra = is_array($guzzle['headers'] ?? null) ? $guzzle['headers'] : [];
            $guzzle['headers'] = array_replace($extra, $headers);
        }

        if (!empty($http['cookies'])) {
            $cookies = $http['cookies'];

            if ($cookies instanceof CookieJarInterface) {
                $guzzle['cookies'] = $cookies;
            } elseif (is_array($cookies)) {
                $host = (string) (parse_url($url, PHP_URL_HOST) ?: 'localhost');
                $guzzle['cookies'] = CookieJar::fromArray($cookies, $host);
            }
        }

        return array_replace_recursive($guzzle, $options);
    }

    /**
     * 在 HTML 交给 phpQuery 解析之前完成编码转换，避免文档被提前按 UTF-8 解析而产生乱码。
     */
    protected function encodeHtml(string $html, array $config): string
    {
        $encoding = is_array($config['encoding'] ?? null) ? $config['encoding'] : [];

        if (empty($encoding['auto']) || $html === '') {
            return $html;
        }

        $output = trim((string) ($encoding['output'] ?? 'UTF-8'));
        $output = $output !== '' ? $output : 'UTF-8';
        $input = trim((string) ($encoding['input'] ?? ''));

        if ($input === '') {
            // 优先确认是否为合法 UTF-8，避免 mb_detect_encoding 把 UTF-8 中文误判为 GBK。
            $input = mb_check_encoding($html, 'UTF-8') ? 'UTF-8' : (string) EncodeService::detect($html);
        }

        if ($input === '' || strcasecmp($input, $output) === 0) {
            return $html;
        }

        $converted = @iconv($input, $output . '//IGNORE', $html);

        return $converted === false ? $html : $converted;
    }

    protected function cacheEnabled(array $config): bool
    {
        return $this->cache !== null && !empty($config['cache']['enabled']);
    }

    protected function cacheStore(array $config): object
    {
        $store = trim((string) ($config['cache']['store'] ?? ''));

        if ($store !== '' && is_object($this->cache) && method_exists($this->cache, 'store')) {
            return $this->cache->store($store);
        }

        return $this->cache;
    }

    protected function cacheGet(array $config, string $key): ?string
    {
        try {
            $value = $this->cacheStore($config)->get($key);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable $e) {
            $this->log($config, 'warning', 'QueryList 读取缓存失败', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function cacheSet(array $config, string $key, string $html): void
    {
        try {
            $ttl = (int) ($config['cache']['ttl'] ?? 0);
            $this->cacheStore($config)->set($key, $html, $ttl > 0 ? $ttl : null);
        } catch (Throwable $e) {
            $this->log($config, 'warning', 'QueryList 写入缓存失败', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    protected function cacheKey(array $config, string $name, string $url, mixed $query, array $options): string
    {
        $prefix = trim((string) ($config['cache']['prefix'] ?? 'querylist'));
        $prefix = $prefix !== '' ? $prefix : 'querylist';
        $payload = [
            'profile' => $name,
            'url' => $url,
            'query' => $this->normalizeCacheValue($query),
            'options' => $this->normalizeCacheValue($options),
        ];

        return $prefix . ':' . md5(serialize($payload));
    }

    protected function normalizeCacheValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->normalizeCacheValue($item);
            }

            return $result;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_object($value)) {
            return ['__object' => get_class($value)];
        }

        return null;
    }

    protected function log(array $config, string $level, string $message, array $context = []): void
    {
        $log = is_array($config['log'] ?? null) ? $config['log'] : [];

        if (empty($log['enabled']) || $this->logger === null) {
            return;
        }

        $channel = trim((string) ($log['channel'] ?? ''));

        try {
            $logger = $channel !== '' && method_exists($this->logger, 'channel')
                ? $this->logger->channel($channel)
                : $this->logger;

            $logger->log($level, $message, $context);
        } catch (Throwable) {
            // 日志组件异常不影响采集流程
        }
    }

    protected function logContext(string $name, string $url, float $started, bool $cached): array
    {
        return [
            'profile' => $name,
            'url' => $url,
            'cached' => $cached,
            'duration' => round((microtime(true) - $started) * 1000, 2),
        ];
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->make()->{$method}(...$parameters);
    }
}