<?php

namespace Hcode;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
	private $mail;

	// ===== CONFIG SMTP =====
	private const SMTP_HOST = 'mail.ms-tecnologia.app.br';
	private const SMTP_USER = 'prato@ms-tecnologia.app.br';
	private const SMTP_PASS = ']&&i]ku%?c=('; // ⚠️ troque
	private const SMTP_PORT = 465;
	private const SMTP_SECURE = PHPMailer::ENCRYPTION_SMTPS;

	private const FROM_NAME = 'Prato Cheio';
	private const FROM_EMAIL = 'prato@ms-tecnologia.app.br';

	public function __construct()
	{
		$this->mail = new PHPMailer(true);

		try {

			$this->mail->isSMTP();

			// FORÇA IPv4
			$this->mail->Host = gethostbyname(self::SMTP_HOST);

			$this->mail->SMTPAuth   = true;
			$this->mail->Username   = self::SMTP_USER;
			$this->mail->Password   = self::SMTP_PASS;
			$this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
			$this->mail->Port       = 465;
			$this->mail->CharSet    = 'UTF-8';
			$this->mail->Timeout    = 30;

			// DEBUG (deixe ligado agora)
			$this->mail->SMTPDebug  = 2;

			// evita erro SSL local
			$this->mail->SMTPOptions = [
				'ssl' => [
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				]
			];

			// ===== REMETENTE =====
			$this->mail->setFrom(self::FROM_EMAIL, self::FROM_NAME);
		} catch (Exception $e) {
			throw new \Exception("Erro ao configurar e-mail: " . $e->getMessage());
		}
	}

	public function send(
		string $toAddress,
		string $toName,
		string $subject,
		string $htmlBody,
		string $altBody = ''
	): bool {

		try {

			$this->mail->clearAddresses();
			$this->mail->clearAttachments();

			$this->mail->addAddress($toAddress, $toName);

			$this->mail->isHTML(true);
			$this->mail->Subject = $subject;
			$this->mail->Body    = $htmlBody;
			$this->mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

			if (!$this->mail->send()) {
				throw new \Exception($this->mail->ErrorInfo);
			}

			return true;
		} catch (Exception $e) {
			throw new \Exception("Erro ao enviar e-mail: " . $e->getMessage());
		}
	}

	public static function quickSend(
		string $toAddress,
		string $toName,
		string $subject,
		string $htmlBody,
		string $altBody = ''
	): bool {

		$mailer = new self();

		return $mailer->send(
			$toAddress,
			$toName,
			$subject,
			$htmlBody,
			$altBody
		);
	}
}
