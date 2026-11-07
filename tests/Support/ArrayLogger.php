<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList\Tests\Support;

use Psr\Log\AbstractLogger;

class ArrayLogger extends AbstractLogger
{
    /** @var array<int, array{0: mixed, 1: string, 2: array}> */
    public array $records = [];

    public string $currentChannel = '';

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }

    public function channel(?string $name = null): static
    {
        $this->currentChannel = (string) $name;

        return $this;
    }
}