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

class MessageTool
{
	public function __construct(
		private readonly ImapConnectionFactory $factory,
	) {}

	/**
	 * @param list<string>|null $fields
	 *
	 * @return array{messages: list<array<string, mixed>>}|array{error: true, message: string}
	 */
	#[McpTool(name: 'list_messages', description: 'List messages in a mailbox with pagination', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function listMessages(
		string $mailbox = 'INBOX',
		#[Schema(description: 'Maximum number of messages to return')]
		int $limit = 20,
		#[Schema(description: 'Number of messages to skip')]
		int $offset = 0,
		#[Schema(description: 'Fields to return (uid is always included). Available: from, to, subject, date, seen. Omit for all fields.', items: ['type' => 'string'])]
		array|null $fields = null,
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();

			return ['messages' => $connection->listMessages($mailbox, $limit, $offset, $fields)];
		} catch (MailboxNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/**
	 * @param list<string>|null $flags
	 * @param list<string>|null $fields
	 *
	 * @return array{messages: list<array<string, mixed>>}|array{error: true, message: string}
	 */
	#[McpTool(name: 'search_messages', description: 'Search messages by criteria (from, to, subject, date range, body, flags)', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function searchMessages(
		string $mailbox = 'INBOX',
		#[Schema(description: 'Filter by sender email or name')]
		string|null $from = null,
		#[Schema(description: 'Filter by recipient email or name')]
		string|null $to = null,
		#[Schema(description: 'Filter by subject keyword')]
		string|null $subject = null,
		#[Schema(description: 'Messages since date (YYYY-MM-DD)')]
		string|null $since = null,
		#[Schema(description: 'Messages before date (YYYY-MM-DD)')]
		string|null $before = null,
		#[Schema(description: 'Search in message body')]
		string|null $body = null,
		#[Schema(description: 'IMAP flags to filter by. Available: SEEN, UNSEEN, FLAGGED, UNFLAGGED, ANSWERED, UNANSWERED, DELETED, UNDELETED, RECENT, OLD, NEW', items: ['type' => 'string'])]
		array|null $flags = null,
		int $limit = 20,
		int $offset = 0,
		#[Schema(description: 'Fields to return (uid is always included). Available: from, to, subject, date, seen. Omit for all fields.', items: ['type' => 'string'])]
		array|null $fields = null,
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();

			return ['messages' => $connection->searchMessages(
				mailbox: $mailbox,
				from: $from,
				to: $to,
				subject: $subject,
				since: $since,
				before: $before,
				body: $body,
				flags: $flags,
				limit: $limit,
				offset: $offset,
				fields: $fields,
			)];
		} catch (MailboxNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{uid: int, from: string, to: string, cc: string, subject: string, date: string, body: string, has_attachments: bool}|array{error: true, message: string} */
	#[McpTool(name: 'read_message', description: 'Read full message content (headers + body) with configurable format and length', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function readMessage(
		#[Schema(description: 'Message UID')]
		int $uid,
		string $mailbox = 'INBOX',
		#[Schema(description: 'Body format: text, html, or both', pattern: '^(text|html|both)$')]
		string $format = 'text',
		#[Schema(description: 'Maximum body length in characters (0 = unlimited)')]
		int $max_length = 4000,
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();

			return $connection->readMessage($uid, $mailbox, $format, $max_length);
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}

	/** @return array{uid: int, message_id: string, from: string, to: string, cc: string, reply_to: string, subject: string, date: string, in_reply_to: string, seen: bool, flagged: bool, answered: bool}|array{error: true, message: string} */
	#[McpTool(name: 'get_message_headers', description: 'Get message headers without downloading the body', annotations: new ToolAnnotations(readOnlyHint: true))]
	public function getMessageHeaders(
		#[Schema(description: 'Message UID')]
		int $uid,
		string $mailbox = 'INBOX',
	): array {
		$connection = null;

		try {
			$connection = $this->factory->create();

			return $connection->getMessageHeaders($uid, $mailbox);
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}
}
