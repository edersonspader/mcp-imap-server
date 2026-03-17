<?php

declare(strict_types=1);

namespace App\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Imap\ImapConnectionFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

class MailboxTool
{
	public function __construct(
		private readonly ImapConnectionFactory $factory,
	) {}

	/** @return array{mailboxes: list<array{name: string, path: string, children: int}>}|array{error: true, message: string} */
	#[McpTool(name: 'list_mailboxes', description: 'List all mailboxes/folders on the IMAP server', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function listMailboxes(): array
	{
		$connection = null;

		try {
			$connection = $this->factory->create();

			return ['mailboxes' => $connection->listMailboxes()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{total: int, unseen: int, recent: int}|array{error: true, message: string} */
	#[McpTool(name: 'count_messages', description: 'Count messages in a mailbox (total, unseen, recent)', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function countMessages(string $mailbox = 'INBOX'): array
	{
		$connection = null;

		try {
			$connection = $this->factory->create();

			return $connection->countMessages($mailbox);
		} catch (MailboxNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{success?: bool, error?: bool, message: string} */
	#[McpTool(name: 'create_mailbox', description: 'Create a new mailbox/folder', annotations: new ToolAnnotations(destructiveHint: false))]
	public function createMailbox(string $name): array
	{
		$connection = null;

		try {
			$connection = $this->factory->create();
			$connection->createMailbox($name);

			return ['success' => true, 'message' => "Mailbox '{$name}' created"];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} catch (\Throwable $e) {
			return ['error' => true, 'message' => "Failed to create mailbox '{$name}': " . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{success?: bool, error?: bool, message: string} */
	#[McpTool(name: 'delete_mailbox', description: 'Delete a mailbox/folder and all its contents permanently. REQUIRES USER CONFIRMATION.', annotations: new ToolAnnotations(destructiveHint: true, title: 'Delete Mailbox (destructive)'))]
	public function deleteMailbox(string $name): array
	{
		$connection = null;

		try {
			$connection = $this->factory->create();
			$connection->deleteMailbox($name);

			return ['success' => true, 'message' => "Mailbox '{$name}' deleted"];
		} catch (MailboxNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} catch (\Throwable $e) {
			return ['error' => true, 'message' => "Failed to delete mailbox '{$name}': " . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}
}
