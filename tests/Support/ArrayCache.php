<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList\Tests\Support;

class ArrayCache
{
    /** @var array<string, mixed> */
    protected array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value, mixed $ttl = null): bool
    {
        $this->items[$key] = $value;

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function all(): array
    {
        return $this->items;
    }
}