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
}
