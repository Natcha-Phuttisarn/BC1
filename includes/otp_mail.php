<?php

require_once __DIR__ . '/password_otp.php';
require_once __DIR__ . '/mail.php';

final class PhpMailerOtpMailTransport implements PasswordOtpMailTransport
{
    /** @var callable */
    private $mailerFactory;

    public function __construct(?callable $mailerFactory = null)
    {
        $this->mailerFactory = $mailerFactory ?? static function () {
            return createBizcompMailer();
        };
    }

    public function send(string $email, string $name, string $code): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        try {
            $mail = ($this->mailerFactory)();
            if (!($mail instanceof \PHPMailer\PHPMailer\PHPMailer)) {
                throw new RuntimeException('Invalid OTP mailer factory.');
            }

            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'รหัส OTP สำหรับตั้งรหัสผ่านใหม่ | Bizcomp Club';

            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $mail->Body = '<div style="max-width:600px;margin:30px auto;font-family:Arial,sans-serif;color:#334155;">'
                . '<div style="padding:22px;background:#173f73;color:#fff;text-align:center;border-radius:10px 10px 0 0;">'
                . '<h2 style="margin:0;">Bizcomp Club</h2></div>'
                . '<div style="padding:28px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 10px 10px;">'
                . '<h2 style="color:#173f73;">รหัส OTP สำหรับตั้งรหัสผ่านใหม่</h2>'
                . '<p>สวัสดี ' . $safeName . '</p>'
                . '<p>ใช้รหัสต่อไปนี้เพื่อยืนยันคำขอของคุณ:</p>'
                . '<p style="font-size:30px;font-weight:700;letter-spacing:8px;text-align:center;color:#173f73;">' . $safeCode . '</p>'
                . '<p>รหัสนี้หมดอายุภายใน 5 นาทีและใช้ได้เพียงครั้งเดียว</p>'
                . '<p style="color:#64748b;font-size:13px;">หากคุณไม่ได้ขอเปลี่ยนรหัสผ่าน กรุณาเพิกเฉยต่ออีเมลนี้</p>'
                . '</div></div>';
            $mail->AltBody = "สวัสดี {$name}\n\nรหัส OTP สำหรับตั้งรหัสผ่านใหม่: {$code}\n"
                . "รหัสนี้หมดอายุภายใน 5 นาทีและใช้ได้เพียงครั้งเดียว";

            return (bool)$mail->send();
        } catch (Throwable $e) {
            error_log('OTP MAIL ERROR: ' . $e->getMessage());
            return false;
        }
    }
}
