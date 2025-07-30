<?php
include "db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username  = trim($_POST["username"]);
    $password  = $_POST["password"];
    $confirm   = $_POST["confirm"];
    $fullname  = trim($_POST["fullname"]);
    $birthyear = intval($_POST["birthyear"]);
    $email     = trim($_POST["email"]);

    if ($password !== $confirm) {
        $error = "❌ Mật khẩu xác nhận không khớp!";
    } elseif (strlen($password) < 6) {
        $error = "❌ Mật khẩu cần tối thiểu 6 ký tự!";
    } else {
        // Kiểm tra trùng username
        $result = pg_query_params($conn,
            "SELECT id FROM users WHERE username = $1", [$username]
        );

        if (pg_num_rows($result) > 0) {
            $error = "❌ Tên đăng nhập đã tồn tại!";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Thêm người dùng mới (không truyền id)
            $insert = pg_query_params($conn,
                "INSERT INTO users (username, password, fullname, birthyear, email)
                 VALUES ($1, $2, $3, $4, $5)",
                [$username, $hash, $fullname, $birthyear, $email]
            );

            if ($insert) {
                $success = "✅ Tạo tài khoản thành công! <br>Bạn có thể <a href='login.php'>đăng nhập</a>.";
            } else {
                $error = "❌ Lỗi khi tạo tài khoản. Vui lòng thử lại.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản</title>
    <style>
    * {
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        background-color: #f1f1f1;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }

    .container {
        background-color: white;
        padding: 30px 35px;
        border-radius: 12px;
        box-shadow: 0 0 12px rgba(0, 0, 0, 0.08);
        width: 100%;
        max-width: 400px;
    }

    h2 {
        text-align: center;
        margin-bottom: 25px;
        font-size: 22px;
    }

    input[type="text"],
    input[type="password"],
    input[type="number"],
    input[type="email"] {
        width: 100%;
        padding: 12px 15px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
    }

    button {
        width: 100%;
        padding: 12px;
        background-color: #28a745;
        color: white;
        font-size: 16px;
        font-weight: bold;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
    }

    button:hover {
        background-color: #218838;
    }

    .error {
        color: red;
        margin-bottom: 10px;
        text-align: center;
    }

    .success {
        color: green;
        text-align: center;
        margin-bottom: 10px;
    }

    .link {
        text-align: center;
        margin-top: 15px;
        font-size: 14px;
    }

    .link a {
        color: #007bff;
        text-decoration: none;
    }

    .link a:hover {
        text-decoration: underline;
    }
    .back-button {
        display: block;
        text-align: center;
        margin-top: 15px;
        text-decoration: none;
        font-size: 14px;
        color: #007bff;
        transition: color 0.2s ease;
    }

    .back-button:hover {
        text-decoration: underline;
        color: #0056b3;
    }
    </style>

    <script>
        document.querySelector("form").addEventListener("submit", function(event) {
            const confirmed = confirm("Bạn có chắc chắn muốn tạo tài khoản không?");
            if (!confirmed) {
                event.preventDefault(); // Ngăn gửi form nếu người dùng chọn Cancel
            }
        });
    </script>
</head>
    
<body>
    <div class="container">
        <h2>📝 Đăng ký tài khoản</h2>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="username" placeholder="Tên đăng nhập" required>
            <input type="password" name="password" placeholder="Mật khẩu (Tối thiểu 6 kí tự)" required>
            <input type="password" name="confirm" placeholder="Xác nhận mật khẩu" required>
            <input type="text" name="fullname" placeholder="Họ và tên" required>
            <input type="number" name="birthyear" placeholder="Năm sinh (VD: 2000)" min="1900" max="2100" required>
            <input type="email" name="email" placeholder="Email" required>
            <button type="submit">Đăng ký</button>
        </form>

        <a href="login.php" class="back-button">← Quay lại đăng nhập</a>

        <div class="link">
            Đã có tài khoản? <a href="login.php">Đăng nhập</a>
        </div>
    </div>
</body>
</html>
