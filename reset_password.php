<?php
session_start();
include "db.php";

$error = $success = "";

// Kiểm tra đã xác minh OTP chưa
if (!isset($_SESSION["reset_email"]) || !isset($_SESSION["otp_verified"])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password     = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    // Regex: 6+ ký tự, 1 chữ hoa, 1 số, 1 đặc biệt
    $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*\W).{6,}$/';

    if (!preg_match($pattern, $new_password)) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự, bao gồm 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt.";
    }
    elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp.";
    }
    else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $update = pg_query_params($conn,
            "UPDATE users
             SET password = $1, reset_token = NULL, reset_token_expiry = NULL
             WHERE email = $2",
            [$hashed_password, $email]
        );

        if ($update) {
            unset($_SESSION["reset_email"], $_SESSION["otp_verified"]);
            $success = "Đặt lại mật khẩu thành công. <a href='login.php'>Đăng nhập</a>";
        } else {
            $error = "Có lỗi xảy ra khi cập nhật mật khẩu.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f1f1;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .reset-container {
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            margin-bottom: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message {
            margin-bottom: 15px;
        }
        .message.error {
            color: red;
        }
        .message.success {
            color: green;
        }
        a {
            color: #007bff;
        }
    </style>
</head>
<body>
<div class="reset-container">
    <h2>Đặt lại mật khẩu</h2>
    <?php if ($error): ?>
        <p class="message error"><?= $error ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="message success"><?= $success ?></p>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <label for="new_password">Mật khẩu mới:</label>
        <input type="password" name="new_password" id="new_password" required>

        <label for="confirm_password">Xác nhận mật khẩu:</label>
        <input type="password" name="confirm_password" id="confirm_password" required>

        <button type="submit">Đổi mật khẩu</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
