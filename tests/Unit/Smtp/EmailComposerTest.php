<?php

declare(strict_types=1);

namespace App\Tests\Unit\Smtp;

use App\Smtp\EmailComposer;
use App\Smtp\SmtpConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

#[CoversClass(EmailComposer::class)]
final class EmailComposerTest extends TestCase
{
	private SmtpConfig $config;
	private EmailComposer $composer;

	protected function setUp(): void
	{
		$this->config = new SmtpConfig(
			host: 'smtp.test.com',
			port: 587,
			user: 'user@test.com',
			password: 'secret',
			from: 'default@test.com',
			allowedFrom: ['alias@test.com'],
		);

		$this->composer = new EmailComposer($this->config);
	}

	#[Test]
	public function it_composes_simple_email(): void
	{
		$email = $this->composer->compose(
			to: ['recipient@example.com'],
			subject: 'Hello',
			body: 'World',
		);

		self::assertSame('default@test.com', $email->getFrom()[0]->getAddress());
		self::assertSame('recipient@example.com', $email->getTo()[0]->getAddress());
		self::assertSame('Hello', $email->getSubject());
		self::assertSame('World', $email->getTextBody());
	}

	#[Test]
	public function it_composes_email_with_all_fields(): void
	{
		$email = $this->composer->compose(
			to: ['to@example.com'],
			subject: 'Full',
			body: 'Text body',
			from: 'alias@test.com',
			cc: ['cc@example.com'],
			bcc: ['bcc@example.com'],
			replyTo: 'reply@example.com',
			bodyHtml: '<p>HTML body</p>',
		);

		self::assertSame('alias@test.com', $email->getFrom()[0]->getAddress());
		self::assertSame('cc@example.com', $email->getCc()[0]->getAddress());
		self::assertSame('bcc@example.com', $email->getBcc()[0]->getAddress());
		self::assertSame('reply@example.com', $email->getReplyTo()[0]->getAddress());
		self::assertSame('<p>HTML body</p>', $email->getHtmlBody());
	}

	#[Test]
	public function it_throws_on_invalid_email_address(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid email address');

		$this->composer->compose(
			to: ['not-an-email'],
			subject: 'Test',
			body: 'Body',
		);
	}

