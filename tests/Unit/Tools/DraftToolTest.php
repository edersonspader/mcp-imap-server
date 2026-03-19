<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Imap\ImapConnectionFactory;
use App\Imap\ImapConnectionInterface;
use App\Smtp\SmtpConfig;
use App\Smtp\SmtpConnectionFactory;
use App\Tools\DraftTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(DraftTool::class)]
final class DraftToolTest extends TestCase
{
	private ImapConnectionInterface&Stub $imap;
	private SmtpConfig $config;
	private DraftTool $tool;

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

		$imapFactory = $this->createStub(ImapConnectionFactory::class);
		$imapFactory->method('create')->willReturn($this->imap);

		$smtpFactory = $this->createStub(SmtpConnectionFactory::class);
		$smtpFactory->method('getConfig')->willReturn($this->config);

		$this->tool = new DraftTool($imapFactory, $smtpFactory);
	}

	#[Test]
	public function it_saves_draft(): void
	{
		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->expects(self::once())
			->method('appendMessage')
			->with(
				self::callback(static fn(mixed $msg): bool => \is_string($msg) && $msg !== ''),
				'Drafts',
				['\\Draft', '\\Seen'],
			);
		$imap->expects(self::once())->method('disconnect');

		$tool = $this->buildTool($imap);

		$result = $tool->saveDraft(
			to: ['recipient@example.com'],
			subject: 'Draft Subject',
			body: 'Draft body',
		);

		self::assertTrue($result['success'] ?? false);
		self::assertStringContainsString('Drafts', $result['message']);
	}

	#[Test]
	public function it_saves_draft_with_all_fields(): void
	{
		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->expects(self::once())->method('appendMessage');

		$tool = $this->buildTool($imap);

		$result = $tool->saveDraft(
			to: ['to@example.com'],
			subject: 'Full draft',
			body: 'Text body',
			cc: ['cc@example.com'],
			bcc: ['bcc@example.com'],
			body_html: '<p>HTML body</p>',
		);

		self::assertTrue($result['success'] ?? false);
	}

	#[Test]
	public function it_saves_draft_with_custom_folder(): void
	{
		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->expects(self::once())
			->method('appendMessage')
			->with(
				self::callback(static fn(mixed $msg): bool => \is_string($msg) && $msg !== ''),
				'MyDrafts',
				['\\Draft', '\\Seen'],
			);

		$tool = $this->buildTool($imap);

		$result = $tool->saveDraft(
			subject: 'Test',
			body: 'Body',
			draft_folder: 'MyDrafts',
		);

		self::assertTrue($result['success'] ?? false);
		self::assertStringContainsString('MyDrafts', $result['message']);
	}

	#[Test]
	public function it_saves_minimal_draft(): void
	{
		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->expects(self::once())->method('appendMessage');

		$tool = $this->buildTool($imap);

		$result = $tool->saveDraft(subject: 'Just a subject', body: ' ');

		self::assertTrue($result['success'] ?? false);
	}

	#[Test]
	public function it_returns_error_on_mailbox_not_found(): void
	{
		$this->imap->method('appendMessage')
			->willThrowException(new MailboxNotFoundException("Mailbox 'Ghost' not found"));

		$result = $this->tool->saveDraft(
			subject: 'Test',
			body: 'Body',
			draft_folder: 'Ghost',
		);

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('Ghost', $result['message']);
	}

	#[Test]
	public function it_returns_error_on_connection_failure(): void
	{
		$imap = $this->createMock(ImapConnectionInterface::class);
		$imap->expects(self::never())->method('disconnect');

		$imapFactory = $this->createStub(ImapConnectionFactory::class);
		$imapFactory->method('create')
			->willThrowException(new ImapConnectionException('Timeout'));

		$smtpFactory = $this->createStub(SmtpConnectionFactory::class);
		$smtpFactory->method('getConfig')->willReturn($this->config);

		$tool = new DraftTool($imapFactory, $smtpFactory);
		$result = $tool->saveDraft(subject: 'Test', body: 'Body');

		self::assertTrue($result['error'] ?? false);
		self::assertStringContainsString('Connection failed', $result['message']);
	}

	private function buildTool(ImapConnectionInterface|null $imap = null): DraftTool
	{
		$imapFactory = $this->createStub(ImapConnectionFactory::class);
		$imapFactory->method('create')->willReturn($imap ?? $this->imap);

		$smtpFactory = $this->createStub(SmtpConnectionFactory::class);
		$smtpFactory->method('getConfig')->willReturn($this->config);

		return new DraftTool($imapFactory, $smtpFactory);
	}
}
