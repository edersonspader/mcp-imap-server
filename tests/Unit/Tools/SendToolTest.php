<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Exception\MessageNotFoundException;
use App\Exception\SmtpSendException;
use App\Imap\ImapConnectionFactory;
use App\Imap\ImapConnectionInterface;
use App\Smtp\SmtpConfig;
use App\Smtp\SmtpConnectionFactory;
use App\Smtp\SmtpConnectionInterface;
use App\Tools\SendTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendTool::class)]
final class SendToolTest extends TestCase
{
	private ImapConnectionInterface&Stub $imap;
	private SmtpConnectionInterface&Stub $smtp;
	private SmtpConfig $config;
	private ImapConnectionFactory&Stub $imapFactory;
	private SmtpConnectionFactory&Stub $smtpFactory;
	private SendTool $tool;

	protected function setUp(): void
	{
		$this->config = new SmtpConfig(
			host: 'smtp.test.com',
			port: 587,
			user: 'user@test.com',
			password: 'secret',
			from: 'default@test.com',
		);

		$this->imap = $this->createStub(ImapConnectionInterface::class);

		$this->imapFactory = $this->createStub(ImapConnectionFactory::class);
		$this->imapFactory->method('create')->willReturn($this->imap);

		$this->smtp = $this->createStub(SmtpConnectionInterface::class);

		$this->smtpFactory = $this->createStub(SmtpConnectionFactory::class);
		$this->smtpFactory->method('getConfig')->willReturn($this->config);
		$this->smtpFactory->method('create')->willReturn($this->smtp);

		$this->tool = new SendTool($this->smtpFactory, $this->imapFactory);
	}

	#[Test]
	public function it_sends_email(): void
	{
		$smtp = $this->createMock(SmtpConnectionInterface::class);
		$smtp->expects(self::once())->method('send');

		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->expects(self::once())->method('appendMessage');

		$tool = $this->buildTool($imap, $smtp);

		$result = $tool->sendEmail(
			to: ['recipient@example.com'],
			subject: 'Hello',
			body: 'World',
		);

		self::assertTrue($result['success'] ?? false);
		self::assertStringContainsString('recipient@example.com', $result['message']);
	}

	#[Test]
	public function it_skips_saving_to_sent_when_empty(): void
	{
		$smtp = $this->createMock(SmtpConnectionInterface::class);
		$smtp->expects(self::once())->method('send');

		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->expects(self::never())->method('appendMessage');

		$tool = $this->buildTool($imap, $smtp);

		$result = $tool->sendEmail(
			to: ['recipient@example.com'],
			subject: 'Hello',
			body: 'World',
			save_to_sent: '',
		);

		self::assertTrue($result['success'] ?? false);
	}

