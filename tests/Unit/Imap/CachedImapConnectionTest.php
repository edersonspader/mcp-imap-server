<?php

declare(strict_types=1);

namespace App\Tests\Unit\Imap;

use App\Imap\CachedImapConnection;
use App\Imap\ImapConnectionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversClass(CachedImapConnection::class)]
final class CachedImapConnectionTest extends TestCase
{
	private ImapConnectionInterface&MockObject $inner;
	private Psr16Cache $cache;
	private CachedImapConnection $connection;

	protected function setUp(): void
	{
		$this->inner = $this->createMock(ImapConnectionInterface::class);
		$this->cache = new Psr16Cache(new ArrayAdapter());
		$this->connection = new CachedImapConnection($this->inner, $this->cache);
	}

	#[Test]
	public function it_caches_mailboxes_on_second_call(): void
	{
		$data = [['name' => 'INBOX', 'path' => 'INBOX', 'children' => 0]];
		$this->inner->expects(self::once())->method('listMailboxes')->willReturn($data);

		$first = $this->connection->listMailboxes();
		$second = $this->connection->listMailboxes();

		self::assertSame($data, $first);
		self::assertSame($data, $second);
	}

	#[Test]
	public function it_caches_count_messages_on_second_call(): void
	{
		$data = ['total' => 42, 'unseen' => 5, 'recent' => 1];
		$this->inner->expects(self::once())->method('countMessages')->with('INBOX')->willReturn($data);

		$first = $this->connection->countMessages('INBOX');
		$second = $this->connection->countMessages('INBOX');

		self::assertSame($data, $first);
		self::assertSame($data, $second);
	}

	#[Test]
	public function it_caches_list_messages_on_second_call(): void
	{
		$data = [['uid' => 1, 'subject' => 'Test']];
		$this->inner->expects(self::once())
			->method('listMessages')
			->with('INBOX', 20, 0, null)
			->willReturn($data);

		$first = $this->connection->listMessages('INBOX');
		$second = $this->connection->listMessages('INBOX');

		self::assertSame($data, $first);
		self::assertSame($data, $second);
	}

	#[Test]
	public function it_caches_search_messages_on_second_call(): void
	{
		$data = [['uid' => 2, 'subject' => 'Found']];
		$this->inner->expects(self::once())
			->method('searchMessages')
			->willReturn($data);

		$first = $this->connection->searchMessages('INBOX', from: 'test@example.com');
		$second = $this->connection->searchMessages('INBOX', from: 'test@example.com');

		self::assertSame($data, $first);
		self::assertSame($data, $second);
	}

	#[Test]
	public function it_caches_read_message_indefinitely(): void
	{
		$data = [
			'uid' => 1,
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'subject' => 'Hi',
			'date' => '2025-01-01',
			'body' => 'Hello',
			'has_attachments' => false,
		];
		$this->inner->expects(self::once())
			->method('readMessage')
			->with(1, 'INBOX', 'text', 4000)
			->willReturn($data);

		$first = $this->connection->readMessage(1);
		$second = $this->connection->readMessage(1);

		self::assertSame($data, $first);
		self::assertSame($data, $second);
	}

	#[Test]
	public function it_caches_message_headers_indefinitely(): void
	{
		$data = [
			'uid' => 1,
			'message_id' => '<abc@test>',
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'reply_to' => '',
			'subject' => 'Hi',
			'date' => '2025-01-01',
			'in_reply_to' => '',
			'seen' => true,
			'flagged' => false,
			'answered' => false,
		];
		$this->inner->expects(self::once())
			->method('getMessageHeaders')
			->with(1, 'INBOX')
			->willReturn($data);

		$first = $this->connection->getMessageHeaders(1);
		$second = $this->connection->getMessageHeaders(1);

		self::assertSame($data, $first);
		self::assertSame($data, $second);
	}

	#[Test]
	public function it_invalidates_mailboxes_after_create(): void
	{
		$first = [['name' => 'INBOX', 'path' => 'INBOX', 'children' => 0]];
		$second = [
			['name' => 'INBOX', 'path' => 'INBOX', 'children' => 0],
			['name' => 'Archive', 'path' => 'Archive', 'children' => 0],
		];
		$this->inner->expects(self::exactly(2))
			->method('listMailboxes')
			->willReturnOnConsecutiveCalls($first, $second);

		$this->connection->listMailboxes();
		$this->connection->createMailbox('Archive');
		$result = $this->connection->listMailboxes();

		self::assertCount(2, $result);
	}

