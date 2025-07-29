<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendOTP($toEmail, $otp) {
    $mail = new PHPMailer(true);

    try {
        // Cấu hình SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'phanthang08bn@gmail.com'; // Thay bằng email của bạn
        $mail->Password = 'mxihfanfjmjlttlj'; // Thay bằng mật khẩu ứng dụng
        $mail->setFrom('phanthang08bn@gmail.com', 'Quản lý thu chi');
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Thiết lập người gửi & người nhận
        $mail->setFrom('your_email@gmail.com', 'Email khôi phục tài khoản.');
        $mail->addAddress($toEmail);

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = 'Mã OTP đặt lại mật khẩu';
        $mail->Body    = "Mã OTP của bạn là: <b>$otp</b>. Mã có hiệu lực trong 10 phút.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "Lỗi gửi email: " . $mail->ErrorInfo; // In ra lỗi cụ thể
        return false;
    }
}