	#[Test]
	public function it_throws_on_too_many_recipients(): void
	{
		$recipients = array_map(
			static fn(int $i): string => "user{$i}@example.com",
			range(1, 21),
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Too many recipients');

		$this->composer->compose(
			to: $recipients,
			subject: 'Test',
			body: 'Body',
		);
	}

	#[Test]
	public function it_composes_reply(): void
	{
		$headers = $this->makeHeaders();

		$email = $this->composer->composeReply(
			originalHeaders: $headers,
			originalBody: 'Original message',
			body: 'My reply',
		);

		self::assertSame('Re: Test Subject', $email->getSubject());
		self::assertSame('sender@example.com', $email->getTo()[0]->getAddress());
		$textBody = $email->getTextBody();
		self::assertIsString($textBody);
		self::assertStringContainsString('My reply', $textBody);
		self::assertStringContainsString('> Original message', $textBody);
	}

	#[Test]
	public function it_does_not_double_re_prefix(): void
	{
		$headers = $this->makeHeaders(subject: 'Re: Already replied');

		$email = $this->composer->composeReply(
			originalHeaders: $headers,
			originalBody: 'Text',
			body: 'Reply',
		);

		self::assertSame('Re: Already replied', $email->getSubject());
	}

	#[Test]
	public function it_uses_reply_to_header_when_present(): void
	{
		$headers = $this->makeHeaders(replyTo: 'replyto@example.com');

		$email = $this->composer->composeReply(
			originalHeaders: $headers,
			originalBody: 'Body',
			body: 'Reply',
		);

		self::assertSame('replyto@example.com', $email->getTo()[0]->getAddress());
	}

	#[Test]
	public function it_sets_in_reply_to_and_references(): void
	{
		$headers = $this->makeHeaders(
			messageId: '<msg123@example.com>',
			inReplyTo: '<prev@example.com>',
		);

		$email = $this->composer->composeReply(
			originalHeaders: $headers,
			originalBody: 'Body',
			body: 'Reply',
		);

		$inReplyTo = $email->getHeaders()->get('In-Reply-To')?->getBody();
		$references = $email->getHeaders()->get('References')?->getBody();

		self::assertSame('<msg123@example.com>', $inReplyTo);
		self::assertSame('<prev@example.com> <msg123@example.com>', $references);
	}

	#[Test]
	public function it_composes_reply_all(): void
	{
		$headers = $this->makeHeaders(
			to: 'default@test.com, other@example.com',
			cc: 'cc@example.com',
		);

		$email = $this->composer->composeReply(
			originalHeaders: $headers,
			originalBody: 'Body',
			body: 'Reply',
			replyAll: true,
		);

		$toAddrs = array_map(
			static fn(\Symfony\Component\Mime\Address $a): string => $a->getAddress(),
			$email->getTo(),
		);
		$ccAddrs = array_map(
			static fn(\Symfony\Component\Mime\Address $a): string => $a->getAddress(),
			$email->getCc(),
		);

		self::assertContains('sender@example.com', $toAddrs);
		self::assertContains('other@example.com', $toAddrs);
		self::assertContains('cc@example.com', $ccAddrs);
		self::assertNotContains('default@test.com', $toAddrs);
		self::assertNotContains('alias@test.com', $toAddrs);
	}

	#[Test]
	public function it_resolves_sender_from_original_to(): void
	{
		$headers = $this->makeHeaders(to: 'alias@test.com');

		$email = $this->composer->composeReply(
			originalHeaders: $headers,
			originalBody: 'Body',
			body: 'Reply',
		);

		self::assertSame('alias@test.com', $email->getFrom()[0]->getAddress());
	}

	#[Test]
	public function it_composes_reply_with_html(): void
	{
		$headers = $this->makeHeaders();

		$email = $this->composer->composeReply(
			originalHeaders: $headers,
			originalBody: 'Original',
			body: 'Reply text',
			bodyHtml: '<p>Reply HTML</p>',
		);

		$html = $email->getHtmlBody();
		self::assertIsString($html);

		self::assertStringContainsString('<p>Reply HTML</p>', $html);
		self::assertStringContainsString('<blockquote', $html);
	}

	#[Test]
	public function it_composes_forward(): void
	{
		$headers = $this->makeForwardHeaders();

		$email = $this->composer->composeForward(
			originalHeaders: $headers,
			originalBody: 'Original body',
			originalAttachments: [],
			to: ['forward@example.com'],
		);

		self::assertSame('Fwd: Test Subject', $email->getSubject());
		self::assertSame('forward@example.com', $email->getTo()[0]->getAddress());
		$textBody = $email->getTextBody();
		self::assertIsString($textBody);
		self::assertStringContainsString('Forwarded message', $textBody);
		self::assertStringContainsString('Original body', $textBody);
	}

	#[Test]
	public function it_does_not_double_fwd_prefix(): void
	{
		$headers = $this->makeForwardHeaders(subject: 'Fwd: Already forwarded');

		$email = $this->composer->composeForward(
			originalHeaders: $headers,
			originalBody: 'Text',
			originalAttachments: [],
			to: ['forward@example.com'],
		);

		self::assertSame('Fwd: Already forwarded', $email->getSubject());
	}

	#[Test]
	public function it_composes_forward_with_prepended_body(): void
	{
		$headers = $this->makeForwardHeaders();

		$email = $this->composer->composeForward(
			originalHeaders: $headers,
			originalBody: 'Original',
			originalAttachments: [],
			to: ['forward@example.com'],
			body: 'See below',
		);

		$text = $email->getTextBody();
		self::assertIsString($text);

		self::assertStringContainsString('See below', $text);
		self::assertStringContainsString('Original', $text);
	}

	#[Test]
	public function it_composes_forward_with_html(): void
	{
		$headers = $this->makeForwardHeaders();

		$email = $this->composer->composeForward(
			originalHeaders: $headers,
			originalBody: 'Original text',
			originalAttachments: [],
			to: ['forward@example.com'],
			bodyHtml: '<p>FYI</p>',
		);

		$html = $email->getHtmlBody();
		self::assertIsString($html);

		self::assertStringContainsString('<p>FYI</p>', $html);
		self::assertStringContainsString('Forwarded message', $html);
	}

	#[Test]
	public function it_throws_on_invalid_forward_recipient(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid email address');

		$this->composer->composeForward(
			originalHeaders: $this->makeForwardHeaders(),
			originalBody: 'Body',
			originalAttachments: [],
			to: ['not-valid'],
		);
	}

	#[Test]
	public function it_enforces_recipient_limit_on_forward(): void
	{
		$recipients = array_map(
			static fn(int $i): string => "user{$i}@example.com",
			range(1, 21),
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Too many recipients');

		$this->composer->composeForward(
			originalHeaders: $this->makeForwardHeaders(),
			originalBody: 'Body',
			originalAttachments: [],
			to: $recipients,
		);
	}

	#[Test]
	public function it_validates_cc_addresses(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid email address');

		$this->composer->compose(
			to: ['valid@example.com'],
			subject: 'Test',
			body: 'Body',
			cc: ['bad-cc'],
		);
	}

	#[Test]
	public function it_validates_bcc_addresses(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid email address');

		$this->composer->compose(
			to: ['valid@example.com'],
			subject: 'Test',
			body: 'Body',
			bcc: ['bad-bcc'],
		);
	}

	#[Test]
	public function it_validates_reply_to_address(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid email address');

		$this->composer->compose(
			to: ['valid@example.com'],
			subject: 'Test',
			body: 'Body',
			replyTo: 'bad-reply-to',
		);
	}

	#[Test]
	public function it_counts_to_cc_bcc_for_recipient_limit(): void
	{
		$to = array_map(static fn(int $i): string => "to{$i}@example.com", range(1, 8));
		$cc = array_map(static fn(int $i): string => "cc{$i}@example.com", range(1, 7));
		$bcc = array_map(static fn(int $i): string => "bcc{$i}@example.com", range(1, 6));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Too many recipients');

		$this->composer->compose(
			to: $to,
			subject: 'Test',
			body: 'Body',
			cc: $cc,
			bcc: $bcc,
		);
	}

	#[Test]
	public function it_throws_on_missing_attachment_file(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Attachment file not found');

		$this->composer->compose(
			to: ['valid@example.com'],
			subject: 'Test',
			body: 'Body',
			attachments: ['/nonexistent/file.txt'],
		);
	}

	/**
	 * @return array{from: string, to: string, cc: string, reply_to: string, subject: string, message_id: string, in_reply_to: string, date: string}
	 */
	private function makeHeaders(
		string $from = 'sender@example.com',
		string $to = 'default@test.com',
		string $cc = '',
		string $replyTo = '',
		string $subject = 'Test Subject',
		string $messageId = '<msg@example.com>',
		string $inReplyTo = '',
		string $date = '2026-01-15 10:00:00',
	): array {
		return [
			'from' => $from,
			'to' => $to,
			'cc' => $cc,
			'reply_to' => $replyTo,
			'subject' => $subject,
			'message_id' => $messageId,
			'in_reply_to' => $inReplyTo,
			'date' => $date,
		];
	}

	/**
	 * @return array{from: string, to: string, cc: string, subject: string, message_id: string, date: string}
	 */
	private function makeForwardHeaders(
		string $from = 'sender@example.com',
		string $to = 'default@test.com',
		string $cc = '',
		string $subject = 'Test Subject',
		string $messageId = '<msg@example.com>',
		string $date = '2026-01-15 10:00:00',
	): array {
		return [
			'from' => $from,
			'to' => $to,
			'cc' => $cc,
			'subject' => $subject,
			'message_id' => $messageId,
			'date' => $date,
		];
	}
}