	#[Test]
	public function it_invalidates_mailboxes_after_delete(): void
	{
		$first = [
			['name' => 'INBOX', 'path' => 'INBOX', 'children' => 0],
			['name' => 'Old', 'path' => 'Old', 'children' => 0],
		];
		$second = [['name' => 'INBOX', 'path' => 'INBOX', 'children' => 0]];
		$this->inner->expects(self::exactly(2))
			->method('listMailboxes')
			->willReturnOnConsecutiveCalls($first, $second);

		$this->connection->listMailboxes();
		$this->connection->deleteMailbox('Old');
		$result = $this->connection->listMailboxes();

		self::assertCount(1, $result);
	}

	#[Test]
	public function it_invalidates_list_cache_after_move(): void
	{
		$before = [['uid' => 1, 'subject' => 'Test']];
		$after = [];
		$this->inner->expects(self::exactly(2))
			->method('listMessages')
			->willReturnOnConsecutiveCalls($before, $after);

		$this->connection->listMessages('INBOX');
		$this->connection->moveMessage(1, 'INBOX', 'Archive');
		$result = $this->connection->listMessages('INBOX');

		self::assertSame([], $result);
	}

	#[Test]
	public function it_invalidates_list_cache_after_batch_move(): void
	{
		$before = [['uid' => 1], ['uid' => 2]];
		$after = [];
		$this->inner->expects(self::exactly(2))
			->method('listMessages')
			->willReturnOnConsecutiveCalls($before, $after);
		$this->inner->method('batchMoveMessages')
			->willReturn(['moved' => [1, 2], 'failed' => []]);

		$this->connection->listMessages('INBOX');
		$this->connection->batchMoveMessages([1, 2], 'INBOX', 'Archive');
		$result = $this->connection->listMessages('INBOX');

		self::assertSame([], $result);
	}

	#[Test]
	public function it_invalidates_header_cache_after_flag(): void
	{
		$unseen = [
			'uid' => 1,
			'message_id' => '<abc>',
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'reply_to' => '',
			'subject' => 'Hi',
			'date' => '2025-01-01',
			'in_reply_to' => '',
			'seen' => false,
			'flagged' => false,
			'answered' => false,
		];
		$seen = [
			'uid' => 1,
			'message_id' => '<abc>',
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'reply_to' => '',
			'subject' => 'Hi',
			'date' => '2025-01-01',
			'in_reply_to' => '',
			'seen' => true,
			'flagged' => false,
			'answered' => false,
		];
		$this->inner->expects(self::exactly(2))
			->method('getMessageHeaders')
			->willReturnOnConsecutiveCalls($unseen, $seen);

		$this->connection->getMessageHeaders(1);
		$this->connection->setFlag(1, 'Seen');
		$result = $this->connection->getMessageHeaders(1);

		self::assertTrue($result['seen']);
	}

	#[Test]
	public function it_invalidates_header_cache_after_clear_flag(): void
	{
		$flagged = [
			'uid' => 1,
			'message_id' => '<abc>',
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'reply_to' => '',
			'subject' => 'Hi',
			'date' => '2025-01-01',
			'in_reply_to' => '',
			'seen' => true,
			'flagged' => true,
			'answered' => false,
		];
		$unflagged = [
			'uid' => 1,
			'message_id' => '<abc>',
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'reply_to' => '',
			'subject' => 'Hi',
			'date' => '2025-01-01',
			'in_reply_to' => '',
			'seen' => true,
			'flagged' => false,
			'answered' => false,
		];
		$this->inner->expects(self::exactly(2))
			->method('getMessageHeaders')
			->willReturnOnConsecutiveCalls($flagged, $unflagged);

		$this->connection->getMessageHeaders(1);
		$this->connection->clearFlag(1, 'Flagged');
		$result = $this->connection->getMessageHeaders(1);

		self::assertFalse($result['flagged']);
	}

