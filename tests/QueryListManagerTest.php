<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList\Tests;

use InvalidArgumentException;
use Throwable;
use Youvision\ThinkQueryList\QueryListManager;
use Youvision\ThinkQueryList\Tests\Support\ArrayCache;
use Youvision\ThinkQueryList\Tests\Support\ArrayLogger;
use Youvision\ThinkQueryList\Tests\Support\GreetPlugin;

class QueryListManagerTest extends TestCase
{
    private const LIST_HTML = <<<'HTML'
<html><body>
<div class="list">
  <div class="item"><h3><a href="/a">标题一</a></h3><span class="date">2026-01-01</span></div>
  <div class="item"><h3><a href="/b">标题二</a></h3><span class="date">2026-01-02</span></div>
</div>
</body></html>
HTML;

    public function test_html_applies_default_rules_and_range(): void
    {
        $manager = new QueryListManager($this->basicConfig());
        $data = $manager->html(self::LIST_HTML)->query()->getData()->all();

        $this->assertCount(2, $data);
        $this->assertSame('标题一', trim((string) $data[0]['title']));
        $this->assertSame('/a', $data[0]['link']);
        $this->assertSame('2026-01-02', trim((string) $data[1]['date']));
    }

    public function test_profile_inherits_defaults_and_overrides_config(): void
    {
        $manager = new QueryListManager([
            'default' => 'default',
            'defaults' => [
                'http' => ['timeout' => 10, 'connect_timeout' => 5],
                'range' => '.item',
            ],
            'profiles' => [
                'default' => [],
                'news' => ['http' => ['timeout' => 3]],
            ],
        ]);

        $profile = $manager->profile('news');

        $this->assertSame('news', $profile->getName());
        $this->assertSame(3, $profile->getConfig('http.timeout'));
        $this->assertSame(5, $profile->getConfig('http.connect_timeout'));
        $this->assertSame('.item', $profile->getConfig('range'));
    }

