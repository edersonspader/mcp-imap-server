<?php

declare(strict_types=1);

namespace App\Tests\Unit\Imap;

use App\Imap\ImapConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapConfig::class)]
final class ImapConfigTest extends TestCase
{
	#[Test]
	public function it_creates_with_all_parameters(): void
	{
		$config = new ImapConfig(
			host: 'imap.example.com',
			port: 993,
			user: 'user@example.com',
			password: 'secret',
			encryption: 'ssl',
			validateCert: true,
		);

		self::assertSame('imap.example.com', $config->host);
		self::assertSame(993, $config->port);
		self::assertSame('user@example.com', $config->user);
		self::assertSame('secret', $config->password);
		self::assertSame('ssl', $config->encryption);
		self::assertTrue($config->validateCert);
	}

	#[Test]
	public function it_uses_default_values(): void
	{
		$config = new ImapConfig(
			host: 'imap.example.com',
			port: 993,
			user: 'user@example.com',
			password: 'secret',
		);

		self::assertSame('ssl', $config->encryption);
		self::assertTrue($config->validateCert);
	}

	#[Test]
	public function it_creates_from_env(): void
	{
		$_ENV['IMAP_HOST'] = 'imap.test.com';
		$_ENV['IMAP_PORT'] = '143';
		$_ENV['IMAP_USER'] = 'test@test.com';
		$_ENV['IMAP_PASSWORD'] = 'pass123';
		$_ENV['IMAP_ENCRYPTION'] = 'tls';
		$_ENV['IMAP_VALIDATE_CERT'] = 'false';

		try {
			$config = ImapConfig::fromEnv();

			self::assertSame('imap.test.com', $config->host);
			self::assertSame(143, $config->port);
			self::assertSame('test@test.com', $config->user);
			self::assertSame('pass123', $config->password);
			self::assertSame('tls', $config->encryption);
			self::assertFalse($config->validateCert);
		} finally {
			unset($_ENV['IMAP_HOST'], $_ENV['IMAP_PORT'], $_ENV['IMAP_USER'], $_ENV['IMAP_PASSWORD'], $_ENV['IMAP_ENCRYPTION'], $_ENV['IMAP_VALIDATE_CERT']);
		}
	}

	#[Test]
	public function it_uses_defaults_from_env(): void
	{
		$_ENV['IMAP_HOST'] = 'imap.test.com';
		$_ENV['IMAP_USER'] = 'test@test.com';
		$_ENV['IMAP_PASSWORD'] = 'pass123';

		try {
			$config = ImapConfig::fromEnv();

			self::assertSame(993, $config->port);
			self::assertSame('ssl', $config->encryption);
			self::assertTrue($config->validateCert);
		} finally {
			unset($_ENV['IMAP_HOST'], $_ENV['IMAP_USER'], $_ENV['IMAP_PASSWORD']);
		}
	}

	#[Test]
	public function it_throws_on_missing_host(): void
	{
		$_ENV['IMAP_USER'] = 'test@test.com';
		$_ENV['IMAP_PASSWORD'] = 'pass123';

		try {
			$this->expectException(\InvalidArgumentException::class);
			$this->expectExceptionMessage('Missing required IMAP environment variables');

			ImapConfig::fromEnv();
		} finally {
			unset($_ENV['IMAP_HOST'], $_ENV['IMAP_USER'], $_ENV['IMAP_PASSWORD']);
		}
	}

	#[Test]
	public function it_throws_on_missing_user(): void
	{
		$_ENV['IMAP_HOST'] = 'imap.test.com';
		$_ENV['IMAP_PASSWORD'] = 'pass123';

		try {
			$this->expectException(\InvalidArgumentException::class);

			ImapConfig::fromEnv();
		} finally {
			unset($_ENV['IMAP_HOST'], $_ENV['IMAP_USER'], $_ENV['IMAP_PASSWORD']);
		}
	}

	#[Test]
	public function it_throws_on_missing_password(): void
	{
		$_ENV['IMAP_HOST'] = 'imap.test.com';
		$_ENV['IMAP_USER'] = 'test@test.com';

		try {
			$this->expectException(\InvalidArgumentException::class);

			ImapConfig::fromEnv();
		} finally {
			unset($_ENV['IMAP_HOST'], $_ENV['IMAP_USER'], $_ENV['IMAP_PASSWORD']);
		}
	}
}
