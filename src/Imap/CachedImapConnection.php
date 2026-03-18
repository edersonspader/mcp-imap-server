<?php

declare(strict_types=1);

namespace App\Imap;

use Psr\SimpleCache\CacheInterface;

class CachedImapConnection implements ImapConnectionInterface
{
	private const int TTL_MAILBOXES = 900;
	private const int TTL_VOLATILE = 300;

	public function __construct(
		private readonly ImapConnectionInterface $inner,
		private readonly CacheInterface $cache,
	) {}

	public function disconnect(): void
	{
		$this->inner->disconnect();
	}

	/** @return list<array{name: string, path: string, children: int}> */
	public function listMailboxes(): array
	{
		$key = 'mailboxes';

		/** @var list<array{name: string, path: string, children: int}>|null $cached */
		$cached = $this->cache->get($key);

		if ($cached !== null) {
			return $cached;
		}

		$result = $this->inner->listMailboxes();
		$this->cache->set($key, $result, self::TTL_MAILBOXES);

		return $result;
	}

	/** @return array{total: int, unseen: int, recent: int} */
	public function countMessages(string $mailbox): array
	{
		$key = $this->mailboxKey('count', $mailbox);

		/** @var array{total: int, unseen: int, recent: int}|null $cached */
		$cached = $this->cache->get($key);

		if ($cached !== null) {
			return $cached;
		}

		$result = $this->inner->countMessages($mailbox);
		$this->cache->set($key, $result, self::TTL_VOLATILE);

		return $result;
	}

	public function createMailbox(string $name): void
	{
		$this->inner->createMailbox($name);
		$this->cache->delete('mailboxes');
	}

	public function deleteMailbox(string $name): void
	{
		$this->inner->deleteMailbox($name);
		$this->cache->delete('mailboxes');
		$this->invalidateMailbox($name);
	}

	/**
	 * @param list<string>|null $fields
	 *
	 * @return list<array<string, mixed>>
	 */
	public function listMessages(string $mailbox, int $limit = 20, int $offset = 0, array|null $fields = null): array
	{
		$version = $this->getMailboxVersion($mailbox);
		$fieldsHash = $fields !== null ? md5(implode(',', $fields)) : 'all';
		$key = "list:{$version}:{$mailbox}:{$limit}:{$offset}:{$fieldsHash}";

		/** @var list<array<string, mixed>>|null $cached */
		$cached = $this->cache->get($key);

		if ($cached !== null) {
			return $cached;
		}

		$result = $this->inner->listMessages($mailbox, $limit, $offset, $fields);
		$this->cache->set($key, $result, self::TTL_VOLATILE);

		return $result;
	}

	/**
	 * @param list<string>|null $fields
	 *
	 * @return list<array<string, mixed>>
	 */
	public function searchMessages(
		string $mailbox,
		string|null $from = null,
		string|null $to = null,
		string|null $subject = null,
		string|null $since = null,
		string|null $before = null,
		string|null $body = null,
		bool|null $unseen = null,
		bool|null $flagged = null,
		int $limit = 20,
		int $offset = 0,
		array|null $fields = null,
	): array {
		$version = $this->getMailboxVersion($mailbox);
		$paramsHash = md5(serialize([
			$mailbox,
			$from,
			$to,
			$subject,
			$since,
			$before,
			$body,
			$unseen,
			$flagged,
			$limit,
			$offset,
			$fields,
		]));
		$key = "search:{$version}:{$paramsHash}";

		/** @var list<array<string, mixed>>|null $cached */
		$cached = $this->cache->get($key);

		if ($cached !== null) {
			return $cached;
		}

		$result = $this->inner->searchMessages(
			$mailbox,
			$from,
			$to,
			$subject,
			$since,
			$before,
			$body,
			$unseen,
			$flagged,
			$limit,
			$offset,
			$fields,
		);
		$this->cache->set($key, $result, self::TTL_VOLATILE);

		return $result;
	}

	/**
	 * @return array{uid: int, from: string, to: string, cc: string, subject: string, date: string, body: string, has_attachments: bool}
	 */
	public function readMessage(
		int $uid,
		string $mailbox = 'INBOX',
		string $format = 'text',
		int $maxLength = 4000,
	): array {
		$key = "msg:{$mailbox}:{$uid}:{$format}:{$maxLength}";

		/** @var array{uid: int, from: string, to: string, cc: string, subject: string, date: string, body: string, has_attachments: bool}|null $cached */
		$cached = $this->cache->get($key);

		if ($cached !== null) {
			return $cached;
		}

		$result = $this->inner->readMessage($uid, $mailbox, $format, $maxLength);
		$this->cache->set($key, $result);

		return $result;
	}

