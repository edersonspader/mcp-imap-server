<?php

declare(strict_types=1);

namespace App\Tests\Unit\Imap;

use App\Exception\MailboxNotFoundException;
use App\Imap\ImapConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Client;

#[CoversClass(ImapConnection::class)]
final class ImapConnectionTest extends TestCase
{
    private function createClientStub(): Client&Stub
    {
        $client = $this->createStub(Client::class);
        $client->method('disconnect')->willReturn($client);

        return $client;
    }

    #[Test]
    public function it_disconnects(): void
    {
        $client = $this->createClientStub();

        $connection = new ImapConnection($client);
        $connection->disconnect();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_on_mailbox_not_found(): void
    {
        $client = $this->createClientStub();
        $client->method('getFolderByPath')->willReturn(null);

        $connection = new ImapConnection($client);

        $this->expectException(MailboxNotFoundException::class);
        $this->expectExceptionMessage("Mailbox 'NonExistent' not found");

        $connection->countMessages('NonExistent');
    }

    #[Test]
    public function it_creates_mailbox(): void
    {
        $client = $this->createClientStub();

        $connection = new ImapConnection($client);
        $connection->createMailbox('NewFolder');

        $this->addToAssertionCount(1);
    }
}
