<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_GET['id'] ?? null;

if (!$transaction_id) {
    echo "Không tìm thấy giao dịch.";
    exit();
}

// Nếu người dùng xác nhận xoá
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm']) && $_POST['confirm'] === "yes") {
        // ❌ XÓA MỀM (đã bỏ)
        // ✅ XÓA THẬT giao dịch
        $stmt = mysqli_prepare($conn, "DELETE FROM transactions WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $transaction_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác nhận xóa giao dịch</title>
    <style>
        body {
            font-family: Arial;
            background: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        button, a {
            margin: 10px;
            padding: 10px 20px;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-weight: bold;
        }

        button {
            background-color: #dc3545;
            color: white;
            cursor: pointer;
        }

        a {
            background-color: #6c757d;
            color: white;
        }

        a:hover, button:hover {
            opacity: 0.85;
        }
    </style>
</head>
<body>
    <form method="post">
        <p>Bạn có chắc chắn muốn xóa giao dịch này?</p>
        <input type="hidden" name="confirm" value="yes">
        <button type="submit">✅ Đồng ý</button>
        <a href="dashboard.php">❌ Hủy</a>
    </form>
</body>
</html>
