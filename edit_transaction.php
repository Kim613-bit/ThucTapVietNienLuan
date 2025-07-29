<?php
session_start();
include "db.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy ID giao dịch
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: transactions.php");
    exit();
}

// Xử lý cập nhật khi submit form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];

    $sql = "UPDATE transactions SET type = ?, amount = ?, description = ?, date = ? 
            WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "idssii", $type, $amount, $description, $date, $id, $user_id);
    mysqli_stmt_execute($stmt);

    header("Location: transactions.php");
    exit();
}

// Lấy thông tin giao dịch cần sửa
$sql = "SELECT * FROM transactions WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$transaction = mysqli_fetch_assoc($result);

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
