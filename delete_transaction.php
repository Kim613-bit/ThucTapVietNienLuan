<?php
session_start();
include "db.php"; // file này phải tạo kết nối bằng pg_connect()
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm']) && $_POST['confirm'] === "yes") {
        // ✅ XÓA giao dịch
        $result = pg_query_params($conn,
            "DELETE FROM transactions WHERE id = $1 AND user_id = $2",
            array($transaction_id, $user_id)
        );
        // Bạn có thể kiểm tra kết quả: if ($result) { ... }
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
