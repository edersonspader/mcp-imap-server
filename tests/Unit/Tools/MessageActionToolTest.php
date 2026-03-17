<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Exception\MessageNotFoundException;
use App\Imap\ImapConnection;
use App\Imap\ImapConnectionFactory;
use App\Tools\MessageActionTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageActionTool::class)]
final class MessageActionToolTest extends TestCase
{
	private ImapConnection&MockObject $connection;
	private MessageActionTool $tool;

	protected function setUp(): void
	{
		$this->connection = $this->createMock(ImapConnection::class);

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($this->connection);

		$this->tool = new MessageActionTool($factory);
	}

	#[Test]
	public function it_moves_message(): void
	{
		$this->connection->expects(self::once())
			->method('moveMessage')
			->with(1, 'INBOX', 'Archive');
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->moveMessage(1, 'INBOX', 'Archive');

		self::assertTrue($result['success']);
		self::assertStringContainsString('moved', $result['message']);
	}

	#[Test]
	public function it_copies_message(): void
	{
		$this->connection->expects(self::once())
			->method('copyMessage')
			->with(2, 'INBOX', 'Backup');
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->copyMessage(2, 'INBOX', 'Backup');

		self::assertTrue($result['success']);
		self::assertStringContainsString('copied', $result['message']);
	}

	#[Test]
	public function it_deletes_message(): void
	{
		$this->connection->expects(self::once())
			->method('deleteMessage')
			->with(3, 'INBOX');
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->deleteMessage(3);

		self::assertTrue($result['success']);
		self::assertStringContainsString('deleted', $result['message']);
	}

	#[Test]
	public function it_sets_flag(): void
	{
		$this->connection->expects(self::once())
			->method('setFlag')
			->with(4, 'Seen', 'INBOX');
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->flagMessage(4, 'Seen');

		self::assertTrue($result['success']);
		self::assertStringContainsString('set', $result['message']);
	}

