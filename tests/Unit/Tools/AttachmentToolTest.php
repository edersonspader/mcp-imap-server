<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MessageNotFoundException;
use App\Imap\ImapConnection;
use App\Imap\ImapConnectionFactory;
use App\Tools\AttachmentTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttachmentTool::class)]
final class AttachmentToolTest extends TestCase
{
	private ImapConnection&MockObject $connection;
	private AttachmentTool $tool;

	protected function setUp(): void
	{
		$this->connection = $this->createMock(ImapConnection::class);

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($this->connection);

		$this->tool = new AttachmentTool($factory);
	}

	#[Test]
	public function it_fetches_attachments(): void
	{
		$expected = [
			['filename' => 'doc.pdf', 'size' => 1024, 'mime_type' => 'application/pdf', 'saved_path' => 'var/attachments/1/doc.pdf'],
		];

		$this->connection->expects(self::once())->method('fetchAttachments')->with(1, 'INBOX', 'var/attachments')->willReturn($expected);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->getAttachments(1);

		self::assertSame(['attachments' => $expected], $result);
	}

	#[Test]
	public function it_returns_empty_list_when_no_attachments(): void
	{
		$this->connection->method('fetchAttachments')->willReturn([]);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->getAttachments(2);

		self::assertSame(['attachments' => []], $result);
	}

	#[Test]
	public function it_returns_error_on_message_not_found(): void
	{
		$this->connection->expects(self::once())->method('fetchAttachments')
			->willThrowException(new MessageNotFoundException('Message UID 99 not found'));

		$result = $this->tool->getAttachments(99);

		self::assertTrue($result['error']);
		self::assertStringContainsString('99', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_connection_failure(): void
	{
		$this->connection->expects(self::never())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')
			->willThrowException(new ImapConnectionException('Timeout'));

		$tool = new AttachmentTool($factory);
		$result = $tool->getAttachments(1);

		self::assertTrue($result['error']);
		self::assertStringContainsString('Connection failed', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_mailbox_not_found(): void
	{
		$this->connection->expects(self::once())->method('fetchAttachments')
			->willThrowException(new \App\Exception\MailboxNotFoundException("Mailbox 'Ghost' not found"));

		$result = $this->tool->getAttachments(1, 'Ghost');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Ghost', $result['message']);
	}

	#[Test]
	public function it_uses_custom_save_path(): void
	{
		$this->connection->expects(self::once())
			->method('fetchAttachments')
			->with(1, 'Sent', '/tmp/attachments')
			->willReturn([]);

		$this->tool->getAttachments(1, 'Sent', '/tmp/attachments');
	}
}
