<?php

declare(strict_types=1);

namespace App\Prompts;

use Mcp\Capability\Attribute\CompletionProvider;
use Mcp\Capability\Attribute\McpPrompt;

class EmailPrompt
{
	/** @return list<array{role: string, content: string}> */
	#[McpPrompt(name: 'summarize_email', description: 'Summarize an email content')]
	public function summarizeEmail(
		string $email_content,
		#[CompletionProvider(values: ['pt-br', 'en', 'es', 'fr', 'de'])]
		string $language = 'pt-br',
	): array {
		return [
			[
				'role' => 'assistant',
				'content' => 'You are an expert email analyst. Summarize emails clearly and concisely.',
			],
			[
				'role' => 'user',
				'content' => "Summarize the following email in {$language}. Include: main topic, key points, action items (if any), and urgency level.\n\n---\n\n{$email_content}",
			],
		];
	}

	/** @return list<array{role: string, content: string}> */
	#[McpPrompt(name: 'draft_reply', description: 'Draft a reply to an email')]
	public function draftReply(
		string $email_content,
		#[CompletionProvider(values: ['professional', 'casual', 'formal', 'friendly'])]
		string $tone = 'professional',
		#[CompletionProvider(values: ['pt-br', 'en', 'es', 'fr', 'de'])]
		string $language = 'pt-br',
	): array {
		return [
			[
				'role' => 'assistant',
				'content' => "You are a professional email writer. Draft replies that are clear, appropriate, and match the requested tone.",
			],
			[
				'role' => 'user',
				'content' => "Draft a {$tone} reply in {$language} to the following email:\n\n---\n\n{$email_content}",
			],
		];
	}

	/** @return list<array{role: string, content: string}> */
	#[McpPrompt(name: 'categorize_inbox', description: 'Categorize a list of inbox messages')]
	public function categorizeInbox(string $email_list): array
	{
		return [
			[
				'role' => 'assistant',
				'content' => 'You are an inbox organizer. Categorize emails into actionable groups.',
			],
			[
				'role' => 'user',
				'content' => "Categorize the following inbox messages into groups (e.g., Urgent, Action Required, FYI, Newsletters, Spam). For each message, provide the category and a brief reason.\n\n---\n\n{$email_list}",
			],
		];
	}
}