	#[Test]
	public function it_invalidates_headers_after_batch_flag(): void
	{
		$this->inner->expects(self::exactly(2))
			->method('getMessageHeaders')
			->willReturnOnConsecutiveCalls(
				['uid' => 1, 'message_id' => '', 'from' => '', 'to' => '', 'cc' => '', 'reply_to' => '', 'subject' => '', 'date' => '', 'in_reply_to' => '', 'seen' => false, 'flagged' => false, 'answered' => false],
				['uid' => 1, 'message_id' => '', 'from' => '', 'to' => '', 'cc' => '', 'reply_to' => '', 'subject' => '', 'date' => '', 'in_reply_to' => '', 'seen' => true, 'flagged' => false, 'answered' => false],
			);
		$this->inner->method('batchSetFlag')->willReturn(true);

		$this->connection->getMessageHeaders(1, 'INBOX');
		$this->connection->batchSetFlag([1, 2], 'Seen', true, 'INBOX');
		$result = $this->connection->getMessageHeaders(1, 'INBOX');

		self::assertTrue($result['seen']);
	}

	#[Test]
	public function it_invalidates_caches_after_delete(): void
	{
		$msgData = [
			'uid' => 5,
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'subject' => 'Del',
			'date' => '2025-01-01',
			'body' => 'Body',
			'has_attachments' => false,
		];
		$this->inner->expects(self::exactly(2))->method('readMessage')->willReturn($msgData);

		$this->connection->readMessage(5);
		$this->connection->deleteMessage(5);
		$this->connection->readMessage(5);

		self::assertSame(5, $msgData['uid']);
	}

	#[Test]
	public function it_invalidates_caches_after_batch_delete(): void
	{
		$this->inner->expects(self::exactly(2))
			->method('countMessages')
			->with('INBOX')
			->willReturnOnConsecutiveCalls(
				['total' => 10, 'unseen' => 3, 'recent' => 0],
				['total' => 8, 'unseen' => 1, 'recent' => 0],
			);
		$this->inner->method('batchDeleteMessages')->willReturn(true);

		$this->connection->countMessages('INBOX');
		$this->connection->batchDeleteMessages([1, 2], 'INBOX');
		$result = $this->connection->countMessages('INBOX');

		self::assertSame(8, $result['total']);
	}

	#[Test]
	public function it_invalidates_list_after_copy(): void
	{
		$before = [['uid' => 1]];
		$after = [['uid' => 1], ['uid' => 99]];
		$this->inner->expects(self::exactly(2))
			->method('listMessages')
			->willReturnOnConsecutiveCalls($before, $after);

		$this->connection->listMessages('Archive');
		$this->connection->copyMessage(1, 'INBOX', 'Archive');
		$result = $this->connection->listMessages('Archive');

		self::assertCount(2, $result);
	}

	#[Test]
	public function it_delegates_disconnect_to_inner(): void
	{
		$this->inner->expects(self::once())->method('disconnect');

		$this->connection->disconnect();
	}

	#[Test]
	public function it_does_not_cache_attachments(): void
	{
		$data = [['filename' => 'f.pdf', 'size' => 100, 'mime_type' => 'application/pdf', 'saved_path' => '/tmp/f.pdf']];
		$this->inner->expects(self::exactly(2))
			->method('fetchAttachments')
			->willReturn($data);

		$this->connection->fetchAttachments(1);
		$this->connection->fetchAttachments(1);

		self::assertSame('f.pdf', $data[0]['filename']);
	}

	#[Test]
	public function it_separates_cache_per_mailbox(): void
	{
		$inbox = ['total' => 10, 'unseen' => 2, 'recent' => 0];
		$sent = ['total' => 5, 'unseen' => 0, 'recent' => 0];

		$this->inner->expects(self::exactly(2))
			->method('countMessages')
			->willReturnMap([
				['INBOX', $inbox],
				['Sent', $sent],
			]);

		self::assertSame($inbox, $this->connection->countMessages('INBOX'));
		self::assertSame($sent, $this->connection->countMessages('Sent'));
	}

	#[Test]
	public function it_uses_different_cache_keys_for_different_fields(): void
	{
		$all = [['uid' => 1, 'subject' => 'Test', 'from' => 'a@b.com']];
		$partial = [['uid' => 1, 'subject' => 'Test']];

		$this->inner->expects(self::exactly(2))
			->method('listMessages')
			->willReturnOnConsecutiveCalls($all, $partial);

		$first = $this->connection->listMessages('INBOX', fields: null);
		$second = $this->connection->listMessages('INBOX', fields: ['subject']);

		self::assertSame($all, $first);
		self::assertSame($partial, $second);
	}
}
