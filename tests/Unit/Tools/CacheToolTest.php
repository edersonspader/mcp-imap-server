<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Tools\CacheTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(CacheTool::class)]
final class CacheToolTest extends TestCase
{
	#[Test]
	public function it_clears_cache(): void
	{
		$cache = $this->createMock(CacheInterface::class);
		$cache->expects(self::once())->method('clear')->willReturn(true);

		$tool = new CacheTool($cache);
		$result = $tool->clearCache();

		self::assertTrue($result['success']);
		self::assertSame('IMAP data cache cleared', $result['message']);
	}
}
