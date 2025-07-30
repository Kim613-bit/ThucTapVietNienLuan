<?php
session_start();
include "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Truy vấn người dùng theo username
    $result = pg_query_params($conn,
        "SELECT id, password FROM users WHERE username = $1",
        [$username]
    );

    if ($result && pg_num_rows($result) === 1) {
        $user = pg_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Sai tên đăng nhập hoặc mật khẩu!";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Sai tên đăng nhập hoặc mật khẩu!";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <h2>Đăng nhập hệ thống</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <p style="color:red;"><?= $_SESSION['error'] ?></p>
            <?php unset($_SESSION['error']); // Xóa sau khi hiển thị ?>
        <?php endif; ?>


        <form method="post">
            <input type="text" name="username" placeholder="Tên đăng nhập" required><br>
            <input type="password" name="password" placeholder="Mật khẩu" required><br>
            <button type="submit">Đăng nhập</button>
            <p style="margin-top: 10px;">
                Chưa có tài khoản? <a href="register.php">Đăng ký</a>
            </p>
            <p style="margin-top: 10px;">
                <a href="forgot_password.php">Quên mật khẩu?</a>
            </p>
        </form>
    </div>
</body>
</html>
