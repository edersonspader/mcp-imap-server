<?php

declare(strict_types=1);

namespace App\Imap;

readonly class ImapConfig
{
	public function __construct(
		public string $host,
		public int $port,
		public string $user,
		public string $password,
		public string $encryption = 'ssl',
		public bool $validateCert = true,
	) {}

	public static function fromEnv(): self
	{
		$host = self::envString('IMAP_HOST');
		$user = self::envString('IMAP_USER');
		$password = self::envString('IMAP_PASSWORD');

		if ($host === '' || $user === '' || $password === '') {
			throw new \InvalidArgumentException(
				'Missing required IMAP environment variables: IMAP_HOST, IMAP_USER, IMAP_PASSWORD'
			);
		}

		return new self(
			host: $host,
			port: (int) self::envString('IMAP_PORT', '993'),
			user: $user,
			password: $password,
			encryption: self::envString('IMAP_ENCRYPTION', 'ssl'),
			validateCert: self::envString('IMAP_VALIDATE_CERT', 'true') === 'true',
		);
	}

	private static function envString(string $key, string $default = ''): string
	{
		$value = $_ENV[$key] ?? null;

		return \is_string($value) ? $value : $default;
	}
}
