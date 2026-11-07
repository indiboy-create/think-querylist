<?php

declare(strict_types=1);

use QL\QueryList as BaseQueryList;
use Youvision\ThinkQueryList\Profile;
use Youvision\ThinkQueryList\QueryListManager;

if (!function_exists('querylist')) {
    /**
     * 获取采集管理器；指定采集配置时返回对应的配置包装对象。
     */
    function querylist(?string $profile = null): QueryListManager|Profile
    {
        $manager = app('querylist');

        return $profile === null ? $manager : $manager->profile($profile);
    }
}

if (!function_exists('querylist_profile')) {
    /**
     * 获取指定采集配置的包装对象。
     */
    function querylist_profile(?string $profile = null): Profile
    {
        return app('querylist')->profile($profile);
    }
}

if (!function_exists('querylist_get')) {
    /**
     * 使用指定采集配置发起 GET 抓取。
     */
    function querylist_get(
        string $url,
        mixed $query = [],
        array $options = [],
        ?string $profile = null
    ): BaseQueryList {
        return app('querylist')->get($url, $query, $options, $profile);
    }
}

if (!function_exists('querylist_post')) {
    /**
     * 使用指定采集配置发起 POST 请求。
     */
    function querylist_post(
        string $url,
        mixed $data = [],
        array $options = [],
        ?string $profile = null
    ): BaseQueryList {
        return app('querylist')->post($url, $data, $options, $profile);
    }
}

if (!function_exists('querylist_html')) {
    /**
     * 使用指定采集配置解析 HTML 字符串。
     */
    function querylist_html(string $html, ?string $profile = null): BaseQueryList
    {
        return app('querylist')->html($html, $profile);
    }
}