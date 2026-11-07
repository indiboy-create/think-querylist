<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList;

use QL\QueryList as BaseQueryList;

/**
 * 采集配置包装对象
 *
 * 用于按名称固定一套采集配置，调用 get() / html() 时无需重复传入配置名称。
 */
class Profile
{
    public function __construct(
        protected QueryListManager $manager,
        protected string $name,
        protected array $config
    ) {
    }

    public function getName(): string
    {
        return $this->name;
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

    /**
     * 创建应用了当前采集配置的全新 QueryList 实例。
     */
    public function make(): BaseQueryList
    {
        return $this->manager->make($this->name);
    }

    /**
     * 将当前采集配置应用到已有 QueryList 实例。
     */
    public function apply(BaseQueryList $ql): BaseQueryList
    {
        return $this->manager->applyProfile($ql, $this->name);
    }

    public function get(string $url, mixed $query = [], array $options = []): BaseQueryList
    {
        return $this->manager->get($url, $query, $options, $this->name);
    }

    public function post(string $url, mixed $data = [], array $options = []): BaseQueryList
    {
        return $this->manager->post($url, $data, $options, $this->name);
    }

    public function html(string $html): BaseQueryList
    {
        return $this->manager->html($html, $this->name);
    }

    public function fetch(string $url, mixed $query = [], array $options = []): string
    {
        return $this->manager->fetch($url, $query, $options, $this->name);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->make()->{$method}(...$parameters);
    }
}