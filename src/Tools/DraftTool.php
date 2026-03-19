<?php

declare(strict_types=1);

namespace App\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Imap\ImapConnectionFactory;
use App\Smtp\SmtpConnectionFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\Mime\Email;

class DraftTool
{
	public function __construct(
		private readonly ImapConnectionFactory $imapFactory,
		private readonly SmtpConnectionFactory $smtpFactory,
	) {}

	/**
	 * @param list<string>|null $to
	 * @param list<string>|null $cc
	 * @param list<string>|null $bcc
	 *
	 * @return array{success?: bool, error?: bool, message: string}
	 */
	#[McpTool(
		name: 'save_draft',
		description: 'Save an email draft to the Drafts folder via IMAP APPEND',
		annotations: new ToolAnnotations(destructiveHint: false),
	)]
	public function saveDraft(
		#[Schema(description: 'Recipient email addresses', items: ['type' => 'string'])]
		array|null $to = null,
		string|null $subject = null,
		#[Schema(description: 'Plain text body')]
		string|null $body = null,
		#[Schema(description: 'CC recipients', items: ['type' => 'string'])]
		array|null $cc = null,
		#[Schema(description: 'BCC recipients', items: ['type' => 'string'])]
		array|null $bcc = null,
		#[Schema(description: 'HTML body')]
		string|null $body_html = null,
		#[Schema(description: 'Drafts folder name')]
		string $draft_folder = 'Drafts',
	): array {
		$config = $this->smtpFactory->getConfig();

		$email = (new Email())->from($config->from);

		if ($to !== null) {
			foreach ($to as $addr) {
				$email->addTo($addr);
			}
		}

		if ($cc !== null) {
			foreach ($cc as $addr) {
				$email->addCc($addr);
			}
		}

		if ($bcc !== null) {
			foreach ($bcc as $addr) {
				$email->addBcc($addr);
			}
		}

		if ($subject !== null) {
			$email->subject($subject);
		}

		if ($body !== null) {
			$email->text($body);
		}

		if ($body_html !== null) {
			$email->html($body_html);
		}

		$rawMessage = $email->toString();
		$connection = null;

		try {
			$connection = $this->imapFactory->create();
			$connection->appendMessage($rawMessage, $draft_folder, ['\\Draft', '\\Seen']);

			return ['success' => true, 'message' => "Draft saved to '{$draft_folder}'"];
		} catch (MailboxNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'Connection failed: ' . $e->getMessage()];
		} finally {
			$connection?->disconnect();
		}
	}
}
