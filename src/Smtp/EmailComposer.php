<?php

declare(strict_types=1);

namespace App\Smtp;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailComposer
{
	private const int MAX_RECIPIENTS = 20;

	public function __construct(
		private readonly SmtpConfig $config,
	) {}

	/**
	 * @param list<string>      $to
	 * @param list<string>|null $cc
	 * @param list<string>|null $bcc
	 * @param list<string>|null $attachments File paths on disk
	 */
	public function compose(
		array $to,
		string $subject,
		string $body,
		string|null $from = null,
		string|null $fromName = null,
		array|null $cc = null,
		array|null $bcc = null,
		string|null $replyTo = null,
		string|null $bodyHtml = null,
		array|null $attachments = null,
	): Email {
		$resolvedFrom = $this->config->resolveFrom($from, $fromName);

		$this->validateAddresses($to);

		if ($cc !== null) {
			$this->validateAddresses($cc);
		}

		if ($bcc !== null) {
			$this->validateAddresses($bcc);
		}

		if ($replyTo !== null) {
			$this->validateAddresses([$replyTo]);
		}

		$this->enforceRecipientLimit($to, $cc ?? [], $bcc ?? []);

		$email = (new Email())
			->from($resolvedFrom)
			->subject($subject)
			->text($body);

		foreach ($to as $addr) {
			$email->addTo($addr);
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

		if ($replyTo !== null) {
			$email->replyTo($replyTo);
		}

		if ($bodyHtml !== null) {
			$email->html($bodyHtml);
		}

		$this->addAttachments($email, $attachments);

		return $email;
	}

	/**
	 * @param array{from: string, to: string, cc: string, reply_to: string, subject: string, message_id: string, in_reply_to: string, date: string} $originalHeaders
	 * @param list<string>|null $attachments
	 */
	public function composeReply(
		array $originalHeaders,
		string $originalBody,
		string $body,
		bool $replyAll = false,
		string|null $from = null,
		string|null $fromName = null,
		string|null $bodyHtml = null,
		array|null $attachments = null,
	): Email {
		$originalTo = $originalHeaders['to'];
		$resolvedFrom = $this->config->resolveFrom($from, $fromName, $originalTo);

		$replyToAddr = $originalHeaders['reply_to'] !== ''
			? $originalHeaders['reply_to']
			: $originalHeaders['from'];

		$subject = $originalHeaders['subject'];

		if (!str_starts_with(mb_strtolower($subject), 're:')) {
			$subject = 'Re: ' . $subject;
		}

		$quotedBody = $this->quoteText($originalBody, $originalHeaders);

		if ($bodyHtml !== null) {
			$quotedHtml = $this->quoteHtml($originalBody, $originalHeaders);
			$fullHtml = $bodyHtml . "\n\n" . $quotedHtml;
		}

		$email = (new Email())
			->from($resolvedFrom)
			->to($replyToAddr)
			->subject($subject)
			->text($body . "\n\n" . $quotedBody);

		if (isset($fullHtml)) {
			$email->html($fullHtml);
		}

		$messageId = $originalHeaders['message_id'];

		if ($messageId !== '') {
			$email->getHeaders()->addTextHeader('In-Reply-To', $messageId);
			$email->getHeaders()->addTextHeader('References', $this->buildReferences(
				$originalHeaders['in_reply_to'],
				$messageId,
			));
		}

		if ($replyAll) {
			$this->addReplyAllRecipients($email, $originalHeaders, $resolvedFrom->getAddress());
		}

		/** @var list<string> $allTo */
		$allTo = array_map(
			static fn(Address $a): string => $a->getAddress(),
			array_merge($email->getTo(), $email->getCc()),
		);
		$this->validateAddresses($allTo);

		/** @var list<string> $toAddrs */
		$toAddrs = array_map(static fn(Address $a): string => $a->getAddress(), $email->getTo());
		/** @var list<string> $ccAddrs */
		$ccAddrs = array_map(static fn(Address $a): string => $a->getAddress(), $email->getCc());
		/** @var list<string> $bccAddrs */
		$bccAddrs = array_map(static fn(Address $a): string => $a->getAddress(), $email->getBcc());
		$this->enforceRecipientLimit($toAddrs, $ccAddrs, $bccAddrs);

		$this->addAttachments($email, $attachments);

		return $email;
	}

	/**
	 * @param array{from: string, to: string, cc: string, subject: string, message_id: string, date: string} $originalHeaders
	 * @param list<array{filename: string, saved_path: string}> $originalAttachments
	 * @param list<string>                                      $to
	 * @param list<string>|null                                 $attachments Additional file paths
	 */
	public function composeForward(
		array $originalHeaders,
		string $originalBody,
		array $originalAttachments,
		array $to,
		string|null $body = null,
		string|null $from = null,
		string|null $fromName = null,
		string|null $bodyHtml = null,
		array|null $attachments = null,
	): Email {
		$originalTo = $originalHeaders['to'];
		$resolvedFrom = $this->config->resolveFrom($from, $fromName, $originalTo);

		$this->validateAddresses($to);
		$this->enforceRecipientLimit($to, [], []);

		$subject = $originalHeaders['subject'];

		if (!str_starts_with(mb_strtolower($subject), 'fwd:')) {
			$subject = 'Fwd: ' . $subject;
		}

		$forwardHeader = $this->buildForwardHeader($originalHeaders);
		$textBody = ($body ?? '') . "\n\n" . $forwardHeader . $originalBody;

		$email = (new Email())
			->from($resolvedFrom)
			->subject($subject)
			->text($textBody);

		foreach ($to as $addr) {
			$email->addTo($addr);
		}

		if ($bodyHtml !== null) {
			$forwardHeaderHtml = nl2br(htmlspecialchars($forwardHeader, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
			$email->html($bodyHtml . "\n\n" . $forwardHeaderHtml . htmlspecialchars($originalBody, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
		}

		foreach ($originalAttachments as $att) {
			if (is_file($att['saved_path'])) {
				$email->attachFromPath($att['saved_path'], $att['filename']);
			}
		}

		$this->addAttachments($email, $attachments);

		return $email;
	}

	/**
	 * @param list<string> $addresses
	 *
	 * @throws \InvalidArgumentException
	 */
	public function validateAddresses(array $addresses): void
	{
		foreach ($addresses as $address) {
			if (filter_var($address, \FILTER_VALIDATE_EMAIL) === false) {
				throw new \InvalidArgumentException("Invalid email address: '{$address}'");
			}
		}
	}

	/**
	 * @param list<string> $to
	 * @param list<string> $cc
	 * @param list<string> $bcc
	 */
	private function enforceRecipientLimit(array $to, array $cc, array $bcc): void
	{
		$total = \count($to) + \count($cc) + \count($bcc);

		if ($total > self::MAX_RECIPIENTS) {
			throw new \InvalidArgumentException(
				"Too many recipients: {$total} (max " . self::MAX_RECIPIENTS . '). Split into smaller batches.',
			);
		}
	}

	/**
	 * @param array{from: string, date: string} $headers
	 */
	private function quoteText(string $originalBody, array $headers): string
	{
		$date = $headers['date'];
		$from = $headers['from'];
		$header = "On {$date}, {$from} wrote:";
		$quoted = implode("\n", array_map(
			static fn(string $line): string => '> ' . $line,
			explode("\n", $originalBody),
		));

		return $header . "\n" . $quoted;
	}

	/**
	 * @param array{from: string, date: string} $headers
	 */
	private function quoteHtml(string $originalBody, array $headers): string
	{
		$date = htmlspecialchars($headers['date'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
		$from = htmlspecialchars($headers['from'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
		$escapedBody = htmlspecialchars($originalBody, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

		return "<p>On {$date}, {$from} wrote:</p>\n<blockquote style=\"border-left: 2px solid #ccc; padding-left: 10px; margin-left: 0;\">\n{$escapedBody}\n</blockquote>";
	}

	/**
	 * @param array{from: string, to: string, cc: string, date: string, subject: string} $headers
	 */
	private function buildForwardHeader(array $headers): string
	{
		return "---------- Forwarded message ----------\n"
			. "From: {$headers['from']}\n"
			. "Date: {$headers['date']}\n"
			. "Subject: {$headers['subject']}\n"
			. "To: {$headers['to']}\n\n";
	}

	private function buildReferences(string $inReplyTo, string $messageId): string
	{
		$refs = [];

		if ($inReplyTo !== '') {
			$refs[] = $inReplyTo;
		}

		$refs[] = $messageId;

		return implode(' ', $refs);
	}

	/**
	 * @param array{from: string, to: string, cc: string} $originalHeaders
	 */
	private function addReplyAllRecipients(Email $email, array $originalHeaders, string $resolvedFrom): void
	{
		$ownAddresses = $this->config->allowedFrom;
		$isOwn = static fn(string $addr): bool => \in_array(mb_strtolower($addr), $ownAddresses, true);

		$originalToList = $this->parseAddressList($originalHeaders['to']);
		$originalCcList = $this->parseAddressList($originalHeaders['cc']);

		foreach ($originalToList as $addr) {
			if (!$isOwn($addr) && mb_strtolower($addr) !== mb_strtolower($resolvedFrom)) {
				$email->addTo($addr);
			}
		}

		foreach ($originalCcList as $addr) {
			if (!$isOwn($addr) && mb_strtolower($addr) !== mb_strtolower($resolvedFrom)) {
				$email->addCc($addr);
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private function parseAddressList(string $raw): array
	{
		if ($raw === '') {
			return [];
		}

		$addresses = [];

		foreach (explode(',', $raw) as $part) {
			$part = trim($part);

			if (preg_match('/<([^>]+)>/', $part, $matches) === 1) {
				$addresses[] = $matches[1];
			} elseif (filter_var($part, \FILTER_VALIDATE_EMAIL) !== false) {
				$addresses[] = $part;
			}
		}

		return $addresses;
	}

	/**
	 * @param list<string>|null $attachments
	 */
	private function addAttachments(Email $email, array|null $attachments): void
	{
		if ($attachments === null) {
			return;
		}

		foreach ($attachments as $path) {
			if (!is_file($path)) {
				throw new \InvalidArgumentException("Attachment file not found: '{$path}'");
			}

			$email->attachFromPath($path);
		}
	}
}
