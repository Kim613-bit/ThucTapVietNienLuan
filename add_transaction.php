<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];

    // Kiểm tra số tiền là số hợp lệ
    if (!is_numeric($amount) || $amount <= 0) {
        echo "<p style='color:red;'>Số tiền không hợp lệ!</p>";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO transactions (user_id, type, amount, description, date) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isdss", $user_id, $type, $amount, $description, $date);
        mysqli_stmt_execute($stmt);
        header("Location: transactions.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO transactions (user_id, type, amount, description, date)
            VALUES ('$user_id', '$type', '$amount', '$description', '$date')";
    mysqli_query($conn, $sql);

    header("Location: dashboard.php");
    exit();
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
