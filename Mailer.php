<?php
namespace GDO\Mailer;

use GDO\Mail\Mail;
use GDO\Mail\Module_Mail;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Own GDOv7 mailer using mail().
 * Symphony mailer shall be availble soon.
 *
 * @version 7.0.1
 * @since 7.0.1
 * @author gizmore
 */
final class Mailer
{

	public const HEADER_NEWLINE = "\n";

    /**
     * @throws \phpmailerException
     */
    public static function send(Mail $mail)
	{
		$attachments = $mail->getAttachments();
		if (count($attachments) > 0)
		{
			return self::sendWithAttachments($mail);
		}

		$headers = '';
//		$to = $mail->getUTF8Receiver();
//		$from = $mail->getUTF8Sender();
		$html = $mail->isHTML();
		$subject = $mail->getUTF8Subject();
		$message = $html ? $mail->nestedHTMLBody() : $mail->nestedTextBody();
		$contentType = $html ? 'text/html' : 'text/plain';
		$headers .= "Content-Type: $contentType; charset=utf-8" . self::HEADER_NEWLINE . 'MIME-Version: 1.0' .
			self::HEADER_NEWLINE . 'Content-Transfer-Encoding: 8bit' . self::HEADER_NEWLINE . 'X-Mailer: PHP' .
//			self::HEADER_NEWLINE . 'From: ' . $from .
            self::HEADER_NEWLINE . 'Reply-To: ' . $mail->getUTF8Reply() .
            self::HEADER_NEWLINE . 'Message-ID: ' . $mail->getMessageId() .
			self::HEADER_NEWLINE . 'Return-Path: ' . $mail->getUTF8Return();
		$encrypted = self::encrypt($message);
        return self::sendPHPMailer($mail, $subject, $encrypted, $headers);
    }

    /**
     * @throws \phpmailerException
     */
    private static function sendWithAttachments(Mail $mail)
	{
// 		$cc = $mail->getCC();
// 		$bcc = $mail->getBCC();
//		$to = $mail->getUTF8Receiver();
//		$from = $mail->getUTF8Sender();
		$subject = $mail->getUTF8Subject();
		$random_hash = sha1(microtime(true));
		$bound_mix = "GDOv7-MIX-{$random_hash}";
		$bound_alt = "GDOv7-ALT-{$random_hash}";
		$headers = "Content-Type: multipart/mixed; boundary=\"{$bound_mix}\"" . self::HEADER_NEWLINE .
			'MIME-Version: 1.0' . self::HEADER_NEWLINE .
            'Content-Transfer-Encoding: 8bit' . self::HEADER_NEWLINE .
            'Message-ID: ' . $mail->getMessageId() . self::HEADER_NEWLINE .
			'X-Mailer: PHP' . self::HEADER_NEWLINE .
//            'From: ' . $from . self::HEADER_NEWLINE .
            'Reply-To: ' . $mail->getUTF8Reply() . self::HEADER_NEWLINE .
            'Return-Path: ' . $mail->getUTF8Return();

		$message = "--$bound_mix\n";
		$message .= "Content-Type: multipart/alternative; boundary=\"$bound_alt\"\n";
		$message .= "\n";

		$message .= "--$bound_alt\n";
		$message .= "Content-Type: text/plain; charset=utf-8\n";
		$message .= "Content-Transfer-Encoding: 8bit\n";
		$message .= "\n";

		$message .= self::encrypt($mail->nestedTextBody());
		$message .= "\n\n";

		$message .= "--$bound_alt\n";
		$message .= "Content-Type: text/html; charset=utf-8\n";
		$message .= "Content-Transfer-Encoding: 8bit\n";
		$message .= "\n";

		$message .= self::encrypt($mail->nestedHTMLBody());
		$message .= "\n\n";

		$message .= "--$bound_alt--\n";
		$message .= "\n";

		foreach ($mail->getAttachments() as $filename => $attachdata)
		{
			[$attach, $mime, $encrypted] = $attachdata;
			$filename = preg_replace('/[^a-z0-9_\-\.]/i', '', $filename);
			$message .= "--$bound_mix\n";
			$message .= "Content-Type: $mime; name=\"$filename\"\n";
			$message .= "Content-Transfer-Encoding: base64\nContent-Disposition: attachment\n\n";
			if ($encrypted)
			{
				$message .= self::encrypt(chunk_split(base64_encode($attach)));
			}
			else
			{
				$message .= chunk_split(base64_encode($attach));
			}
		}

		$message .= "--$bound_mix--\n\n";
        return self::sendPHPMailer($mail, $subject, $message, $headers);
//		return @mail($to, $subject, $message, $headers);
	}

	private static function encrypt($message)
	{
		return $message;
	}

    /**
     * @throws \phpmailerException
     * @throws Exception
     */
    private static function sendPHPMailer(Mail $mail, string $subject, string $message, string $headers): bool
    {
        self::init();

        $mailer = new PHPMailer();

        $mailer->isSMTP();
        $mailer->Host = GDO_SMTP_HOST;
        $mailer->Port = GDO_SMTP_PORT;
        $mailer->SMTPAuth = true;
        $mailer->AuthType = 'PLAIN';
        $mailer->Username = GDO_SMTP_USER;
        $mailer->Password = GDO_SMTP_PASS;
        $mailer->SMTPSecure = 'tls'; // Enable TLS encryption, 'ssl' also accepted
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
// Email content
        $mailer->setFrom($mail->getSender(), $mail->getSenderName()); // Sender's email and name
        $mailer->addAddress($mail->getReceiver(), $mail->getReceiverName()); // Recipient's email and name
        $mailer->Subject = $subject; // Email subject
        $mailer->Body = $message; // Email content
        foreach (explode(self::HEADER_NEWLINE, $headers) as $header)
        {
            list($key, $value) = explode(':', $header);
            $mailer->addCustomHeader($key, trim($value));
        }

        // Send email
        return $mailer->send();
    }

    private static function init(): void
    {
        static $inited;
        if (!isset($inited))
        {
            $inited = true;
            $path = Module_Mailer::instance()->filePath('vendor/autoload.php');
            require_once $path;
        }
    }

}
