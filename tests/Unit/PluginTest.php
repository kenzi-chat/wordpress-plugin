<?php

declare(strict_types=1);

namespace Kenzi\Chat\Tests\Unit;

use Kenzi\Chat\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testInstanceReturnsSingleton(): void
    {
        $a = Plugin::instance();
        $b = Plugin::instance();

        $this->assertSame($a, $b);
    }

    public function testInstanceReturnsPluginClass(): void
    {
        $this->assertInstanceOf(Plugin::class, Plugin::instance());
    }
}
