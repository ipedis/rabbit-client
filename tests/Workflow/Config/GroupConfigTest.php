<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow\Config;

use Ipedis\Rabbit\Workflow\Config\GroupConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GroupConfigTest extends TestCase
{
    #[Test]
    public function defaults(): void
    {
        $groupConfig = new GroupConfig();

        $this->assertFalse($groupConfig->hasToRetry());
        $this->assertSame(3, $groupConfig->getMaxRetry());
    }

    #[Test]
    public function custom_values(): void
    {
        $groupConfig = new GroupConfig(retry: true, maxRetry: 7);

        $this->assertTrue($groupConfig->hasToRetry());
        $this->assertSame(7, $groupConfig->getMaxRetry());
    }
}
