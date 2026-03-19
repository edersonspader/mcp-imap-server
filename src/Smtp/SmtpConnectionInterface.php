<?php

declare(strict_types=1);

namespace App\Smtp;

use Symfony\Component\Mime\Email;

interface SmtpConnectionInterface
{
	/**
	 * @throws \App\Exception\SmtpSendException
	 */
	public function send(Email $email): void;

	public function disconnect(): void;
}
