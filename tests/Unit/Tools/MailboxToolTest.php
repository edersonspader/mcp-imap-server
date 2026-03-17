<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Imap\ImapConnection;
use App\Imap\ImapConnectionFactory;
use App\Tools\MailboxTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MailboxTool::class)]
final class MailboxToolTest extends TestCase
{
	private ImapConnection&MockObject $connection;
	private MailboxTool $tool;

	protected function setUp(): void
	{
		$this->connection = $this->createMock(ImapConnection::class);

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($this->connection);

		$this->tool = new MailboxTool($factory);
	}

	#[Test]
	public function it_lists_mailboxes(): void
	{
		$expected = [
			['name' => 'INBOX', 'path' => 'INBOX', 'children' => 0],
			['name' => 'Sent', 'path' => 'Sent', 'children' => 0],
		];

		$this->connection->method('listMailboxes')->willReturn($expected);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->listMailboxes();

		self::assertSame(['mailboxes' => $expected], $result);
	}

	#[Test]
	public function it_counts_messages(): void
	{
		$expected = ['total' => 42, 'unseen' => 5, 'recent' => 2];

		$this->connection->expects(self::once())->method('countMessages')->with('INBOX')->willReturn($expected);
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->countMessages('INBOX');

		self::assertSame($expected, $result);
	}

	#[Test]
	public function it_creates_mailbox(): void
	{
		$this->connection->expects(self::once())->method('createMailbox')->with('Archive');
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->createMailbox('Archive');

		self::assertTrue($result['success']);
		self::assertStringContainsString('Archive', $result['message']);
	}

	#[Test]
	public function it_deletes_mailbox(): void
	{
		$this->connection->expects(self::once())->method('deleteMailbox')->with('OldFolder');
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->deleteMailbox('OldFolder');

		self::assertTrue($result['success']);
		self::assertStringContainsString('OldFolder', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_mailbox_not_found(): void
	{
		$this->connection->expects(self::once())->method('countMessages')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Missing' not found"));

		$result = $this->tool->countMessages('Missing');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Missing', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_connection_failure(): void
	{
		$this->connection->expects(self::never())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')
			->willThrowException(new ImapConnectionException('Connection refused'));

		$tool = new MailboxTool($factory);
		$result = $tool->listMailboxes();

		self::assertTrue($result['error']);
		self::assertStringContainsString('Connection failed', $result['message']);
	}

	#[Test]
	public function it_disconnects_on_delete_mailbox_not_found(): void
	{
		$this->connection->method('deleteMailbox')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Ghost' not found"));
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->deleteMailbox('Ghost');

		self::assertTrue($result['error']);
	}

	#[Test]
	public function it_returns_error_on_create_mailbox_failure(): void
	{
		$this->connection->method('createMailbox')
			->willThrowException(new \RuntimeException('Folder already exists'));
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->createMailbox('INBOX');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Failed to create mailbox', $result['message']);
		self::assertStringContainsString('Folder already exists', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_delete_mailbox_failure(): void
	{
		$this->connection->method('deleteMailbox')
			->willThrowException(new \RuntimeException('Permission denied'));
		$this->connection->expects(self::once())->method('disconnect');

		$result = $this->tool->deleteMailbox('Protected');

		self::assertTrue($result['error']);
		self::assertStringContainsString('Failed to delete mailbox', $result['message']);
		self::assertStringContainsString('Permission denied', $result['message']);
	}
}
