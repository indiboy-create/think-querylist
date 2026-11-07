<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList\Facade;

use think\Facade;

/**
 * @see \Youvision\ThinkQueryList\QueryListManager
 * @see \QL\QueryList
 *
 * @method static \QL\QueryList make(?string $name = null)
 * @method static \Youvision\ThinkQueryList\Profile profile(?string $name = null)
 * @method static \QL\QueryList get(string $url, mixed $query = [], array $options = [], ?string $name = null)
 * @method static \QL\QueryList post(string $url, mixed $data = [], array $options = [], ?string $name = null)
 * @method static \QL\QueryList html(string $html, ?string $name = null)
 * @method static string fetch(string $url, mixed $query = [], array $options = [], ?string $name = null)
 * @method static \QL\QueryList applyProfile(\QL\QueryList $ql, ?string $name = null)
 * @method static string getDefaultProfileName()
 * @method static array resolveProfileConfig(?string $name = null)
 * @method static mixed getConfig(?string $key = null, mixed $default = null)
 * @method static \Psr\Log\LoggerInterface|null getLogger()
 * @method static \Youvision\ThinkQueryList\QueryListManager setLogger(\Psr\Log\LoggerInterface|null $logger)
 * @method static mixed getCache()
 * @method static \Youvision\ThinkQueryList\QueryListManager setCache(mixed $cache)
 * @method static \Youvision\ThinkQueryList\QueryListManager forget(?string $name = null)
 */
class QueryList extends Facade
{
    protected static function getFacadeClass()
    {
        return 'querylist';
    }
}