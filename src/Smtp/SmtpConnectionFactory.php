<?php

declare(strict_types=1);

namespace App\Smtp;

use App\Exception\SmtpConnectionException;
use Symfony\Component\Mailer\Transport;

class SmtpConnectionFactory
{
	public function __construct(
		private readonly SmtpConfig $config,
	) {}

	public function getConfig(): SmtpConfig
	{
		return $this->config;
	}

	/** @throws SmtpConnectionException */
	public function create(): SmtpConnectionInterface
	{
		try {
			$transport = Transport::fromDsn($this->config->buildDsn());
		} catch (\Exception $e) {
			throw new SmtpConnectionException(
				"Failed to create SMTP transport for {$this->config->host}:{$this->config->port}: {$e->getMessage()}",
				previous: $e,
			);
		}

		return new SmtpConnection($transport);
	}
}
