<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// Xử lý form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $type        = $_POST['type'];
    $rawAmount   = $_POST['amount'] ?? '0';
    $description = trim($_POST['description']);
    $date        = $_POST['date'];

    // 👉 1. Lọc số tiền nhập
    $sanitized = preg_replace('/[^\d\.]/', '', $rawAmount);
    if ($sanitized === '' || !is_numeric($sanitized)) {
        $error = "Số tiền không hợp lệ. Vui lòng nhập số.";
    } else {
        $amount = floatval($sanitized);

        // 👉 2. Kiểm tra giới hạn
        if ($amount <= 0) {
            $error = "Số tiền phải lớn hơn 0.";
        } elseif ($amount > 1000000000000) {
            $error = "Số tiền vượt quá giới hạn (tối đa 1,000,000,000,000 VND).";
        } else {
            // 👉 3. Thêm giao dịch vào DB (dùng prepared statement)
            $stmt = mysqli_prepare($conn, "INSERT INTO transactions (user_id, type, amount, description, date) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isdss", $user_id, $type, $amount, $description, $date);
            mysqli_stmt_execute($stmt);

            header("Location: transactions.php");
            exit();
        }
    }

    // 👉 Nếu có lỗi thì hiển thị
    if ($error !== "") {
        echo "<p style='color:red;'>$error</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Thêm giao dịch</title>
</head>
<body>
    <h2>Thêm khoản thu/chi</h2>
    <form method="post">
        <label>Loại:</label>
        <select name="type" required>
            <option value="thu">Thu</option>
            <option value="chi">Chi</option>
        </select><br><br>

        <label>Số tiền:</label><br>
        <input type="number" name="amount" required><br><br>

        <label>Mô tả:</label><br>
        <textarea name="description" required></textarea><br><br>

        <label>Ngày:</label><br>
        <input type="date" name="date" required><br><br>

        <button type="submit">Lưu giao dịch</button>
    </form>

    <p><a href="dashboard.php">← Quay lại trang chính</a></p>
</body>
</html>
