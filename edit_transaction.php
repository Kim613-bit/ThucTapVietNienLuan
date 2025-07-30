<?php
session_start();
include "db.php"; // đảm bảo file db.php có kết nối pg_connect()
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: transactions.php");
    exit();
}

// 👉 Khi người dùng cập nhật giao dịch
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];

    $query = "UPDATE transactions SET type = $1, amount = $2, description = $3, date = $4 
              WHERE id = $5 AND user_id = $6";
    $result = pg_query_params($conn, $query, array($type, $amount, $description, $date, $id, $user_id));
    
    header("Location: transactions.php");
    exit();
}

// 👉 Lấy thông tin giao dịch để hiển thị form
$query = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2";
$result = pg_query_params($conn, $query, array($id, $user_id));
$transaction = pg_fetch_assoc($result);

if (!$transaction) {
    echo "Giao dịch không tồn tại hoặc không thuộc quyền truy cập.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sửa giao dịch</title>
</head>
<body>
    <h2>Sửa giao dịch</h2>
    <form method="post">
        <label>Loại:</label><br>
        <select name="type" required>
            <option value="1" <?= $transaction['type'] ? 'selected' : '' ?>>Thu</option>
            <option value="0" <?= !$transaction['type'] ? 'selected' : '' ?>>Chi</option>
        </select><br><br>

        <label>Số tiền:</label><br>
        <input type="number" name="amount" value="<?= $transaction['amount'] ?>" required><br><br>

        <label>Mô tả:</label><br>
        <input type="text" name="description" value="<?= htmlspecialchars($transaction['description']) ?>" required><br><br>

        <label>Ngày:</label><br>
        <input type="date" name="date" value="<?= $transaction['date'] ?>" required><br><br>

        <button type="submit">Cập nhật</button>
    </form>
    <br>
    <a href="transactions.php">← Quay lại</a>
</body>
</html>
