<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Exception\MessageNotFoundException;
use App\Imap\ImapConnection;
use App\Imap\ImapConnectionFactory;
use App\Tools\MessageTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageTool::class)]
final class MessageToolTest extends TestCase
{
	private ImapConnection&MockObject $connection;
	private MessageTool $tool;

	protected function setUp(): void
	{
		$this->connection = $this->createMock(ImapConnection::class);

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($this->connection);

		$this->tool = new MessageTool($factory);
	}

	#[Test]
	public function it_lists_messages(): void
	{
		$expected = [
			['uid' => 1, 'from' => 'a@b.com', 'to' => 'c@d.com', 'subject' => 'Hi', 'date' => '2026-01-01 10:00:00', 'seen' => true],
		];

		$this->connection->expects(self::once())->method('listMessages')->with('INBOX', 20, 0)->willReturn($expected);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->listMessages();

		self::assertSame(['messages' => $expected], $result);
	}

	#[Test]
	public function it_lists_messages_with_pagination(): void
	{
		$this->connection->expects(self::once())
			->method('listMessages')
			->with('Sent', 10, 5, null)
			->willReturn([]);

		$result = $this->tool->listMessages('Sent', 10, 5);

		self::assertSame(['messages' => []], $result);
	}

	#[Test]
	public function it_searches_messages(): void
	{
		$expected = [
			['uid' => 5, 'from' => 'boss@co.com', 'to' => 'me@co.com', 'subject' => 'Urgent', 'date' => '2026-03-01 08:00:00', 'seen' => false],
		];

		$this->connection->method('searchMessages')->willReturn($expected);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->searchMessages(from: 'boss@co.com', unseen: true);

		self::assertSame(['messages' => $expected], $result);
	}

	#[Test]
	public function it_reads_message(): void
	{
		$expected = [
			'uid' => 42,
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'subject' => 'Test',
			'date' => '2026-01-01 10:00:00',
			'body' => 'Hello world',
			'has_attachments' => false,
		];

		$this->connection->expects(self::once())->method('readMessage')->with(42, 'INBOX', 'text', 4000)->willReturn($expected);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->readMessage(42);

		self::assertSame($expected, $result);
	}

	#[Test]
	public function it_gets_message_headers(): void
	{
		$expected = [
			'uid' => 10,
			'message_id' => '<abc@test.com>',
			'from' => 'a@b.com',
			'to' => 'c@d.com',
			'cc' => '',
			'reply_to' => 'a@b.com',
			'subject' => 'Headers',
			'date' => '2026-01-01 10:00:00',
			'in_reply_to' => '',
			'seen' => true,
			'flagged' => false,
			'answered' => false,
		];

		$this->connection->expects(self::once())->method('getMessageHeaders')->with(10, 'INBOX')->willReturn($expected);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->getMessageHeaders(10);

		self::assertSame($expected, $result);
	}

	#[Test]
	public function it_returns_error_on_mailbox_not_found(): void
	{
		$this->connection->expects(self::once())->method('listMessages')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Ghost' not found"));

		$result = $this->tool->listMessages('Ghost');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Ghost', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_message_not_found(): void
	{
		$this->connection->expects(self::once())->method('readMessage')
			->willThrowException(new MessageNotFoundException('Message UID 999 not found'));

		$result = $this->tool->readMessage(999);

		self::assertTrue($result['error']);
		self::assertStringContainsString('999', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_connection_failure(): void
	{
		$this->connection->expects(self::never())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')
			->willThrowException(new ImapConnectionException('Timeout'));

		$tool = new MessageTool($factory);
		$result = $tool->listMessages();

		self::assertTrue($result['error']);
		self::assertStringContainsString('Connection failed', $result['message']);
	}

	#[Test]
	public function it_lists_messages_with_selected_fields(): void
	{
		$expected = [
			['uid' => 1, 'subject' => 'Hi'],
			['uid' => 2, 'subject' => 'Bye'],
		];

		$this->connection->expects(self::once())
			->method('listMessages')
			->with('INBOX', 20, 0, ['uid', 'subject'])
			->willReturn($expected);

		$result = $this->tool->listMessages(fields: ['uid', 'subject']);

		self::assertSame(['messages' => $expected], $result);
	}

	#[Test]
	public function it_searches_messages_with_selected_fields(): void
	{
		$expected = [
			['uid' => 5, 'from' => 'boss@co.com'],
		];

		$this->connection->expects(self::once())
			->method('searchMessages')
			->with(
				'INBOX',
				'boss@co.com',
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				20,
				0,
				['uid', 'from'],
			)
			->willReturn($expected);

		$result = $this->tool->searchMessages(from: 'boss@co.com', fields: ['uid', 'from']);

		self::assertSame(['messages' => $expected], $result);
	}
}