	#[Test]
	public function it_returns_error_on_invalid_address(): void
	{
		$result = $this->tool->sendEmail(
			to: ['not-valid'],
			subject: 'Test',
			body: 'Body',
		);

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('Invalid email address', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_smtp_failure(): void
	{
		$this->smtp->method('send')
			->willThrowException(new SmtpSendException('Connection refused'));

		$result = $this->tool->sendEmail(
			to: ['recipient@example.com'],
			subject: 'Hello',
			body: 'World',
		);

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('SMTP error', $result['message']);
	}

	#[Test]
	public function it_replies_to_email(): void
	{
		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->method('getMessageHeaders')->willReturn($this->makeHeaders());
		$imap->method('readMessage')->willReturn([
			'uid' => 42,
			'from' => 'sender@example.com',
			'to' => 'default@test.com',
			'cc' => '',
			'subject' => 'Original',
			'date' => '2026-01-15 10:00:00',
			'body' => 'Original body',
			'has_attachments' => false,
		]);

		$smtp = $this->createMock(SmtpConnectionInterface::class);
		$smtp->expects(self::once())->method('send');

		$imap->expects(self::once())->method('setFlag')->with(42, 'Answered', 'INBOX');

		$tool = $this->buildTool($imap, $smtp);

		$result = $tool->replyEmail(uid: 42, body: 'My reply');

		self::assertTrue($result['success'] ?? false);
		self::assertStringContainsString('Reply sent', $result['message']);
	}

	#[Test]
	public function it_replies_all_to_email(): void
	{
		$this->imap->method('getMessageHeaders')->willReturn($this->makeHeaders(
			to: 'default@test.com, other@example.com',
			cc: 'cc@example.com',
		));
		$this->imap->method('readMessage')->willReturn([
			'uid' => 42,
			'from' => 'sender@example.com',
			'to' => 'default@test.com, other@example.com',
			'cc' => 'cc@example.com',
			'subject' => 'Original',
			'date' => '2026-01-15 10:00:00',
			'body' => 'Original body',
			'has_attachments' => false,
		]);

		$smtp = $this->createMock(SmtpConnectionInterface::class);
		$smtp->expects(self::once())->method('send');

		$tool = $this->buildTool(smtp: $smtp);

		$result = $tool->replyEmail(uid: 42, body: 'Reply all', reply_all: true);

		self::assertTrue($result['success'] ?? false);
		self::assertStringContainsString('Reply-all sent', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_message_not_found_for_reply(): void
	{
		$this->imap->method('getMessageHeaders')
			->willThrowException(new MessageNotFoundException('Message UID 999 not found'));

		$result = $this->tool->replyEmail(uid: 999, body: 'Reply');

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('999', $result['message']);
	}

	#[Test]
	public function it_forwards_email(): void
	{
		$this->imap->method('getMessageHeaders')->willReturn($this->makeHeaders());
		$this->imap->method('readMessage')->willReturn([
			'uid' => 42,
			'from' => 'sender@example.com',
			'to' => 'default@test.com',
			'cc' => '',
			'subject' => 'Original',
			'date' => '2026-01-15 10:00:00',
			'body' => 'Original body',
			'has_attachments' => false,
		]);
		$this->imap->method('fetchAttachments')->willReturn([]);

		$smtp = $this->createMock(SmtpConnectionInterface::class);
		$smtp->expects(self::once())->method('send');

		$tool = $this->buildTool(smtp: $smtp);

		$result = $tool->forwardEmail(uid: 42, to: ['forward@example.com']);

		self::assertTrue($result['success'] ?? false);
		self::assertStringContainsString('forward@example.com', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_imap_failure_for_forward(): void
	{
		$this->imap->method('getMessageHeaders')
			->willThrowException(new ImapConnectionException('Timeout'));

		$result = $this->tool->forwardEmail(uid: 42, to: ['forward@example.com']);

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('IMAP error', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_smtp_failure_for_reply(): void
	{
		$this->imap->method('getMessageHeaders')->willReturn($this->makeHeaders());
		$this->imap->method('readMessage')->willReturn([
			'uid' => 42,
			'from' => 'sender@example.com',
			'to' => 'default@test.com',
			'cc' => '',
			'subject' => 'Original',
			'date' => '2026-01-15 10:00:00',
			'body' => 'Original body',
			'has_attachments' => false,
		]);
		$this->smtp->method('send')
			->willThrowException(new SmtpSendException('Send failed'));

		$result = $this->tool->replyEmail(uid: 42, body: 'Reply');

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('SMTP error', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_mailbox_not_found_for_forward(): void
	{
		$this->imap->method('getMessageHeaders')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Ghost' not found"));

		$result = $this->tool->forwardEmail(uid: 42, to: ['forward@example.com']);

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('Ghost', $result['message']);
	}

	/**
	 * @return array{uid: int, message_id: string, from: string, to: string, cc: string, reply_to: string, subject: string, date: string, in_reply_to: string, seen: bool, flagged: bool, answered: bool}
	 */
	private function makeHeaders(
		string $from = 'sender@example.com',
		string $to = 'default@test.com',
		string $cc = '',
		string $replyTo = '',
		string $subject = 'Original',
	): array {
		return [
			'uid' => 42,
			'message_id' => '<msg42@example.com>',
			'from' => $from,
			'to' => $to,
			'cc' => $cc,
			'reply_to' => $replyTo,
			'subject' => $subject,
			'date' => '2026-01-15 10:00:00',
			'in_reply_to' => '',
			'seen' => true,
			'flagged' => false,
			'answered' => false,
		];
	}

	private function buildTool(
		ImapConnectionInterface|null $imap = null,
		SmtpConnectionInterface|null $smtp = null,
	): SendTool {
		$imapFactory = $this->createStub(ImapConnectionFactory::class);
		$imapFactory->method('create')->willReturn($imap ?? $this->imap);

		$smtpFactory = $this->createStub(SmtpConnectionFactory::class);
		$smtpFactory->method('getConfig')->willReturn($this->config);
		$smtpFactory->method('create')->willReturn($smtp ?? $this->smtp);

		return new SendTool($smtpFactory, $imapFactory);
	}
}
