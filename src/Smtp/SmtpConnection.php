<?php

declare(strict_types=1);

namespace App\Smtp;

use App\Exception\SmtpSendException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class SmtpConnection implements SmtpConnectionInterface
{
	public function __construct(
		private readonly TransportInterface $transport,
	) {}

	/** @throws SmtpSendException */
	public function send(Email $email): void
	{
		try {
			$this->transport->send($email);
		} catch (TransportExceptionInterface $e) {
			throw new SmtpSendException(
				'Failed to send email: ' . $e->getMessage(),
				previous: $e,
			);
		}
	}

	public function disconnect(): void
	{
		// Transport is stateless per-send; no persistent connection to close
	}
}
