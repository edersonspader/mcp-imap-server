<?php

declare(strict_types=1);

namespace App\Resources;

use App\Imap\ImapConnectionFactory;
use Mcp\Capability\Attribute\McpResource;

class MailboxStatusResource
{
	public function __construct(
		private readonly ImapConnectionFactory $factory,
	) {}

	/** @return list<array{name: string, path: string, total: int, unseen: int, recent: int}> */
	#[McpResource(
		uri: 'mailbox://status',
		name: 'mailbox_status',
		description: 'Status of all mailboxes with message counts',
		mimeType: 'application/json',
	)]
	public function getStatus(): array
	{
		$connection = $this->factory->create();

		try {
			$mailboxes = $connection->listMailboxes();
			$result = [];

			foreach ($mailboxes as $mailbox) {
				$counts = $connection->countMessages($mailbox['path']);
				$result[] = [
					'name' => $mailbox['name'],
					'path' => $mailbox['path'],
					'total' => $counts['total'],
					'unseen' => $counts['unseen'],
					'recent' => $counts['recent'],
				];
			}

			return $result;
		} finally {
			$connection->disconnect();
		}
	}
}
