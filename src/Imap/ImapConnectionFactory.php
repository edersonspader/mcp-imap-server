<?php

declare(strict_types=1);

namespace App\Imap;

use App\Exception\ImapAuthException;
use App\Exception\ImapConnectionException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class ImapConnectionFactory
{
	public function __construct(
		private readonly ImapConfig $config,
	) {}

	/** @throws ImapConnectionException */
	public function create(): ImapConnection
	{
		$config = Config::make([
			'accounts' => [
				'default' => [
					'host' => $this->config->host,
					'port' => $this->config->port,
					'encryption' => $this->config->encryption,
					'validate_cert' => $this->config->validateCert,
					'username' => $this->config->user,
					'password' => $this->config->password,
					'protocol' => 'imap',
					'rfc' => 'BODY',
				],
			],
		]);

		$client = new Client($config->getClientConfig('default'));

		try {
			$client->connect();
		} catch (AuthFailedException $e) {
			throw new ImapAuthException(
				"IMAP authentication failed for {$this->config->user}@{$this->config->host}",
				previous: $e,
			);
		} catch (ConnectionFailedException $e) {
			throw new ImapConnectionException(
				"Failed to connect to IMAP server {$this->config->host}:{$this->config->port}",
				previous: $e,
			);
		}

		return new ImapConnection($client);
	}
}
