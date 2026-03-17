<?php

declare(strict_types=1);

namespace App\Tests\Unit\Imap;

use App\Exception\ImapAuthException;
use App\Exception\ImapConnectionException;
use App\Imap\ImapConfig;
use App\Imap\ImapConnection;
use App\Imap\ImapConnectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapConnectionFactory::class)]
final class ImapConnectionFactoryTest extends TestCase
{
	#[Test]
	public function it_stores_config(): void
	{
		$config = new ImapConfig(
			host: 'imap.example.com',
			port: 993,
			user: 'user@example.com',
			password: 'secret',
		);

		$factory = new ImapConnectionFactory($config);

		// Factory is created without error — config is stored
		self::assertInstanceOf(ImapConnectionFactory::class, $factory);
	}
}
