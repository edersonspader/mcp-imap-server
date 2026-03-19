<?php

declare(strict_types=1);

namespace App\Smtp;

readonly class SmtpConfig
{
	/** @var list<string> */
	public array $allowedFrom;

	/**
	 * @param list<string> $allowedFrom
	 */
	public function __construct(
		public string $host,
		public int $port,
		public string $user,
		public string $password,
		public string $from,
		public string $encryption = 'tls',
		array $allowedFrom = [],
	) {
		$normalized = array_map(mb_strtolower(...), $allowedFrom);

		if (!\in_array(mb_strtolower($this->from), $normalized, true)) {
			$normalized[] = mb_strtolower($this->from);
		}

		$this->allowedFrom = array_values(array_unique($normalized));
	}

	public static function fromEnv(): self
	{
		$host = self::envString('SMTP_HOST');
		$user = self::envString('SMTP_USER');
		$password = self::envString('SMTP_PASSWORD');
		$from = self::envString('SMTP_FROM');

		if ($host === '' || $user === '' || $password === '' || $from === '') {
			throw new \InvalidArgumentException(
				'Missing required SMTP environment variables: SMTP_HOST, SMTP_USER, SMTP_PASSWORD, SMTP_FROM'
			);
		}

		$allowedRaw = self::envString('SMTP_ALLOWED_FROM');
		$allowedFrom = $allowedRaw !== ''
			? array_map(trim(...), explode(',', $allowedRaw))
			: [];

		return new self(
			host: $host,
			port: (int) self::envString('SMTP_PORT', '587'),
			user: $user,
			password: $password,
			from: $from,
			encryption: self::envString('SMTP_ENCRYPTION', 'tls'),
			allowedFrom: $allowedFrom,
		);
	}

	/**
	 * Resolve the sender address based on priority:
	 * 1. Explicit from (if in allowed list)
	 * 2. Original To address from the received email (if in allowed list — reply/forward identity match)
	 * 3. Default from
	 */
	public function resolveFrom(string|null $requestedFrom, string|null $originalTo = null): string
	{
		if ($requestedFrom !== null && $this->isAllowed($requestedFrom)) {
			return $requestedFrom;
		}

		if ($originalTo !== null && $this->isAllowed($originalTo)) {
			return $originalTo;
		}

		return $this->from;
	}

	public function isAllowed(string $address): bool
	{
		return \in_array(mb_strtolower($address), $this->allowedFrom, true);
	}

	public function buildDsn(): string
	{
		$scheme = match ($this->encryption) {
			'ssl', 'smtps' => 'smtps',
			default => 'smtp',
		};

		$user = urlencode($this->user);
		$password = urlencode($this->password);

		return "{$scheme}://{$user}:{$password}@{$this->host}:{$this->port}";
	}

	private static function envString(string $key, string $default = ''): string
	{
		$value = $_ENV[$key] ?? null;

		return \is_string($value) ? $value : $default;
	}
}
