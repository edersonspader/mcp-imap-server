<?php

declare(strict_types=1);

namespace App\Imap;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Exception\MessageNotFoundException;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

class ImapConnection implements ImapConnectionInterface
{
    public function __construct(
        private readonly Client $client,
    ) {}

    public function disconnect(): void
    {
        $this->client->disconnect();
    }

    /** @return list<array{name: string, path: string, children: int}> */
    public function listMailboxes(): array
    {
        $folders = $this->client->getFolders(hierarchical: false);
        $result = [];

        /** @var Folder $folder */
        foreach ($folders as $folder) {
            $result[] = [
                'name' => $folder->name,
                'path' => $folder->path,
                'children' => $folder->children->count(),
            ];
        }

        return $result;
    }

    /** @return array{total: int, unseen: int, recent: int} */
    public function countMessages(string $mailbox): array
    {
        $folder = $this->getFolder($mailbox);

        try {
            $status = $folder->status();
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error counting messages in '{$mailbox}': {$e->getMessage()}",
                previous: $e,
            );
        }

        return [
            'total' => $status['messages'] ?? 0,
            'unseen' => $status['unseen'] ?? 0,
            'recent' => $status['recent'] ?? 0,
        ];
    }

    public function createMailbox(string $name): void
    {
        try {
            $this->client->openFolder('INBOX');
            $this->client->createFolder($name);
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error creating mailbox '{$name}': {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function deleteMailbox(string $name): void
    {
        $folder = $this->getFolder($name);

        try {
            $this->client->openFolder('INBOX');
            $folder->delete();
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error deleting mailbox '{$name}': {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param list<string>|null $fields
     *
     * @return list<array<string, mixed>>
     *
     * @throws MailboxNotFoundException
     */
    public function listMessages(string $mailbox, int $limit = 20, int $offset = 0, array|null $fields = null): array
    {
        $folder = $this->getFolder($mailbox);
        $page = $offset > 0 ? (int) floor($offset / $limit) + 1 : 1;
        try {
            $messages = $folder->messages()
                ->all()
                ->setFetchBody(false)
                ->limit($limit, $page)
                ->get();
        } catch (GetMessagesFailedException) {
            return [];
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error listing messages in '{$mailbox}': {$e->getMessage()}",
                previous: $e,
            );
        }

        $result = [];

        /** @var Message $message */
        foreach ($messages as $message) {
            $result[] = $this->formatMessageSummary($message, $fields);
        }

        return $result;
    }

    /**
     * @param list<string>|null $fields
     *
     * @return list<array<string, mixed>>
     *
     * @throws MailboxNotFoundException
     */
    public function searchMessages(
        string $mailbox,
        string|null $from = null,
        string|null $to = null,
        string|null $subject = null,
        string|null $since = null,
        string|null $before = null,
        string|null $body = null,
        bool|null $unseen = null,
        bool|null $flagged = null,
        int $limit = 20,
        int $offset = 0,
        array|null $fields = null,
    ): array {
        $folder = $this->getFolder($mailbox);

        $query = $folder->messages()->all()->setFetchBody(false);

        if ($from !== null) {
            $query = $query->from($from);
        }

        if ($to !== null) {
            $query = $query->to($to);
        }

        if ($subject !== null) {
            $query = $query->subject($subject);
        }

        if ($since !== null) {
            $query = $query->since($since);
        }

        if ($before !== null) {
            $query = $query->before($before);
        }

        if ($body !== null) {
            $query = $query->text($body);
        }

        if ($unseen === true) {
            $query = $query->unseen();
        }

        if ($flagged === true) {
            $query = $query->flagged();
        }

        $page = $offset > 0 ? (int) floor($offset / $limit) + 1 : 1;

        try {
            $messages = $query->limit($limit, $page)->get();
        } catch (GetMessagesFailedException) {
            return [];
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error searching messages in '{$mailbox}': {$e->getMessage()}",
                previous: $e,
            );
        }

        $result = [];

        /** @var Message $message */
        foreach ($messages as $message) {
            $result[] = $this->formatMessageSummary($message, $fields);
        }

        return $result;
    }

    /**
     * @return array{uid: int, from: string, to: string, cc: string, subject: string, date: string, body: string, has_attachments: bool}
     *
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function readMessage(
        int $uid,
        string $mailbox = 'INBOX',
        string $format = 'text',
        int $maxLength = 4000,
    ): array {
        $message = $this->fetchMessageByUid($uid, $mailbox);

        $body = match ($format) {
            'html' => $message->getHTMLBody() !== '' ? $message->getHTMLBody() : $message->getTextBody(),
            'both' => $this->buildCombinedBody($message),
            default => $message->getTextBody() !== '' ? $message->getTextBody() : $message->getHTMLBody(),
        };

        if ($maxLength > 0 && mb_strlen($body) > $maxLength) {
            $body = mb_substr($body, 0, $maxLength) . "\n\n[... truncated at {$maxLength} characters]";
        }

        return [
            'uid' => $message->uid,
            'from' => $this->attributeToString($message->from),
            'to' => $this->attributeToString($message->to),
            'cc' => $this->attributeToString($message->cc),
            'subject' => (string) $message->subject,
            'date' => $this->formatDate($message),
            'body' => $body,
            'has_attachments' => $message->hasAttachments(),
        ];
    }

    /**
     * @return array{uid: int, message_id: string, from: string, to: string, cc: string, reply_to: string, subject: string, date: string, in_reply_to: string, seen: bool, flagged: bool, answered: bool}
     *
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function getMessageHeaders(int $uid, string $mailbox = 'INBOX'): array
    {
        $message = $this->fetchMessageByUid($uid, $mailbox);

        return [
            'uid' => $message->uid,
            'message_id' => (string) $message->message_id,
            'from' => $this->attributeToString($message->from),
            'to' => $this->attributeToString($message->to),
            'cc' => $this->attributeToString($message->cc),
            'reply_to' => $this->attributeToString($message->reply_to),
            'subject' => (string) $message->subject,
            'date' => $this->formatDate($message),
            'in_reply_to' => (string) $message->in_reply_to,
            'seen' => $message->hasFlag('Seen'),
            'flagged' => $message->hasFlag('Flagged'),
            'answered' => $message->hasFlag('Answered'),
        ];
    }

    /**
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function moveMessage(int $uid, string $fromMailbox, string $toMailbox): void
    {
        $message = $this->fetchMessageByUid($uid, $fromMailbox);

        try {
            $message->move($toMailbox);
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error moving message UID {$uid}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param list<int> $uids
     *
     * @return array{moved: list<int>, failed: list<int>}
     *
     * @throws MailboxNotFoundException
     */
    public function batchMoveMessages(array $uids, string $fromMailbox, string $toMailbox): array
    {
        $sourceFolder = $this->getFolder($fromMailbox);
        $destFolder = $this->getFolder($toMailbox);

        try {
            $this->client->openFolder($sourceFolder->path);

            $stringUids = array_map(strval(...), $uids);
            $response = $this->client->getConnection()->moveManyMessages($stringUids, $destFolder->path);
        } catch (MailboxNotFoundException|ImapConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error during batch move: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->boolean()) {
            return ['moved' => $uids, 'failed' => []];
        }

        return ['moved' => [], 'failed' => $uids];
    }

    /**
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function copyMessage(int $uid, string $fromMailbox, string $toMailbox): void
    {
        $message = $this->fetchMessageByUid($uid, $fromMailbox);

        try {
            $message->copy($toMailbox);
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error copying message UID {$uid}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function deleteMessage(int $uid, string $mailbox = 'INBOX'): void
    {
        $message = $this->fetchMessageByUid($uid, $mailbox);

        try {
            $message->delete(expunge: true);
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error deleting message UID {$uid}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param string $flag One of: Seen, Flagged, Answered, Draft, Deleted
     *
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function setFlag(int $uid, string $flag, string $mailbox = 'INBOX'): void
    {
        $message = $this->fetchMessageByUid($uid, $mailbox);

        try {
            $message->setFlag($flag);
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error setting flag '{$flag}' on message UID {$uid}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param string $flag One of: Seen, Flagged, Answered, Draft, Deleted
     *
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function clearFlag(int $uid, string $flag, string $mailbox = 'INBOX'): void
    {
        $message = $this->fetchMessageByUid($uid, $mailbox);

        try {
            $message->unsetFlag($flag);
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error clearing flag '{$flag}' on message UID {$uid}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @param list<int> $uids
     *
     * @throws MailboxNotFoundException
     */
    public function batchSetFlag(array $uids, string $flag, bool $set, string $mailbox): bool
    {
        $folder = $this->getFolder($mailbox);

        try {
            $this->client->openFolder($folder->path);

            /** @var ImapProtocol $protocol */
            $protocol = $this->client->getConnection();
            $command = $protocol->buildUIDCommand('STORE', IMAP::ST_UID);
            $uidSet = implode(',', $uids);
            $mode = $set ? '+' : '-';
            $item = "{$mode}FLAGS.SILENT";
            $flags = $protocol->escapeList(['\\' . $flag]);

            $response = $protocol->requestAndResponse($command, [$uidSet, $item, $flags], true);
        } catch (MailboxNotFoundException|ImapConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error during batch flag: {$e->getMessage()}",
                previous: $e,
            );
        }

        return $response->boolean();
    }

    /**
     * @param list<int> $uids
     *
     * @throws MailboxNotFoundException
     */
    public function batchDeleteMessages(array $uids, string $mailbox): bool
    {
        $folder = $this->getFolder($mailbox);

        try {
            $this->client->openFolder($folder->path);

            /** @var ImapProtocol $protocol */
            $protocol = $this->client->getConnection();
            $command = $protocol->buildUIDCommand('STORE', IMAP::ST_UID);
            $uidSet = implode(',', $uids);
            $flags = $protocol->escapeList(['\\Deleted']);

            $response = $protocol->requestAndResponse($command, [$uidSet, '+FLAGS.SILENT', $flags], true);

            if (!$response->boolean()) {
                return false;
            }

            return $this->client->getConnection()->expunge()->boolean();
        } catch (MailboxNotFoundException|ImapConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error during batch delete: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * @return list<array{filename: string, size: int, mime_type: string, saved_path: string}>
     *
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    public function fetchAttachments(int $uid, string $mailbox = 'INBOX', string $savePath = 'var/attachments'): array
    {
        $message = $this->fetchMessageByUid($uid, $mailbox);

        if (!$message->hasAttachments()) {
            return [];
        }

        $dir = rtrim($savePath, '/') . '/' . $uid;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $result = [];

        /** @var Attachment $attachment */
        foreach ($message->getAttachments() as $attachment) {
            $filename = (string) ($attachment->name ?: ('unnamed_' . $attachment->part_number));
            $filePath = $dir . '/' . $filename;

            file_put_contents($filePath, (string) $attachment->content);

            $result[] = [
                'filename' => $filename,
                'size' => (int) $attachment->size,
                'mime_type' => $attachment->getMimeType() ?? 'application/octet-stream',
                'saved_path' => $filePath,
            ];
        }

        return $result;
    }

    /**
     * @throws MailboxNotFoundException
     * @throws ImapConnectionException
     */
    private function getFolder(string $mailbox): Folder
    {
        try {
            $folder = $this->client->getFolderByPath($mailbox);
        } catch (\Exception $e) {
            throw new ImapConnectionException(
                "IMAP error accessing mailbox '{$mailbox}': {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($folder === null) {
            throw new MailboxNotFoundException("Mailbox '{$mailbox}' not found");
        }

        return $folder;
    }

    /**
     * @throws MailboxNotFoundException
     * @throws MessageNotFoundException
     */
    private function fetchMessageByUid(int $uid, string $mailbox): Message
    {
        $folder = $this->getFolder($mailbox);

        try {
            return $folder->messages()->getMessageByUid($uid);
        } catch (\Throwable $e) {
            throw new MessageNotFoundException("Message UID {$uid} not found in '{$mailbox}'", 0, $e);
        }
    }

    /**
     * @param list<string>|null $fields
     *
     * @return array<string, mixed>
     */
    private function formatMessageSummary(Message $message, array|null $fields = null): array
    {
        $all = [
            'uid' => $message->uid,
            'from' => $this->attributeToString($message->from),
            'to' => $this->attributeToString($message->to),
            'subject' => (string) $message->subject,
            'date' => $this->formatDate($message),
            'seen' => $message->hasFlag('Seen'),
        ];

        if ($fields === null) {
            return $all;
        }

        $filtered = ['uid' => $all['uid']];

        foreach ($fields as $field) {
            if ($field !== 'uid' && array_key_exists($field, $all)) {
                $filtered[$field] = $all[$field];
            }
        }

        return $filtered;
    }

    private function attributeToString(mixed $attribute): string
    {
        if ($attribute === null) {
            return '';
        }

        if ($attribute instanceof \Webklex\PHPIMAP\Attribute) {
            return $attribute->toString();
        }

        if (\is_string($attribute)) {
            return $attribute;
        }

        if (\is_object($attribute) && method_exists($attribute, '__toString')) {
            return (string) $attribute;
        }

        return '';
    }

    private function formatDate(Message $message): string
    {
        /** @var \Webklex\PHPIMAP\Attribute|null $dateAttr */
        $dateAttr = $message->date;

        if ($dateAttr === null) {
            return '';
        }

        $first = $dateAttr->first();

        if ($first instanceof \DateTimeInterface) {
            return $first->format('Y-m-d H:i:s');
        }

        return (string) $dateAttr;
    }

    private function buildCombinedBody(Message $message): string
    {
        $parts = [];
        $text = $message->getTextBody();
        $html = $message->getHTMLBody();

        if ($text !== '') {
            $parts[] = "--- TEXT ---\n{$text}";
        }

        if ($html !== '') {
            $parts[] = "--- HTML ---\n{$html}";
        }

        return implode("\n\n", $parts);
    }
}
