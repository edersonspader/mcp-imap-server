<?php

declare(strict_types=1);

namespace App\Imap;

use App\Exception\MailboxNotFoundException;
use App\Exception\MessageNotFoundException;

interface ImapConnectionInterface
{
	public function disconnect(): void;

	/** @return list<array{name: string, path: string, children: int}> */
	public function listMailboxes(): array;

	/** @return array{total: int, unseen: int, recent: int} */
	public function countMessages(string $mailbox): array;

	public function createMailbox(string $name): void;

	public function deleteMailbox(string $name): void;

	/**
	 * @param list<string>|null $fields
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @throws MailboxNotFoundException
	 */
	public function listMessages(string $mailbox, int $limit = 20, int $offset = 0, array|null $fields = null): array;

	/**
	 * @param list<string>|null $flags
	 * @param list<string>|null $fields
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @throws MailboxNotFoundException
	 */
	public function searchMessages(
		string $mailbox,
		string|null $from = null,
		string|null $to = null,
		string|null $subject = null,
		string|null $since = null,
		string|null $before = null,
		string|null $body = null,
		array|null $flags = null,
		int $limit = 20,
		int $offset = 0,
		array|null $fields = null,
	): array;

	/**
	 * @return array{uid: int, from: string, to: string, cc: string, subject: string, date: string, body: string, has_attachments: bool}
	 *
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function readMessage(
		int $uid,
		string $mailbox = 'INBOX',
		string $format = 'text',
		int $maxLength = 4000,
	): array;

	/**
	 * @return array{uid: int, message_id: string, from: string, to: string, cc: string, reply_to: string, subject: string, date: string, in_reply_to: string, seen: bool, flagged: bool, answered: bool}
	 *
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function getMessageHeaders(int $uid, string $mailbox = 'INBOX'): array;

	/**
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function moveMessage(int $uid, string $fromMailbox, string $toMailbox): void;

	/**
	 * @param list<int> $uids
	 *
	 * @return array{moved: list<int>, failed: list<int>}
	 *
	 * @throws MailboxNotFoundException
	 */
	public function batchMoveMessages(array $uids, string $fromMailbox, string $toMailbox): array;

	/**
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function copyMessage(int $uid, string $fromMailbox, string $toMailbox): void;

	/**
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function deleteMessage(int $uid, string $mailbox = 'INBOX'): void;

	/**
	 * @param string $flag One of: Seen, Flagged, Answered, Draft, Deleted
	 *
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function setFlag(int $uid, string $flag, string $mailbox = 'INBOX'): void;

	/**
	 * @param string $flag One of: Seen, Flagged, Answered, Draft, Deleted
	 *
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function clearFlag(int $uid, string $flag, string $mailbox = 'INBOX'): void;

	/**
	 * @param list<int> $uids
	 *
	 * @throws MailboxNotFoundException
	 */
	public function batchSetFlag(array $uids, string $flag, bool $set, string $mailbox): bool;

	/**
	 * @param list<int> $uids
	 *
	 * @throws MailboxNotFoundException
	 */
	public function batchDeleteMessages(array $uids, string $mailbox): bool;

	/**
	 * @return list<array{filename: string, size: int, mime_type: string, saved_path: string}>
	 *
	 * @throws MailboxNotFoundException
	 * @throws MessageNotFoundException
	 */
	public function fetchAttachments(int $uid, string $mailbox = 'INBOX', string $savePath = 'var/attachments'): array;
}
