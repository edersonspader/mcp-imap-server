<?php

declare(strict_types=1);

namespace App\Tools;

use App\Exception\ImapConnectionException;
use App\Exception\MailboxNotFoundException;
use App\Exception\MessageNotFoundException;
use App\Exception\SmtpConnectionException;
use App\Exception\SmtpSendException;
use App\Imap\ImapConnectionFactory;
use App\Smtp\EmailComposer;
use App\Smtp\SmtpConnectionFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

class SendTool
{
	private readonly EmailComposer $composer;

	public function __construct(
		private readonly SmtpConnectionFactory $smtpFactory,
		private readonly ImapConnectionFactory $imapFactory,
	) {
		$this->composer = new EmailComposer($this->smtpFactory->getConfig());
	}

	/**
	 * @param list<string>      $to
	 * @param list<string>|null $cc
	 * @param list<string>|null $bcc
	 * @param list<string>|null $attachments
	 *
	 * @return array{success?: bool, error?: bool, message: string}
	 */
	#[McpTool(
		name: 'send_email',
		description: 'Compose and send a new email. This action is irreversible. REQUIRES USER CONFIRMATION.',
		annotations: new ToolAnnotations(destructiveHint: true, openWorldHint: true),
	)]
	public function sendEmail(
		#[Schema(description: 'Recipient email addresses', items: ['type' => 'string'])]
		array $to,
		string $subject,
		#[Schema(description: 'Plain text body')]
		string $body,
		#[Schema(description: 'Sender address (optional — resolved from allowed list or default)')]
		string|null $from = null,
		#[Schema(description: 'CC recipients', items: ['type' => 'string'])]
		array|null $cc = null,
		#[Schema(description: 'BCC recipients', items: ['type' => 'string'])]
		array|null $bcc = null,
		#[Schema(description: 'Reply-To address', format: 'email')]
		string|null $reply_to = null,
		#[Schema(description: 'HTML body (optional — sent alongside plain text)')]
		string|null $body_html = null,
		#[Schema(description: 'File paths to attach', items: ['type' => 'string'])]
		array|null $attachments = null,
		#[Schema(description: 'Mailbox to save sent copy (empty string to skip)')]
		string $save_to_sent = 'Sent',
	): array {
		try {
			$email = $this->composer->compose(
				to: $to,
				subject: $subject,
				body: $body,
				from: $from,
				cc: $cc,
				bcc: $bcc,
				replyTo: $reply_to,
				bodyHtml: $body_html,
				attachments: $attachments,
			);
		} catch (\InvalidArgumentException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		}

		$smtp = null;

		try {
			$smtp = $this->smtpFactory->create();
			$smtp->send($email);
		} catch (SmtpSendException | SmtpConnectionException $e) {
			return ['error' => true, 'message' => 'SMTP error: ' . $e->getMessage()];
		} finally {
			$smtp?->disconnect();
		}

		if ($save_to_sent !== '') {
			$this->saveToMailbox($email->toString(), $save_to_sent, ['\\Seen']);
		}

		$recipients = implode(', ', $to);

		return ['success' => true, 'message' => "Email sent to {$recipients}"];
	}

	/**
	 * @param list<string>|null $attachments
	 *
	 * @return array{success?: bool, error?: bool, message: string}
	 */
	#[McpTool(
		name: 'reply_email',
		description: 'Reply to an existing email with proper threading (In-Reply-To, References). This action is irreversible. REQUIRES USER CONFIRMATION.',
		annotations: new ToolAnnotations(destructiveHint: true, openWorldHint: true),
	)]
	public function replyEmail(
		#[Schema(description: 'Message UID to reply to')]
		int $uid,
		#[Schema(description: 'Plain text reply body')]
		string $body,
		string $mailbox = 'INBOX',
		#[Schema(description: 'Reply to all recipients')]
		bool $reply_all = false,
		#[Schema(description: 'Sender address (optional — auto-detected from original To if in allowed list)')]
		string|null $from = null,
		#[Schema(description: 'HTML reply body (optional)')]
		string|null $body_html = null,
		#[Schema(description: 'File paths to attach', items: ['type' => 'string'])]
		array|null $attachments = null,
		#[Schema(description: 'Mailbox to save sent copy (empty string to skip)')]
		string $save_to_sent = 'Sent',
	): array {
		$imap = null;

		try {
			$imap = $this->imapFactory->create();
			$headers = $imap->getMessageHeaders($uid, $mailbox);
			$message = $imap->readMessage($uid, $mailbox, 'text', 0);
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'IMAP error: ' . $e->getMessage()];
		} finally {
			$imap?->disconnect();
		}

		try {
			$email = $this->composer->composeReply(
				originalHeaders: $headers,
				originalBody: $message['body'],
				body: $body,
				replyAll: $reply_all,
				from: $from,
				bodyHtml: $body_html,
				attachments: $attachments,
			);
		} catch (\InvalidArgumentException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		}

		$smtp = null;

		try {
			$smtp = $this->smtpFactory->create();
			$smtp->send($email);
		} catch (SmtpSendException | SmtpConnectionException $e) {
			return ['error' => true, 'message' => 'SMTP error: ' . $e->getMessage()];
		} finally {
			$smtp?->disconnect();
		}

		$this->setAnsweredFlag($uid, $mailbox);

		if ($save_to_sent !== '') {
			$this->saveToMailbox($email->toString(), $save_to_sent, ['\\Seen']);
		}

		$replyType = $reply_all ? 'Reply-all' : 'Reply';

		return ['success' => true, 'message' => "{$replyType} sent for message UID {$uid}"];
	}

	/**
	 * @param list<string>      $to
	 * @param list<string>|null $attachments
	 *
	 * @return array{success?: bool, error?: bool, message: string}
	 */
	#[McpTool(
		name: 'forward_email',
		description: 'Forward an existing email with its attachments to new recipients. This action is irreversible. REQUIRES USER CONFIRMATION.',
		annotations: new ToolAnnotations(destructiveHint: true, openWorldHint: true),
	)]
	public function forwardEmail(
		#[Schema(description: 'Message UID to forward')]
		int $uid,
		#[Schema(description: 'Recipient email addresses', items: ['type' => 'string'])]
		array $to,
		string $mailbox = 'INBOX',
		#[Schema(description: 'Additional message to prepend (optional)')]
		string|null $body = null,
		#[Schema(description: 'Sender address (optional — auto-detected from original To if in allowed list)')]
		string|null $from = null,
		#[Schema(description: 'HTML body to prepend (optional)')]
		string|null $body_html = null,
		#[Schema(description: 'Additional file paths to attach', items: ['type' => 'string'])]
		array|null $attachments = null,
		#[Schema(description: 'Mailbox to save sent copy (empty string to skip)')]
		string $save_to_sent = 'Sent',
	): array {
		$imap = null;
		$originalAttachments = [];

		try {
			$imap = $this->imapFactory->create();
			$headers = $imap->getMessageHeaders($uid, $mailbox);
			$message = $imap->readMessage($uid, $mailbox, 'text', 0);
			$originalAttachments = $imap->fetchAttachments($uid, $mailbox);
		} catch (MailboxNotFoundException | MessageNotFoundException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		} catch (ImapConnectionException $e) {
			return ['error' => true, 'message' => 'IMAP error: ' . $e->getMessage()];
		} finally {
			$imap?->disconnect();
		}

		try {
			$email = $this->composer->composeForward(
				originalHeaders: $headers,
				originalBody: $message['body'],
				originalAttachments: $originalAttachments,
				to: $to,
				body: $body,
				from: $from,
				bodyHtml: $body_html,
				attachments: $attachments,
			);
		} catch (\InvalidArgumentException $e) {
			return ['error' => true, 'message' => $e->getMessage()];
		}

		$smtp = null;

		try {
			$smtp = $this->smtpFactory->create();
			$smtp->send($email);
		} catch (SmtpSendException | SmtpConnectionException $e) {
			return ['error' => true, 'message' => 'SMTP error: ' . $e->getMessage()];
		} finally {
			$smtp?->disconnect();
		}

		if ($save_to_sent !== '') {
			$this->saveToMailbox($email->toString(), $save_to_sent, ['\\Seen']);
		}

		$recipients = implode(', ', $to);

		return ['success' => true, 'message' => "Message UID {$uid} forwarded to {$recipients}"];
	}

	/**
	 * @param list<string> $flags
	 */
	private function saveToMailbox(string $rawMessage, string $mailbox, array $flags): void
	{
		$imap = null;

		try {
			$imap = $this->imapFactory->create();
			$imap->appendMessage($rawMessage, $mailbox, $flags);
		} catch (ImapConnectionException | MailboxNotFoundException) {
			// Saving to Sent/Drafts is best-effort — do not fail the send
		} finally {
			$imap?->disconnect();
		}
	}

	private function setAnsweredFlag(int $uid, string $mailbox): void
	{
		$imap = null;

		try {
			$imap = $this->imapFactory->create();
			$imap->setFlag($uid, 'Answered', $mailbox);
		} catch (ImapConnectionException | MailboxNotFoundException | MessageNotFoundException) {
			// Setting Answered flag is best-effort
		} finally {
			$imap?->disconnect();
		}
	}
}
