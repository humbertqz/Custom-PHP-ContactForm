<?php

declare(strict_types=1);

namespace ContactModule\Services;

use Closure;
use ContactModule\Config\Config;
use ContactModule\Contracts\MailerInterface;
use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService implements MailerInterface
{
    /** @param (Closure(): PHPMailer)|null $factory */
    public function __construct(private Config $config, private ?Closure $factory = null)
    {
    }

    public function client(): PHPMailer
    {
        $mail = $this->factory === null ? new PHPMailer(true) : ($this->factory)();
        $mail->isSMTP();
        $mail->Host = $this->config->text('MAIL_HOST');
        $mail->Port = $this->config->integer('MAIL_PORT');
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->text('MAIL_USERNAME');
        $mail->Password = $this->config->text('MAIL_PASSWORD');
        $mail->SMTPSecure = $this->config->text('MAIL_ENCRYPTION') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = $this->config->integer('MAIL_TIMEOUT');
        $smtp = new TrackingSmtp();
        $smtp->Timelimit = $mail->Timeout;
        $mail->setSMTPInstance($smtp);
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
        ]];
        return $mail;
    }

    public function send(Submission $submission, array $form, bool $confirmation): DeliveryResult
    {
        $mail = $this->client();
        $smtp = $mail->getSMTPInstance();
        assert($smtp instanceof TrackingSmtp);
        $email = $submission->field('email');
        $reply = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $settings = $form['mail'] ?? [];
        try {
            $mail->setFrom($this->config->text('MAIL_FROM_ADDRESS'), $this->config->text('MAIL_FROM_NAME'));
            if ($confirmation) {
                if (!$reply) {
                    return new DeliveryResult(DeliveryStatus::Failed, 'SMTP_RECIPIENT');
                }
                $mail->addAddress($email);
            } else {
                $this->recipients($mail, $settings);
                if ($reply) {
                    $mail->addReplyTo($email);
                }
            }
            $kind = $confirmation ? 'confirmation' : 'notification';
            $mail->Subject = $settings[$confirmation ? 'confirmation_subject' : 'subject']
                ?? ($confirmation ? 'Recibimos tu mensaje' : 'Nuevo mensaje de contacto');
            $template = $settings[$confirmation ? 'confirmation_template' : 'template'] ?? $kind;
            $mail->isHTML();
            $templateData = $confirmation
                ? new Submission($submission->id, $submission->formId, $submission->createdAt, [])
                : $submission;
            $mail->Body = $this->render($template, $templateData, $form);
            $mail->AltBody = $this->render($template . '-text', $templateData, $form);
            $domain = substr(strrchr($this->config->text('MAIL_FROM_ADDRESS'), '@') ?: '@localhost', 1);
            $mail->MessageID = '<' . $submission->id . '.' . $kind . '@' . $domain . '>';
        } catch (\Throwable) {
            return new DeliveryResult(DeliveryStatus::Failed, 'SMTP_CONFIGURATION');
        }
        try {
            if ($mail->send()) {
                return DeliveryResult::completed();
            }
        } catch (\Throwable) {
            // SMTP transport details and recipient addresses remain private.
        } finally {
            $smtp->close();
        }
        $code = $smtp->failureCode;
        $status = match (true) {
            $smtp->dataAccepted, $smtp->dataStarted && ($code < 400 || $code >= 600) => DeliveryStatus::Ambiguous,
            $code >= 500 && $code < 600 => DeliveryStatus::Failed,
            default => DeliveryStatus::Temporary,
        };
        return new DeliveryResult($status, 'SMTP_SEND');
    }

    /** @param array<string,mixed> $settings */
    private function recipients(PHPMailer $mail, array $settings): void
    {
        foreach ($settings['to'] ?? $this->config->list('MAIL_TO_ADDRESS') as $address) {
            $mail->addAddress($address);
        }
        foreach ($settings['cc'] ?? $this->config->list('MAIL_CC') as $address) {
            $mail->addCC($address);
        }
        foreach ($settings['bcc'] ?? $this->config->list('MAIL_BCC') as $address) {
            $mail->addBCC($address);
        }
    }

    /** @param array<string,mixed> $form */
    private function render(string $template, Submission $submission, array $form): string
    {
        if (!preg_match('/\A[a-z][a-z0-9_-]{0,68}\z/D', $template)) {
            throw new \RuntimeException('Invalid template.');
        }
        $file = $this->config->root . '/templates/emails/' . $template . '.php';
        if (!is_file($file)) {
            $file = $this->config->package . '/templates/emails/' . $template . '.php';
        }
        if (!is_file($file)) {
            throw new \RuntimeException('Template unavailable.');
        }
        return (static function (string $file, Submission $submission, array $form): string {
            ob_start();
            try {
                require $file;
                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        })($file, $submission, $form);
    }
}
