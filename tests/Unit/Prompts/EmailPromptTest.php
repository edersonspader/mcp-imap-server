<?php

declare(strict_types=1);

namespace App\Tests\Unit\Prompts;

use App\Prompts\EmailPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailPrompt::class)]
final class EmailPromptTest extends TestCase
{
	private EmailPrompt $prompt;

	protected function setUp(): void
	{
		$this->prompt = new EmailPrompt();
	}

	#[Test]
	public function it_generates_summarize_email_prompt(): void
	{
		$result = $this->prompt->summarizeEmail('Hello, this is a test email.', 'en');

		self::assertCount(2, $result);
		self::assertSame('assistant', $result[0]['role']);
		self::assertStringContainsString('email analyst', $result[0]['content']);
		self::assertSame('user', $result[1]['role']);
		self::assertStringContainsString('Hello, this is a test email.', $result[1]['content']);
		self::assertStringContainsString('en', $result[1]['content']);
	}

	#[Test]
	public function it_generates_summarize_email_with_default_language(): void
	{
		$result = $this->prompt->summarizeEmail('Test content');

		self::assertStringContainsString('pt-br', $result[1]['content']);
	}

	#[Test]
	public function it_generates_draft_reply_prompt(): void
	{
		$result = $this->prompt->draftReply('Original email content', 'casual', 'es');

		self::assertCount(2, $result);
		self::assertSame('assistant', $result[0]['role']);
		self::assertStringContainsString('email writer', $result[0]['content']);
		self::assertSame('user', $result[1]['role']);
		self::assertStringContainsString('casual', $result[1]['content']);
		self::assertStringContainsString('es', $result[1]['content']);
		self::assertStringContainsString('Original email content', $result[1]['content']);
	}

	#[Test]
	public function it_generates_draft_reply_with_defaults(): void
	{
		$result = $this->prompt->draftReply('Some email');

		self::assertStringContainsString('professional', $result[1]['content']);
		self::assertStringContainsString('pt-br', $result[1]['content']);
	}

	#[Test]
	public function it_generates_categorize_inbox_prompt(): void
	{
		$emailList = "1. Meeting tomorrow\n2. Newsletter\n3. Invoice attached";
		$result = $this->prompt->categorizeInbox($emailList);

		self::assertCount(2, $result);
		self::assertSame('assistant', $result[0]['role']);
		self::assertStringContainsString('organizer', $result[0]['content']);
		self::assertSame('user', $result[1]['role']);
		self::assertStringContainsString($emailList, $result[1]['content']);
	}
}
