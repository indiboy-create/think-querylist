<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList;

use Psr\Log\LoggerInterface;
use think\Service as BaseService;
use Throwable;

class Service extends BaseService
{
    public function register(): void
    {
        $default = require __DIR__ . '/../config/querylist.php';
        $config = $this->app->config->get('querylist', []);

        if (!is_array($config)) {
            $config = [];
        }

        if ($config !== [] && !array_key_exists('profiles', $config) && !array_key_exists('defaults', $config)) {
            $profile = $config;
            unset($profile['default']);

            $config = array_replace_recursive($default, [
                'profiles' => [
                    'default' => $profile,
                ],
            ]);
        } else {
            $config = array_replace_recursive($default, $config);
        }

        $this->app->config->set($config, 'querylist');

        $logger = null;
        try {
            $logger = $this->app->make('log');
        } catch (Throwable) {
            $logger = null;
        }

        $cache = null;
        try {
            $cache = $this->app->make('cache');
        } catch (Throwable) {
            $cache = null;
        }

        $manager = new QueryListManager(
            $config,
            $logger instanceof LoggerInterface ? $logger : null,
            $cache
        );

        $this->app->instance('querylist', $manager);
        $this->app->instance(QueryListManager::class, $manager);
    }
}