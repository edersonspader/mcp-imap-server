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
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\Folder;

#[CoversClass(ImapConnection::class)]
final class ImapConnectionTest extends TestCase
{
    private function createClientStub(): Client&Stub
    {
        $client = $this->createStub(Client::class);
        $client->method('disconnect')->willReturn($client);

        return $client;
    }

    private function createFolderStub(string $path): Folder&Stub
    {
        $folder = $this->createStub(Folder::class);
        $folder->path = $path;

        return $folder;
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

    #[Test]
    public function it_batch_moves_using_encoded_folder_paths(): void
    {
        $client = $this->createClientStub();
        $sourceFolder = $this->createFolderStub('Lixo Eletr&APQ-nico');
        $destFolder = $this->createFolderStub('A&AOc-&APU-es');

        $client->method('getFolderByPath')
            ->willReturnMap([
                ['Lixo Eletrônico', false, false, $sourceFolder],
                ['Ações', false, false, $destFolder],
            ]);

        /** @var list<string> $openFolderCalls */
        $openFolderCalls = [];
        $client->method('openFolder')
            ->willReturnCallback(function (string $path) use (&$openFolderCalls): array {
                $openFolderCalls[] = $path;
                return [];
            });

        $response = $this->createStub(Response::class);
        $response->method('boolean')->willReturn(true);

        $protocol = $this->createMock(ImapProtocol::class);
        $protocol->expects(self::once())
            ->method('moveManyMessages')
            ->with(['1', '2'], 'A&AOc-&APU-es')
            ->willReturn($response);

        $client->method('getConnection')->willReturn($protocol);

        $connection = new ImapConnection($client);
        $result = $connection->batchMoveMessages([1, 2], 'Lixo Eletrônico', 'Ações');

        self::assertSame([1, 2], $result['moved']);
        self::assertSame([], $result['failed']);
        self::assertSame(['Lixo Eletr&APQ-nico'], $openFolderCalls);
    }

    #[Test]
    public function it_batch_sets_flag_using_encoded_folder_path(): void
    {
        $client = $this->createClientStub();
        $folder = $this->createFolderStub('Lixo Eletr&APQ-nico');

        $client->method('getFolderByPath')
            ->willReturnCallback(fn(string $path) => match ($path) {
                'Lixo Eletrônico' => $folder,
                default => null,
            });

        /** @var list<string> $openFolderCalls */
        $openFolderCalls = [];
        $client->method('openFolder')
            ->willReturnCallback(function (string $path) use (&$openFolderCalls): array {
                $openFolderCalls[] = $path;
                return [];
            });

        $protocol = $this->createStub(ImapProtocol::class);
        $client->method('getConnection')->willReturn($protocol);

        $connection = new ImapConnection($client);
        $connection->batchSetFlag([1, 2], 'Seen', true, 'Lixo Eletrônico');

        self::assertSame(['Lixo Eletr&APQ-nico'], $openFolderCalls);
    }

    #[Test]
    public function it_batch_deletes_using_encoded_folder_path(): void
    {
        $client = $this->createClientStub();
        $folder = $this->createFolderStub('Lixo Eletr&APQ-nico');

        $client->method('getFolderByPath')
            ->willReturnCallback(fn(string $path) => match ($path) {
                'Lixo Eletrônico' => $folder,
                default => null,
            });

        /** @var list<string> $openFolderCalls */
        $openFolderCalls = [];
        $client->method('openFolder')
            ->willReturnCallback(function (string $path) use (&$openFolderCalls): array {
                $openFolderCalls[] = $path;
                return [];
            });

        $protocol = $this->createStub(ImapProtocol::class);
        $client->method('getConnection')->willReturn($protocol);

        $connection = new ImapConnection($client);
        $connection->batchDeleteMessages([1, 2], 'Lixo Eletrônico');

        self::assertSame(['Lixo Eletr&APQ-nico'], $openFolderCalls);
    }
}