    public function test_undefined_profile_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QueryListManager($this->basicConfig()))->get($this->url('/list'), [], [], 'missing');
    }

    public function test_get_sends_configured_headers_and_query(): void
    {
        $manager = new QueryListManager([
            'defaults' => [
                'http' => [
                    'headers' => ['X-Test' => 'yes'],
                    'user_agent' => 'ThinkQueryList-Test',
                    'referer' => 'https://example.com/',
                ],
            ],
            'profiles' => ['default' => []],
        ]);

        $payload = json_decode($manager->get($this->url('/headers'), ['page' => '2'])->getHtml(false), true);

        $this->assertSame('GET', $payload['method']);
        $this->assertSame('ThinkQueryList-Test', $payload['user_agent']);
        $this->assertSame('https://example.com/', $payload['referer']);
        $this->assertSame('2', $payload['query']['page']);
    }

    public function test_url_query_string_is_preserved(): void
{
        $manager = new QueryListManager($this->basicConfig());
        $payload = json_decode($manager->get($this->url('/headers?page=3'))->getHtml(false), true);

        $this->assertSame('3', $payload['query']['page']);
    }

    public function test_request_options_override_config(): void
    {
        $manager = new QueryListManager([
            'defaults' => [
                'http' => [
                    'user_agent' => 'From-Config',
                ],
            ],
            'profiles' => ['default' => []],
        ]);

        $payload = json_decode(
            $manager->get($this->url('/headers'), [], ['headers' => ['User-Agent' => 'From-Options']])->getHtml(false),
            true
        );

        $this->assertSame('From-Options', $payload['user_agent']);
    }

    public function test_http_options_are_merged_and_overridden(): void
    {
        $manager = new QueryListManager([
            'defaults' => [
                'http' => [
                    'timeout' => 5,
                    'verify' => true,
                    'proxy' => 'http://127.0.0.1:8080',
                    'options' => [
                        'timeout' => 99,
                        'allow_redirects' => false,
                    ],
                ],
            ],
            'profiles' => ['default' => []],
        ]);

        $method = new \ReflectionMethod($manager, 'resolveHttpOptions');
        $method->setAccessible(true);

        $options = $method->invoke(
            $manager,
            $manager->resolveProfileConfig(),
            ['connect_timeout' => 1],
            'https://example.com/path'
        );

        $this->assertSame(5, $options['timeout']);
        $this->assertTrue($options['verify']);
        $this->assertSame('http://127.0.0.1:8080', $options['proxy']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(1, $options['connect_timeout']);
    }

    public function test_http_timeout_is_applied(): void
    {
        $manager = new QueryListManager([
            'defaults' => ['http' => ['timeout' => 0.1]],
            'profiles' => ['default' => []],
        ]);

        $thrown = null;

        try {
            $manager->get($this->url('/sleep'), ['ms' => 500]);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, '超时配置未生效，请求没有在 0.1 秒后中断');
        $this->assertStringContainsString('timed out', strtolower($thrown->getMessage()));
    }

    public function test_get_uses_cache_on_second_request(): void
    {
        $cache = new ArrayCache();
        $key = 'test' . bin2hex(random_bytes(4));

        $manager = new QueryListManager([
            'defaults' => [
                'cache' => ['enabled' => true, 'ttl' => 60, 'prefix' => 'ql-test'],
            ],
            'profiles' => ['default' => []],
        ], null, $cache);

        $url = $this->url('/count');
        $first = trim((string) $manager->get($url, ['key' => $key])->find('.count')->text());
        $second = trim((string) $manager->get($url, ['key' => $key])->find('.count')->text());

        $this->assertSame('1', $first);
        $this->assertSame('1', $second);
        $this->assertNotEmpty($cache->all());
        $this->assertStringStartsWith('ql-test:', array_key_first($cache->all()));

        @unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_querylist_count_' . $key . '.txt');
    }

    public function test_cache_allows_different_query_parameters(): void
    {
        $cache = new ArrayCache();
        $key = 'test' . bin2hex(random_bytes(4));

        $manager = new QueryListManager([
            'defaults' => [
                'cache' => ['enabled' => true, 'ttl' => 60],
            ],
            'profiles' => ['default' => []],
        ], null, $cache);

        $first = trim((string) $manager->get($this->url('/count'), ['key' => $key])->find('.count')->text());
        $second = trim((string) $manager->get($this->url('/count'), ['key' => $key . '-other'])->find('.count')->text());

        $this->assertSame('1', $first);
        $this->assertSame('1', $second);
        $this->assertCount(2, $cache->all());

        @unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_querylist_count_' . $key . '.txt');
        @unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_querylist_count_' . $key . '-other.txt');
    }

    public function test_post_request_sends_form_data(): void
    {
        $manager = new QueryListManager($this->basicConfig());
        $payload = json_decode(
            $manager->post($this->url('/echo'), ['name' => '测试'])->getHtml(false),
            true
        );

        $this->assertSame('POST', $payload['method']);
        $this->assertSame('测试', $payload['post']['name']);
    }

    public function test_encoding_is_converted_automatically(): void
    {
        $manager = new QueryListManager([
            'defaults' => [
                'encoding' => ['auto' => true],
            ],
            'profiles' => ['default' => []],
        ]);

        $ql = $manager->get($this->url('/gbk'));

        $this->assertStringContainsString('中文标题', (string) $ql->getHtml(false));
        $this->assertSame('中文标题', trim((string) $ql->find('.title')->text()));
    }

    public function test_utf8_content_is_not_misdetected(): void
    {
        $manager = new QueryListManager([
            'defaults' => [
                'encoding' => ['auto' => true],
            ],
            'profiles' => ['default' => []],
        ]);

        $ql = $manager->get($this->url('/list'));
        $text = (string) $ql->find('h3 a')->text();

        $this->assertStringContainsString('标题一', $text);
        $this->assertStringContainsString('标题二', $text);
    }

    public function test_plugins_are_installed(): void
    {
        $manager = new QueryListManager([
            'defaults' => [
                'plugins' => [
                    [GreetPlugin::class, ['你好，']],
                ],
            ],
            'profiles' => ['default' => []],
        ]);

        $ql = $manager->html('<html><body></body></html>');

        $this->assertSame('你好，世界', $ql->greet('世界'));
    }

    public function test_log_records_when_enabled(): void
    {
        $logger = new ArrayLogger();
        $manager = new QueryListManager([
            'defaults' => [
                'log' => ['enabled' => true, 'channel' => 'querylist-test'],
            ],
            'profiles' => ['default' => []],
        ], $logger);

        $manager->get($this->url('/list'));

        $this->assertNotEmpty($logger->records);
        $this->assertSame('info', $logger->records[0][0]);
        $this->assertSame('querylist-test', $logger->currentChannel);
        $this->assertArrayHasKey('duration', $logger->records[0][2]);
        $this->assertSame($this->url('/list'), $logger->records[0][2]['url']);
    }

    public function test_log_is_disabled_by_default(): void
    {
        $logger = new ArrayLogger();
        $manager = new QueryListManager($this->basicConfig(), $logger);

        $manager->get($this->url('/list'));

        $this->assertEmpty($logger->records);
    }

    public function test_cookies_are_sent(): void
    {
        $manager = new QueryListManager([
            'defaults' => [
                'http' => ['cookies' => ['session' => 'abc123']],
            ],
            'profiles' => ['default' => []],
        ]);

        $payload = json_decode($manager->get($this->url('/cookies'))->getHtml(false), true);

        $this->assertSame('abc123', $payload['cookies']['session']);
    }

    public function test_fetch_returns_raw_html(): void
    {
        $manager = new QueryListManager($this->basicConfig());
        $html = $manager->fetch($this->url('/list'));

        $this->assertStringContainsString('标题一', $html);
        $this->assertStringContainsString('class="item"', $html);
    }

    public function test_manager_forwards_unknown_calls_to_querylist(): void
    {
        $manager = new QueryListManager($this->basicConfig());

        $ql = $manager->rules([
            'title' => ['h3 a', 'text'],
        ]);
        $this->assertInstanceOf(\QL\QueryList::class, $ql);

        $data = $ql->range('.item')->html(self::LIST_HTML)->query()->getData()->all();

        $this->assertCount(2, $data);
        $this->assertSame('标题二', trim((string) $data[1]['title']));
    }

    public function test_forget_clears_profile_cache(): void
    {
        $manager = new QueryListManager($this->basicConfig());
        $first = $manager->profile();
        $this->assertSame($first, $manager->profile());

        $manager->forget();

        $this->assertNotSame($first, $manager->profile());
    }

    private function basicConfig(): array
    {
        return [
            'default' => 'default',
            'defaults' => [
                'range' => '.item',
                'rules' => [
                    'title' => ['h3 a', 'text'],
                    'link' => ['h3 a', 'attr(href)'],
                    'date' => ['.date', 'text'],
                ],
            ],
            'profiles' => ['default' => []],
        ];
    }
}