<?php
// mail.php
// ไฟล์นี้ใช้สำหรับตั้งค่าและส่งอีเมลของระบบ Bizcomp Club ผ่าน Gmail SMTP

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';


// =========================================
// ตั้งค่า Email ผู้ส่ง
// =========================================

function bizcompMailConfig(string $key, string $default = ''): string
{
    $value = bcConfig('BC_MAIL_' . $key);
    return trim($value) === '' ? $default : $value;
}

function isBizcompMailConfigured(): bool
{
    return bizcompMailConfig('USERNAME') !== '' && bizcompMailConfig('PASSWORD') !== '';
}


// =========================================
// สร้าง Mail
// =========================================

function createBizcompMailer(): PHPMailer
{
    if (!isBizcompMailConfigured()) {
        throw new RuntimeException('ยังไม่ได้ตั้งค่า BC_MAIL_USERNAME และ BC_MAIL_PASSWORD');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();

    $mail->Host = bizcompMailConfig('HOST', 'smtp.gmail.com');
    $mail->SMTPAuth = true;

    $mail->Username = bizcompMailConfig('USERNAME');
    $mail->Password = bizcompMailConfig('PASSWORD');

    $encryption = bizcompMailConfig('ENCRYPTION', 'tls');
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        throw new RuntimeException('BC_MAIL_ENCRYPTION must be tls or ssl.');
    }
    $mail->SMTPSecure = $encryption;
    $mail->Timeout = 15;

    $mail->Port = (int)bizcompMailConfig('PORT', '587');

    $mail->CharSet = 'UTF-8';

    $mail->setFrom(
        bizcompMailConfig('FROM', $mail->Username),
        bizcompMailConfig('FROM_NAME', 'Bizcomp Club')
    );

    return $mail;
}


// =========================================
// ส่ง Email Reset Password
// =========================================

function sendResetPasswordEmail(
    string $toEmail,
    string $toName,
    string $resetUrl
): bool {

    try {

        $mail = createBizcompMailer();

        $mail->addAddress(
            $toEmail,
            $toName
        );

        $mail->isHTML(true);

        $mail->Subject =
            'ตั้งรหัสผ่านใหม่ | Bizcomp Club';


        $safeName =
            htmlspecialchars(
                $toName,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeUrl =
            htmlspecialchars(
                $resetUrl,
                ENT_QUOTES,
                'UTF-8'
            );


        $mail->Body = '

        <div style="
            max-width:600px;
            margin:30px auto;
            font-family:Arial,sans-serif;
            color:#333;
        ">

            <div style="
                padding:24px;
                background:#173f73;
                color:#ffffff;
                text-align:center;
                border-radius:10px 10px 0 0;
            ">

                <h2 style="margin:0;">
                    Bizcomp Club
                </h2>

                <p style="
                    margin:5px 0 0;
                    font-size:13px;
                ">
                    Business Computer Club
                </p>

            </div>


            <div style="
                padding:30px;
                border:1px solid #e2e8f0;
                border-top:0;
                border-radius:0 0 10px 10px;
            ">

                <h2>
                    ตั้งรหัสผ่านใหม่
                </h2>

                <p>
                    สวัสดี ' . $safeName . '
                </p>

                <p>
                    ระบบได้รับคำขอสำหรับตั้งรหัสผ่านใหม่
                    ของบัญชี Bizcomp Club ของคุณ
                </p>

                <p>
                    กดปุ่มด้านล่างเพื่อกำหนดรหัสผ่านใหม่
                </p>


                <div style="
                    margin:30px 0;
                    text-align:center;
                ">

                    <a
                        href="' . $safeUrl . '"
                        style="
                            display:inline-block;
                            padding:13px 25px;
                            background:#1768cf;
                            color:#ffffff;
                            text-decoration:none;
                            border-radius:7px;
                            font-weight:bold;
                        "
                    >

                        ตั้งรหัสผ่านใหม่

                    </a>

                </div>


                <p style="
                    color:#777;
                    font-size:13px;
                    line-height:1.7;
                ">

                    ลิงก์นี้มีอายุ 30 นาที
                    และสามารถใช้ได้เพียงครั้งเดียว

                </p>


                <p style="
                    color:#777;
                    font-size:13px;
                    line-height:1.7;
                ">

                    หากคุณไม่ได้เป็นผู้ขอตั้งรหัสผ่านใหม่
                    สามารถเพิกเฉยต่ออีเมลฉบับนี้ได้

                </p>

            </div>

        </div>
        ';


        $mail->AltBody =
            "สวัสดี {$toName}\n\n" .
            "ระบบได้รับคำขอตั้งรหัสผ่านใหม่สำหรับบัญชี Bizcomp Club\n\n" .
            "เปิดลิงก์นี้เพื่อตั้งรหัสผ่านใหม่:\n" .
            $resetUrl .
            "\n\nลิงก์นี้หมดอายุภายใน 30 นาที";


        $mail->send();

        return true;

    } catch (Throwable $e) {

        error_log(
            'MAIL ERROR: ' .
            $e->getMessage()
        );

        return false;
    }
}

function sendBizcompNotificationEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $heading,
    array $messageLines
): bool {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL) || !isBizcompMailConfigured()) {
        return false;
    }

    try {
        $mail = createBizcompMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject . ' | Bizcomp Club';

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        $safeLines = array_map(
            static fn($line) => htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8'),
            $messageLines
        );
        $paragraphs = implode('', array_map(
            static fn($line) => '<p style="margin:8px 0;line-height:1.7;">' . $line . '</p>',
            $safeLines
        ));

        $mail->Body = '<div style="max-width:600px;margin:30px auto;font-family:Arial,sans-serif;color:#334155;">'
            . '<div style="padding:22px;background:#173f73;color:#fff;text-align:center;border-radius:10px 10px 0 0;">'
            . '<h2 style="margin:0;">Bizcomp Club</h2></div>'
            . '<div style="padding:28px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 10px 10px;">'
            . '<h2 style="color:#173f73;">' . $safeHeading . '</h2>'
            . '<p>สวัสดี ' . $safeName . '</p>' . $paragraphs
            . '<p style="margin-top:22px;color:#64748b;font-size:13px;">อีเมลนี้ส่งอัตโนมัติจากระบบ Bizcomp Club</p>'
            . '</div></div>';
        $mail->AltBody = "สวัสดี {$toName}\n\n{$heading}\n" . implode("\n", $messageLines);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('MAIL ERROR: ' . $e->getMessage());
        return false;
    }
}
