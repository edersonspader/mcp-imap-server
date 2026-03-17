<?php

declare(strict_types=1);

namespace App\Tests\Unit\Resources;

use App\Exception\ImapConnectionException;
use App\Imap\ImapConnection;
use App\Imap\ImapConnectionFactory;
use App\Resources\MailboxStatusResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MailboxStatusResource::class)]
final class MailboxStatusResourceTest extends TestCase
{
	#[Test]
	public function it_returns_status_for_all_mailboxes(): void
	{
		$connection = $this->createMock(ImapConnection::class);

		$connection->method('listMailboxes')->willReturn([
			['name' => 'INBOX', 'path' => 'INBOX', 'children' => 0],
			['name' => 'Sent', 'path' => 'Sent', 'children' => 0],
		]);

		$connection->method('countMessages')->willReturnMap([
			['INBOX', ['total' => 100, 'unseen' => 10, 'recent' => 3]],
			['Sent', ['total' => 50, 'unseen' => 0, 'recent' => 0]],
		]);

		$connection->expects(self::once())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($connection);

		$resource = new MailboxStatusResource($factory);
		$result = $resource->getStatus();

		self::assertCount(2, $result);
		self::assertSame('INBOX', $result[0]['name']);
		self::assertSame(100, $result[0]['total']);
		self::assertSame(10, $result[0]['unseen']);
		self::assertSame('Sent', $result[1]['name']);
		self::assertSame(50, $result[1]['total']);
	}

	#[Test]
	public function it_returns_empty_list_when_no_mailboxes(): void
	{
		$connection = $this->createMock(ImapConnection::class);
		$connection->method('listMailboxes')->willReturn([]);
		$connection->expects(self::once())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($connection);

		$resource = new MailboxStatusResource($factory);
		$result = $resource->getStatus();

		self::assertSame([], $result);
	}

	#[Test]
	public function it_disconnects_even_on_exception(): void
	{
		$connection = $this->createMock(ImapConnection::class);
		$connection->method('listMailboxes')->willThrowException(new \RuntimeException('IMAP error'));
		$connection->expects(self::once())->method('disconnect');

		$factory = $this->createStub(ImapConnectionFactory::class);
		$factory->method('create')->willReturn($connection);

		$resource = new MailboxStatusResource($factory);

		$this->expectException(\RuntimeException::class);

		$resource->getStatus();
	}
}
