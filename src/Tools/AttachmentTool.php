<?php

declare(strict_types=1);

namespace App\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Exception\MessageNotFoundException;
use App\Imap\ImapConnectionFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

class AttachmentTool
{
	public function __construct(
		private readonly ImapConnectionFactory $factory,
	) {}

	/** @return array{attachments: list<array{filename: string, size: int, mime_type: string, saved_path: string}>}|array{error: true, message: string} */
	#[McpTool(name: 'get_attachments', description: 'Save message attachments to disk and return file metadata', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false))]
	public function getAttachments(
		#[Schema(description: 'Message UID')]
		int $uid,
		string $mailbox = 'INBOX',
		#[Schema(description: 'Directory to save attachments')]
		string $save_path = 'var/attachments',
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();

			return ['attachments' => $connection->fetchAttachments($uid, $mailbox, $save_path)];
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}
}
