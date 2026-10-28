<?php

declare(strict_types=1);

namespace Shared;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Service gửi email qua SMTP (Gmail) sử dụng PHPMailer.
 */
class Mailer
{
    /**
     * Khởi tạo và cấu hình đối tượng PHPMailer từ file config/Mail.php
     *
     * @return PHPMailer
     * @throws \Exception
     */
    public static function createMailer(): PHPMailer
    {
        $config = require __DIR__ . '/../config/Mail.php';

        if (empty($config['username']) || empty($config['password'])) {
            throw new \Exception("Hệ thống chưa được cấu hình tài khoản Gmail SMTP (MAIL_USERNAME/MAIL_PASSWORD).");
        }

        $mail = new PHPMailer(true);

        // Cấu hình Server
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->CharSet    = 'UTF-8';

        // Mã hóa TLS hoặc SSL
        if (strtolower($config['encryption']) === 'ssl' || $config['port'] === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = $config['port'];

        // Người gửi
        $fromAddress = !empty($config['from_address']) ? $config['from_address'] : $config['username'];
        $fromName    = !empty($config['from_name']) ? $config['from_name'] : 'Royal Lotus Hotel';
        $mail->setFrom($fromAddress, $fromName);

        // Xử lý chứng chỉ SSL môi trường local XAMPP Windows
        if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        return $mail;
    }

    /**
     * Gửi email OTP xác nhận quên mật khẩu đến khách hàng
     *
     * @param string $toEmail
     * @param string $otp
     * @param int $expiresInMinutes
     * @param string $customerName
     * @return bool
     * @throws \Exception
     */
    public static function sendOTP(
        string $toEmail,
        string $otp,
        int $expiresInMinutes = 5,
        string $customerName = ''
    ): bool {
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $customerName);

            $mail->isHTML(true);
            $mail->Subject = 'Mã xác nhận đặt lại mật khẩu - Royal Lotus Hotel';

            $displayName = $customerName !== '' ? htmlspecialchars($customerName) : 'Quý khách';

            // HTML Body sang trọng với phong cách Royal Lotus
            $mail->Body = '
            <!DOCTYPE html>
            <html lang="vi">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #2d3748; }
                    .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #e2e8f0; }
                    .header { background: linear-gradient(135deg, #0b1329 0%, #1a2a4e 100%); padding: 30px 24px; text-align: center; }
                    .header h1 { color: #f1c40f; margin: 0; font-size: 24px; letter-spacing: 1px; font-weight: 700; }
                    .header p { color: #cbd5e0; margin: 6px 0 0; font-size: 13px; }
                    .body { padding: 30px 24px; }
                    .otp-box { text-align: center; margin: 24px 0; padding: 18px; background: #f7fafc; border-radius: 8px; border: 2px dashed #cbd5e0; }
                    .otp-code { font-size: 34px; font-weight: 800; letter-spacing: 10px; color: #1a365d; font-family: "Courier New", Courier, monospace; display: inline-block; margin-left: 10px; }
                    .notice { background-color: #fffaf0; border-left: 4px solid #dd6b20; padding: 12px; font-size: 13px; color: #7b341e; border-radius: 4px; margin-top: 20px; }
                    .footer { text-align: center; font-size: 12px; color: #a0aec0; padding: 20px; background-color: #f8fafc; border-top: 1px solid #edf2f7; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="header">
                        <h1>ROYAL LOTUS HOTEL</h1>
                        <p>Hệ thống Khách sạn & Nghỉ dưỡng Cao cấp</p>
                    </div>
                    <div class="body">
                        <p>Kính gửi <strong>' . $displayName . '</strong>,</p>
                        <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản liên kết với địa chỉ email này tại <strong>Royal Lotus Hotel</strong>.</p>
                        <p>Mã xác minh (OTP) dùng một lần của Quý khách là:</p>
                        
                        <div class="otp-box">
                            <span class="otp-code">' . htmlspecialchars($otp) . '</span>
                        </div>

                        <p style="text-align: center; font-size: 14px; color: #4a5568;">
                            Mã này có hiệu lực trong vòng <strong>' . $expiresInMinutes . ' phút</strong>.
                        </p>

                        <div class="notice">
                            <strong>Lưu ý bảo mật quan trọng:</strong> Tuyệt đối không chia sẻ mã này cho bất kỳ ai, kể cả nhân viên hỗ trợ của khách sạn. Nếu Quý khách không gửi yêu cầu này, vui lòng bỏ qua email hoặc liên hệ ngay với chúng tôi.
                        </div>
                    </div>
                    <div class="footer">
                        Email này được gửi tự động từ hệ thống Royal Lotus Hotel.<br>
                        Vui lòng không trả lời trực tiếp email này.
                    </div>
                </div>
            </body>
            </html>
            ';

            $mail->AltBody = "Kính gửi " . $displayName . ",\n\n"
                . "Mã xác nhận (OTP) đặt lại mật khẩu của bạn là: " . $otp . "\n"
                . "Mã này có hiệu lực trong vòng " . $expiresInMinutes . " phút.\n"
                . "Tuyệt đối không chia sẻ mã này cho bất kỳ ai.\n\n"
                . "Trân trọng,\nRoyal Lotus Hotel";

            return $mail->send();
        } catch (Exception $e) {
            // Không lộ mật khẩu hoặc trace SMTP ra ngoài, ghi log an toàn nếu cần
            error_log("Lỗi gửi mail PHPMailer: " . $e->getMessage());
            throw new \Exception("Không thể gửi email OTP lúc này. Vui lòng kiểm tra cấu hình SMTP hoặc thử lại sau.");
        }
    }
}
