<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList\Tests;

use think\App;
use think\Cache;
use Youvision\ThinkQueryList\Facade\QueryList as QueryListFacade;
use Youvision\ThinkQueryList\Profile;
use Youvision\ThinkQueryList\QueryListManager;
use Youvision\ThinkQueryList\Service;

if (!function_exists('app')) {
    require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';
}

class ServiceTest extends TestCase
{
    private const LIST_HTML = <<<'HTML'
<html><body>
<div class="list">
  <div class="item"><h3><a href="/a">标题一</a></h3></div>
  <div class="item"><h3><a href="/b">标题二</a></h3></div>
</div>
</body></html>
HTML;

    public function test_service_registers_manager_facade_and_helpers(): void
    {
        $root = $this->makeAppRoot();

        try {
            $app = new App($root);
            $app->config->set([
                'default' => 'file',
                'stores' => [
                    'file' => [
                        'type' => 'file',
                        'path' => $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache',
                    ],
                ],
            ], 'cache');
            $app->config->set([
                'defaults' => [
                    'range' => '.item',
                    'rules' => [
                        'title' => ['h3 a', 'text'],
                        'link' => ['h3 a', 'attr(href)'],
                    ],
                ],
                'profiles' => ['default' => []],
            ], 'querylist');

            (new Service($app))->register();

            $manager = $app->make('querylist');
            $this->assertInstanceOf(QueryListManager::class, $manager);
            $this->assertSame($manager, $app->make(QueryListManager::class));
            $this->assertInstanceOf(Cache::class, $manager->getCache());
            $this->assertSame('default', $manager->getDefaultProfileName());

            $profile = QueryListFacade::profile();
            $this->assertInstanceOf(Profile::class, $profile);
            $this->assertSame('default', $profile->getName());

            $data = querylist()->html(self::LIST_HTML)->query()->getData()->all();
            $this->assertCount(2, $data);
            $this->assertSame('标题一', trim((string) $data[0]['title']));

            $data = querylist_profile()->html(self::LIST_HTML)->query()->getData()->all();
            $this->assertCount(2, $data);

            $this->assertStringContainsString('标题二', querylist_html(self::LIST_HTML)->getHtml(false));
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_service_accepts_top_level_config(): void
    {
        $root = $this->makeAppRoot();

        try {
            $app = new App($root);
            $app->config->set([
                'http' => ['timeout' => 3],
                'rules' => ['title' => ['h1', 'text']],
            ], 'querylist');

            (new Service($app))->register();

            $manager = $app->make('querylist');
            $config = $manager->resolveProfileConfig();

            $this->assertSame(3, $config['http']['timeout']);
            $this->assertSame(5, $config['http']['connect_timeout']);
            $this->assertSame(['title' => ['h1', 'text']], $config['rules']);
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_service_cache_uses_configured_store(): void
    {
        $root = $this->makeAppRoot();

        try {
            $app = new App($root);
            $app->config->set([
                'default' => 'file',
                'stores' => [
                    'file' => [
                        'type' => 'file',
                        'path' => $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache',
                    ],
                ],
            ], 'cache');
            $app->config->set([
                'defaults' => [
                    'cache' => ['enabled' => true, 'store' => 'file', 'ttl' => 60],
                ],
                'profiles' => ['default' => []],
            ], 'querylist');

            (new Service($app))->register();
            $manager = $app->make('querylist');
            $key = 'test' . bin2hex(random_bytes(4));

            $first = trim((string) $manager->get($this->url('/count'), ['key' => $key])->find('.count')->text());
            $second = trim((string) $manager->get($this->url('/count'), ['key' => $key])->find('.count')->text());

            $this->assertSame('1', $first);
            $this->assertSame('1', $second);

            @unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_querylist_count_' . $key . '.txt');
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_service_supports_custom_default_profile(): void
    {
        $root = $this->makeAppRoot();

        try {
            $app = new App($root);
            $app->config->set([
                'default' => 'news',
                'profiles' => [
                    'news' => [
                        'rules' => ['title' => ['h1', 'text']],
                    ],
                ],
            ], 'querylist');

            (new Service($app))->register();

            $manager = $app->make('querylist');
            $this->assertSame('news', $manager->getDefaultProfileName());
            $this->assertSame(['title' => ['h1', 'text']], $manager->resolveProfileConfig()['rules']);
        } finally {
            $this->deleteDirectory($root);
        }
    }

    private function makeAppRoot(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_querylist_app_' . bin2hex(random_bytes(4));

        if (!mkdir($root, 0777, true) && !is_dir($root)) {
            $this->fail('无法创建临时应用目录');
        }

        return $root;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}