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

class MessageActionTool
{
	public function __construct(
		private readonly ImapConnectionFactory $factory,
	) {}

	/** @return array{success?: bool, error?: bool, message: string} */
	#[McpTool(name: 'move_message', description: 'Move a message from one mailbox to another. Removes from source. REQUIRES USER CONFIRMATION.', annotations: new ToolAnnotations(destructiveHint: true))]
	public function moveMessage(
		#[Schema(description: 'Message UID')]
		int $uid,
		#[Schema(description: 'Source mailbox')]
		string $from_mailbox,
		#[Schema(description: 'Destination mailbox')]
		string $to_mailbox,
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();
			$connection->moveMessage($uid, $from_mailbox, $to_mailbox);

			return ['success' => true, 'message' => "Message {$uid} moved from '{$from_mailbox}' to '{$to_mailbox}'"];
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{success?: bool, error?: bool, message: string} */
	#[McpTool(name: 'copy_message', description: 'Copy a message to another mailbox', annotations: new ToolAnnotations(destructiveHint: false))]
	public function copyMessage(
		#[Schema(description: 'Message UID')]
		int $uid,
		#[Schema(description: 'Source mailbox')]
		string $from_mailbox,
		#[Schema(description: 'Destination mailbox')]
		string $to_mailbox,
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();
			$connection->copyMessage($uid, $from_mailbox, $to_mailbox);

			return ['success' => true, 'message' => "Message {$uid} copied from '{$from_mailbox}' to '{$to_mailbox}'"];
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{success?: bool, error?: bool, message: string} */
	#[McpTool(name: 'delete_message', description: 'Delete a message permanently. This action is irreversible. REQUIRES USER CONFIRMATION.', annotations: new ToolAnnotations(destructiveHint: true, title: 'Delete Message (destructive)'))]
	public function deleteMessage(
		#[Schema(description: 'Message UID')]
		int $uid,
		string $mailbox = 'INBOX',
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();
			$connection->deleteMessage($uid, $mailbox);

			return ['success' => true, 'message' => "Message {$uid} deleted from '{$mailbox}'"];
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{success?: bool, error?: bool, message: string} */
	#[McpTool(name: 'flag_message', description: 'Set or clear a flag on a message (Seen, Flagged, Answered, Draft)', annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: true))]
	public function flagMessage(
		#[Schema(description: 'Message UID')]
		int $uid,
		#[Schema(description: 'Flag name', pattern: '^(Seen|Flagged|Answered|Draft)$')]
		string $flag,
		#[Schema(description: 'true to set, false to clear')]
		bool $set = true,
		string $mailbox = 'INBOX',
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();

			if ($set) {
				$connection->setFlag($uid, $flag, $mailbox);
			} else {
				$connection->clearFlag($uid, $flag, $mailbox);
			}

			$action = $set ? 'set' : 'cleared';

			return ['success' => true, 'message' => "Flag '{$flag}' {$action} on message {$uid}"];
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}
}
