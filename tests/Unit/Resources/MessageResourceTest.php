<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resources;

use App\Imap\ImapConnection;
use App\Imap\ImapConnectionFactory;
use App\Resources\MessageResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageResource::class)]
final class MessageResourceTest extends TestCase
{
	#[Test]
	public function it_returns_message_by_uid(): void
	{
		$expected = [
			'uid' => 42,
			'from' => 'sender@test.com',
			'to' => 'me@test.com',
			'cc' => '',
			'subject' => 'Test Message',
			'date' => '2026-01-15 09:30:00',
			'body' => 'Hello from the test',
			'has_attachments' => false,
		];

		$connection = $this->createMock(ImapConnection::class);
		$connection->expects(self::once())->method('readMessage')
			->with(42, 'INBOX', 'text', 8000)
			->willReturn($expected);
		$connection->expects(self::once())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($connection);

		$resource = new MessageResource($factory);
		$result = $resource->getMessage('INBOX', '42');

		self::assertSame($expected, $result);
	}

	#[Test]
	public function it_reads_from_different_mailbox(): void
	{
		$connection = $this->createMock(ImapConnection::class);
		$connection->expects(self::once())
			->method('readMessage')
			->with(10, 'Sent', 'text', 8000)
			->willReturn([
				'uid' => 10,
				'from' => 'me@test.com',
				'to' => 'other@test.com',
				'cc' => '',
				'subject' => 'Sent msg',
				'date' => '2026-02-01 14:00:00',
				'body' => 'Body',
				'has_attachments' => false,
			]);
		$connection->expects(self::once())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($connection);

		$resource = new MessageResource($factory);
		$result = $resource->getMessage('Sent', '10');

		self::assertSame(10, $result['uid']);
		self::assertSame('Sent msg', $result['subject']);
	}

	#[Test]
	public function it_disconnects_even_on_exception(): void
	{
		$connection = $this->createMock(ImapConnection::class);
		$connection->expects(self::once())->method('readMessage')
			->willThrowException(new \App\Exception\MessageNotFoundException('Not found'));
		$connection->expects(self::once())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($connection);

		$resource = new MessageResource($factory);

		$this->expectException(\App\Exception\MessageNotFoundException::class);

		$resource->getMessage('INBOX', '999');
	}
}
