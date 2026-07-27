<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/PublicMailer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\CsvLogWriter;

	/** Sends public and administrative mail through the configured site transport. */
	class PublicMailer
	{
		public static function send($to = '', $body = '', $subject = '', $fromEmail = '', $fromName = '', $type = 'text', array $attachments = array(), $saveAttachments = true, $signature = true)
		{
			require_once BASEPATH . '/system/vendor/SwiftMailer/swift_required.php';

			$to = preg_replace('/\s+/u', '', (string) $to);
			$fromEmail = preg_replace('/\s+/u', '', (string) $fromEmail);
			$contentType = in_array(strtolower((string) $type), array('html', 'text/html'), true)
				? 'text/html'
				: 'text/plain';

			$signatureText = '';
			if ($signature) {
				$configured = (string) PublicSettings::get('mail_signature');
				$signatureText = $contentType === 'text/html'
					? '<br><br>' . nl2br($configured)
					: "\r\n\r\n" . $configured;
			}

			$body = stripslashes((string) $body) . $signatureText;
			if ($contentType === 'text/html') {
				$body = str_replace(array("\t", "\r", "\n"), '', $body);
				$body = str_replace(array('  ', '> <'), array(' ', '><'), $body);
			}

			$message = \Swift_Message::newInstance((string) $subject)
				->setFrom(array($fromEmail => (string) $fromName))
				->setTo($to)
				->setContentType($contentType)
				->setBody($body)
				->setMaxLineLength((int) PublicSettings::get('mail_word_wrap'));

			foreach ($attachments as $attachment) {
				$message->attach(\Swift_Attachment::fromPath(trim((string) $attachment)));
			}

			$transport = self::transport();
			if ($attachments && $saveAttachments) {
				self::archiveAttachments($attachments);
			}

			$failures = array();
			$mailer = \Swift_Mailer::newInstance($transport);
			if (!@$mailer->send($message, $failures)) {
				CsvLogWriter::event('Не удалось отправить письма следующим адресатам: ' . implode(',', $failures));
				return $failures;
			}

			return true;
		}

		protected static function transport()
		{
			switch ((string) PublicSettings::get('mail_type')) {
				case 'smtp':
					$transport = \Swift_SmtpTransport::newInstance(
						stripslashes((string) PublicSettings::get('mail_host')),
						(int) PublicSettings::get('mail_port')
					);
					$encryption = (string) PublicSettings::get('mail_smtp_encrypt');
					if ($encryption !== '') {
						$transport->setEncryption(strtolower(stripslashes($encryption)));
					}

					$user = (string) PublicSettings::get('mail_smtp_login');
					if ($user !== '') {
						$transport
							->setUsername(stripslashes($user))
							->setPassword(stripslashes((string) PublicSettings::get('mail_smtp_pass')));
					}

					return $transport;

				case 'sendmail':
					return \Swift_SendmailTransport::newInstance((string) PublicSettings::get('mail_sendmail_path'));

				case 'mail':
				default:
					return \Swift_MailTransport::newInstance();
			}
		}

		protected static function archiveAttachments(array $attachments)
		{
			$directory = BASEPATH . '/tmp/' . ATTACH_DIR . '/';
			if (!is_dir($directory)) {
				@mkdir($directory, 0775, true);
			}

			foreach ($attachments as $path) {
				$path = (string) $path;
				if ($path === '' || !is_file($path)) {
					continue;
				}

				$name = str_replace(' ', '', mb_strtolower(trim(basename($path)), 'UTF-8'));
				if (is_file($directory . $name)) {
					$name = rand(1000, 9999) . '_' . $name;
				}

				$target = $directory . $name;
				if (!@move_uploaded_file($path, $target)) {
					@copy($path, $target);
				}
			}
		}
	}