	/**
	 * @return array{uid: int, message_id: string, from: string, to: string, cc: string, reply_to: string, subject: string, date: string, in_reply_to: string, seen: bool, flagged: bool, answered: bool}
	 */
	public function getMessageHeaders(int $uid, string $mailbox = 'INBOX'): array
	{
		$key = "hdr:{$mailbox}:{$uid}";

		/** @var array{uid: int, message_id: string, from: string, to: string, cc: string, reply_to: string, subject: string, date: string, in_reply_to: string, seen: bool, flagged: bool, answered: bool}|null $cached */
		$cached = $this->cache->get($key);

		if ($cached !== null) {
			return $cached;
		}

		$result = $this->inner->getMessageHeaders($uid, $mailbox);
		$this->cache->set($key, $result);

		return $result;
	}

	public function moveMessage(int $uid, string $fromMailbox, string $toMailbox): void
	{
		$this->inner->moveMessage($uid, $fromMailbox, $toMailbox);
		$this->invalidateMailbox($fromMailbox);
		$this->invalidateMailbox($toMailbox);
	}

	/**
	 * @param list<int> $uids
	 *
	 * @return array{moved: list<int>, failed: list<int>}
	 */
	public function batchMoveMessages(array $uids, string $fromMailbox, string $toMailbox): array
	{
		$result = $this->inner->batchMoveMessages($uids, $fromMailbox, $toMailbox);
		$this->invalidateMailbox($fromMailbox);
		$this->invalidateMailbox($toMailbox);

		return $result;
	}

	public function copyMessage(int $uid, string $fromMailbox, string $toMailbox): void
	{
		$this->inner->copyMessage($uid, $fromMailbox, $toMailbox);
		$this->invalidateMailbox($toMailbox);
	}

	public function deleteMessage(int $uid, string $mailbox = 'INBOX'): void
	{
		$this->inner->deleteMessage($uid, $mailbox);
		$this->invalidateMailbox($mailbox);
		$this->cache->deleteMultiple(["msg:{$mailbox}:{$uid}:text:4000", "msg:{$mailbox}:{$uid}:html:4000", "msg:{$mailbox}:{$uid}:both:4000"]);
		$this->cache->delete("hdr:{$mailbox}:{$uid}");
	}

	/**
	 * @param list<int> $uids
	 */
	public function batchDeleteMessages(array $uids, string $mailbox): bool
	{
		$result = $this->inner->batchDeleteMessages($uids, $mailbox);
		$this->invalidateMailbox($mailbox);

		$keys = [];

		foreach ($uids as $uid) {
			$keys[] = "msg:{$mailbox}:{$uid}:text:4000";
			$keys[] = "msg:{$mailbox}:{$uid}:html:4000";
			$keys[] = "msg:{$mailbox}:{$uid}:both:4000";
			$keys[] = "hdr:{$mailbox}:{$uid}";
		}

		$this->cache->deleteMultiple($keys);

		return $result;
	}

	public function setFlag(int $uid, string $flag, string $mailbox = 'INBOX'): void
	{
		$this->inner->setFlag($uid, $flag, $mailbox);
		$this->cache->delete("hdr:{$mailbox}:{$uid}");
		$this->invalidateMailbox($mailbox);
	}

	public function clearFlag(int $uid, string $flag, string $mailbox = 'INBOX'): void
	{
		$this->inner->clearFlag($uid, $flag, $mailbox);
		$this->cache->delete("hdr:{$mailbox}:{$uid}");
		$this->invalidateMailbox($mailbox);
	}

	/**
	 * @param list<int> $uids
	 */
	public function batchSetFlag(array $uids, string $flag, bool $set, string $mailbox): bool
	{
		$result = $this->inner->batchSetFlag($uids, $flag, $set, $mailbox);

		$keys = [];

		foreach ($uids as $uid) {
			$keys[] = "hdr:{$mailbox}:{$uid}";
		}

		$this->cache->deleteMultiple($keys);
		$this->invalidateMailbox($mailbox);

		return $result;
	}

	/**
	 * @return list<array{filename: string, size: int, mime_type: string, saved_path: string}>
	 */
	public function fetchAttachments(int $uid, string $mailbox = 'INBOX', string $savePath = 'var/attachments'): array
	{
		return $this->inner->fetchAttachments($uid, $mailbox, $savePath);
	}

	private function getMailboxVersion(string $mailbox): string
	{
		$versionKey = "ver:{$mailbox}";

		/** @var string|null $version */
		$version = $this->cache->get($versionKey);

		if ($version !== null) {
			return $version;
		}

		$version = (string) hrtime(true);
		$this->cache->set($versionKey, $version);

		return $version;
	}

	private function invalidateMailbox(string $mailbox): void
	{
		$this->cache->delete("ver:{$mailbox}");
		$this->cache->delete($this->mailboxKey('count', $mailbox));
	}

	private function mailboxKey(string $prefix, string $mailbox): string
	{
		return "{$prefix}:{$mailbox}";
	}
}
