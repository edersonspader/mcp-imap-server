<?php

declare(strict_types=1);

namespace App\Tests\Unit\Smtp;

use App\Smtp\SmtpConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmtpConfig::class)]
final class SmtpConfigTest extends TestCase
{
	#[Test]
	public function it_creates_with_all_parameters(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 465,
			user: 'user@example.com',
			password: 'secret',
			from: 'sender@example.com',
			encryption: 'ssl',
			allowedFrom: ['alias@example.com'],
		);

		self::assertSame('smtp.example.com', $config->host);
		self::assertSame(465, $config->port);
		self::assertSame('user@example.com', $config->user);
		self::assertSame('secret', $config->password);
		self::assertSame('sender@example.com', $config->from);
		self::assertSame('ssl', $config->encryption);
	}

	#[Test]
	public function it_uses_default_values(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'sender@example.com',
		);

		self::assertSame('tls', $config->encryption);
	}

	#[Test]
	public function it_includes_from_in_allowed_from_automatically(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'sender@example.com',
		);

		self::assertContains('sender@example.com', $config->allowedFrom);
	}

	#[Test]
	public function it_does_not_duplicate_from_in_allowed_from(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'sender@example.com',
			allowedFrom: ['sender@example.com', 'alias@example.com'],
		);

		$count = array_count_values($config->allowedFrom)['sender@example.com'];

		self::assertSame(1, $count);
		self::assertCount(2, $config->allowedFrom);
	}

	#[Test]
	public function it_normalizes_allowed_from_to_lowercase(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'Sender@Example.com',
			allowedFrom: ['ALIAS@EXAMPLE.COM'],
		);

		self::assertContains('sender@example.com', $config->allowedFrom);
		self::assertContains('alias@example.com', $config->allowedFrom);
	}

	#[Test]
	public function it_creates_from_env(): void
	{
		$_ENV['SMTP_HOST'] = 'smtp.test.com';
		$_ENV['SMTP_PORT'] = '465';
		$_ENV['SMTP_USER'] = 'test@test.com';
		$_ENV['SMTP_PASSWORD'] = 'pass123';
		$_ENV['SMTP_FROM'] = 'from@test.com';
		$_ENV['SMTP_ENCRYPTION'] = 'ssl';
		$_ENV['SMTP_ALLOWED_FROM'] = 'alias1@test.com, alias2@test.com';

		try {
			$config = SmtpConfig::fromEnv();

			self::assertSame('smtp.test.com', $config->host);
			self::assertSame(465, $config->port);
			self::assertSame('test@test.com', $config->user);
			self::assertSame('pass123', $config->password);
			self::assertSame('from@test.com', $config->from);
			self::assertSame('ssl', $config->encryption);
			self::assertContains('alias1@test.com', $config->allowedFrom);
			self::assertContains('alias2@test.com', $config->allowedFrom);
			self::assertContains('from@test.com', $config->allowedFrom);
		} finally {
			unset(
				$_ENV['SMTP_HOST'],
				$_ENV['SMTP_PORT'],
				$_ENV['SMTP_USER'],
				$_ENV['SMTP_PASSWORD'],
				$_ENV['SMTP_FROM'],
				$_ENV['SMTP_ENCRYPTION'],
				$_ENV['SMTP_ALLOWED_FROM'],
			);
		}
	}

	#[Test]
	public function it_uses_env_defaults(): void
	{
		$_ENV['SMTP_HOST'] = 'smtp.test.com';
		$_ENV['SMTP_USER'] = 'test@test.com';
		$_ENV['SMTP_PASSWORD'] = 'pass123';
		$_ENV['SMTP_FROM'] = 'from@test.com';

		try {
			$config = SmtpConfig::fromEnv();

			self::assertSame(587, $config->port);
			self::assertSame('tls', $config->encryption);
		} finally {
			unset(
				$_ENV['SMTP_HOST'],
				$_ENV['SMTP_USER'],
				$_ENV['SMTP_PASSWORD'],
				$_ENV['SMTP_FROM'],
			);
		}
	}

	#[Test]
	public function it_throws_on_missing_env_variables(): void
	{
		unset(
			$_ENV['SMTP_HOST'],
			$_ENV['SMTP_USER'],
			$_ENV['SMTP_PASSWORD'],
			$_ENV['SMTP_FROM'],
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Missing required SMTP environment variables');

		SmtpConfig::fromEnv();
	}

	#[Test]
	public function it_resolves_from_with_explicit_allowed_address(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'default@example.com',
			allowedFrom: ['alias@example.com'],
		);

		$result = $config->resolveFrom('alias@example.com');

		self::assertSame('alias@example.com', $result);
	}

	#[Test]
	public function it_resolves_from_with_original_to_when_requested_is_null(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'default@example.com',
			allowedFrom: ['alias@example.com'],
		);

		$result = $config->resolveFrom(null, 'alias@example.com');

		self::assertSame('alias@example.com', $result);
	}

	#[Test]
	public function it_falls_back_to_default_from_when_not_allowed(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'default@example.com',
		);

		$result = $config->resolveFrom('unknown@other.com');

		self::assertSame('default@example.com', $result);
	}

	#[Test]
	public function it_falls_back_to_default_from_when_both_null(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'default@example.com',
		);

		$result = $config->resolveFrom(null, null);

		self::assertSame('default@example.com', $result);
	}

	#[Test]
	public function it_resolves_from_case_insensitively(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'default@example.com',
			allowedFrom: ['Alias@Example.COM'],
		);

		$result = $config->resolveFrom('alias@example.com');

		self::assertSame('alias@example.com', $result);
	}

	#[Test]
	public function it_checks_is_allowed(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'sender@example.com',
			allowedFrom: ['alias@example.com'],
		);

		self::assertTrue($config->isAllowed('sender@example.com'));
		self::assertTrue($config->isAllowed('alias@example.com'));
		self::assertFalse($config->isAllowed('stranger@other.com'));
	}

	#[Test]
	public function it_builds_smtp_dsn(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'p@ss',
			from: 'sender@example.com',
			encryption: 'tls',
		);

		$dsn = $config->buildDsn();

		self::assertSame('smtp://user%40example.com:p%40ss@smtp.example.com:587', $dsn);
	}

	#[Test]
	public function it_builds_smtps_dsn_for_ssl(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 465,
			user: 'user@example.com',
			password: 'secret',
			from: 'sender@example.com',
			encryption: 'ssl',
		);

		$dsn = $config->buildDsn();

		self::assertStringStartsWith('smtps://', $dsn);
	}

	#[Test]
	public function it_prioritizes_explicit_from_over_original_to(): void
	{
		$config = new SmtpConfig(
			host: 'smtp.example.com',
			port: 587,
			user: 'user@example.com',
			password: 'secret',
			from: 'default@example.com',
			allowedFrom: ['alias@example.com', 'other@example.com'],
		);

		$result = $config->resolveFrom('alias@example.com', 'other@example.com');

		self::assertSame('alias@example.com', $result);
	}
}
