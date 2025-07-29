<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $balance = floatval($_POST['balance']);

    if (!empty($name)) {
        // 1. Tạo tài khoản mới
        $stmt = mysqli_prepare($conn, "INSERT INTO accounts (user_id, name, balance) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isd", $user_id, $name, $balance);
        mysqli_stmt_execute($stmt);

        // 2. Lấy account_id vừa tạo
        $account_id = mysqli_insert_id($conn);

        // 3. Ghi lịch sử vào bảng transactions
        $description = "Tạo tài khoản mới: " . $name;
        $now = date('Y-m-d H:i:s');
        $type = 2; // Ghi chú hệ thống

        $stmt2 = mysqli_prepare($conn, "INSERT INTO transactions (user_id, account_id, type, amount, description, date) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt2, "iiidss", $user_id, $account_id, $type, $balance, $description, $now);
        mysqli_stmt_execute($stmt2);

        // 4. Chuyển hướng về dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Vui lòng nhập tên tài khoản.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>➕ Tạo tài khoản mới</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 480px;
            margin: 60px auto;
            background-color: #fff;
            border-radius: 14px;
            padding: 30px 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        h2::before {
            content: "➕ ";
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-add {
            background-color: #007bff;
            color: white;
        }

        .btn-add:hover {
            background-color: #0056b3;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #007bff;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .error {
            color: red;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Tạo tài khoản mới</h2>

        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="name">Tên khoản tiền:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="balance">Số dư ban đầu:</label>
                <input type="number" id="balance" name="balance" step="0.01" value="0" required>
            </div>

            <button type="submit" class="btn-add">💾 Tạo tài khoản</button>
        </form>


        <a class="back-link" href="dashboard.php">← Quay lại Dashboard</a>
    </div>
</body>
</html>
