<?php

declare(strict_types=1);

namespace App\Resources;

use App\Imap\ImapConnectionFactory;
use Mcp\Capability\Attribute\McpResourceTemplate;

class MessageResource
{
	public function __construct(
		private readonly ImapConnectionFactory $factory,
	) {}

	/** @return array{uid: int, from: string, to: string, cc: string, subject: string, date: string, body: string, has_attachments: bool} */
	#[McpResourceTemplate(
		uriTemplate: 'message://{mailbox}/{uid}',
		name: 'message_by_uid',
		description: 'Full message content by mailbox and UID',
		mimeType: 'application/json',
	)]
	public function getMessage(string $mailbox, string $uid): array
	{
		$connection = $this->factory->create();

		try {
			return $connection->readMessage(
				uid: (int) $uid,
				mailbox: $mailbox,
				format: 'text',
				maxLength: 8000,
			);
		} finally {
			$connection->disconnect();
		}
	}
}