	#[Test]
	public function it_clears_flag(): void
	{
		$this->connection->expects(self::once())
			->method('clearFlag')
			->with(5, 'Flagged', 'Sent');
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->flagMessage(5, 'Flagged', false, 'Sent');

		self::assertTrue($result['success']);
		self::assertStringContainsString('cleared', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_message_not_found(): void
	{
		$this->connection->expects(self::once())->method('moveMessage')
			->willThrowException(new MessageNotFoundException('Message UID 99 not found'));

		$result = $this->tool->moveMessage(99, 'INBOX', 'Archive');

		self::assertTrue($result['error']);
		self::assertStringContainsString('99', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_mailbox_not_found(): void
	{
		$this->connection->expects(self::once())->method('deleteMessage')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Ghost' not found"));

		$result = $this->tool->deleteMessage(1, 'Ghost');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Ghost', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_connection_failure(): void
	{
		$this->connection->expects(self::never())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')
			->willThrowException(new ImapConnectionException('Auth failed'));

		$tool = new MessageActionTool($factory);
		$result = $tool->moveMessage(1, 'INBOX', 'Archive');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Connection failed', $result['message']);
	}

	#[Test]
	public function it_disconnects_on_flag_error(): void
	{
		$this->connection->expects(self::once())->method('setFlag')
			->willThrowException(new MessageNotFoundException('Not found'));
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->flagMessage(100, 'Seen');

		self::assertTrue($result['error']);
	}

	#[Test]
	public function it_batch_moves_messages(): void
	{
		$this->connection->expects(self::once())
			->method('batchMoveMessages')
			->with([1, 2, 3], 'INBOX', 'Archive')
			->willReturn(['moved' => [1, 2, 3], 'failed' => []]);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->batchMoveMessages([1, 2, 3], 'INBOX', 'Archive');

		self::assertTrue($result['success']);
		self::assertSame([1, 2, 3], $result['moved']);
		self::assertStringContainsString('All 3 messages moved', $result['message']);
	}

	#[Test]
	public function it_batch_moves_with_partial_failure(): void
	{
		$this->connection->expects(self::once())
			->method('batchMoveMessages')
			->with([1, 2, 3], 'INBOX', 'Archive')
			->willReturn(['moved' => [], 'failed' => [1, 2, 3]]);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->batchMoveMessages([1, 2, 3], 'INBOX', 'Archive');

		self::assertTrue($result['error']);
		self::assertSame([1, 2, 3], $result['failed']);
		self::assertStringContainsString('All 3 messages failed', $result['message']);
	}

	#[Test]
	public function it_returns_error_when_all_batch_messages_fail(): void
	{
		$this->connection->expects(self::once())
			->method('batchMoveMessages')
			->willReturn(['moved' => [], 'failed' => [1, 2]]);

		$result = $this->tool->batchMoveMessages([1, 2], 'INBOX', 'Archive');

		self::assertTrue($result['error']);
		self::assertStringContainsString('All 2 messages failed', $result['message']);
	}

	#[Test]
	public function it_returns_error_when_batch_exceeds_limit(): void
	{
		$this->connection->expects(self::never())->method('batchMoveMessages');
		$this->connection->expects(self::never())->method('disconnect');

		$uids = range(1, 51);
		$result = $this->tool->batchMoveMessages($uids, 'INBOX', 'Archive');

		self::assertTrue($result['error']);
		self::assertStringContainsString('exceeds limit', $result['message']);
	}

	#[Test]
	public function it_returns_error_when_batch_is_empty(): void
	{
		$this->connection->expects(self::never())->method('batchMoveMessages');
		$this->connection->expects(self::never())->method('disconnect');

		$result = $this->tool->batchMoveMessages([], 'INBOX', 'Archive');

		self::assertTrue($result['error']);
		self::assertStringContainsString('No UIDs', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_batch_mailbox_not_found(): void
	{
		$this->connection->expects(self::once())
			->method('batchMoveMessages')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Ghost' not found"));
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->batchMoveMessages([1, 2], 'Ghost', 'Archive');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Ghost', $result['message']);
	}

	#[Test]
	public function it_disconnects_after_batch_move(): void
	{
		$this->connection->expects(self::once())
			->method('batchMoveMessages')
			->willReturn(['moved' => [1], 'failed' => []]);
		$this->connection->expects(self::once())->method('disconnect');

		$this->tool->batchMoveMessages([1], 'INBOX', 'Archive');
	}

	#[Test]
	public function it_batch_sets_flag(): void
	{
		$this->connection->expects(self::once())
			->method('batchSetFlag')
			->with([1, 2, 3], 'Seen', true, 'INBOX')
			->willReturn(true);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->batchFlagMessages([1, 2, 3], 'Seen');

		self::assertTrue($result['success']);
		self::assertStringContainsString("Flag 'Seen' set on 3 messages", $result['message']);
	}

	#[Test]
	public function it_batch_clears_flag(): void
	{
		$this->connection->expects(self::once())
			->method('batchSetFlag')
			->with([4, 5], 'Flagged', false, 'Sent')
			->willReturn(true);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->batchFlagMessages([4, 5], 'Flagged', false, 'Sent');

		self::assertTrue($result['success']);
		self::assertStringContainsString("Flag 'Flagged' cleared on 2 messages", $result['message']);
	}

	#[Test]
	public function it_returns_error_on_batch_flag_failure(): void
	{
		$this->connection->expects(self::once())
			->method('batchSetFlag')
			->willReturn(false);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->batchFlagMessages([1, 2], 'Seen');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Failed to set', $result['message']);
	}

	#[Test]
	public function it_returns_error_when_batch_flag_exceeds_limit(): void
	{
		$this->connection->expects(self::never())->method('batchSetFlag');

		$uids = range(1, 51);
		$result = $this->tool->batchFlagMessages($uids, 'Seen');

		self::assertTrue($result['error']);
		self::assertStringContainsString('exceeds limit', $result['message']);
	}

	#[Test]
	public function it_returns_error_when_batch_flag_is_empty(): void
	{
		$this->connection->expects(self::never())->method('batchSetFlag');

		$result = $this->tool->batchFlagMessages([], 'Seen');

		self::assertTrue($result['error']);
		self::assertStringContainsString('No UIDs', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_batch_flag_mailbox_not_found(): void
	{
		$this->connection->expects(self::once())
			->method('batchSetFlag')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Ghost' not found"));
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->batchFlagMessages([1], 'Seen', true, 'Ghost');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Ghost', $result['message']);
	}
}
