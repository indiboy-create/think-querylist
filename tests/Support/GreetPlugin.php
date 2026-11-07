<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList\Tests\Support;

use QL\QueryList;

class GreetPlugin
{
    public static function install(QueryList $ql, string $prefix = ''): void
    {
        $ql->bind('greet', function (string $name = '') use ($prefix): string {
            return $prefix . $name;
        });
    }
}