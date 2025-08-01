<?php
session_start();
include "db.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy danh sách giao dịch của người dùng
$sql = "SELECT * FROM transactions WHERE user_id = ? ORDER BY date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lịch sử giao dịch</title>
</head>
<body>
    <h2>Lịch sử giao dịch</h2>
    <a href="add_transaction.php">+ Thêm giao dịch</a> |
    <a href="dashboard.php">← Quay lại Dashboard</a>
    <br><br>

    <table border="1" cellpadding="8">
        <tr>
            <th>STT</th>
            <th>Loại</th>
            <th>Số tiền</th>
            <th>Mô tả</th>
            <th>Ngày</th>
            <th>Hành động</th>
        </tr>

        <?php
        $i = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $i++ . "</td>";
            echo "<td>" . ($row['type'] ? "Thu" : "Chi") . "</td>";
            echo "<td>" . number_format($row['amount'], 2, ',', '.') . " VND</td>";
            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
            echo "<td>" . date("d/m/Y H:i", strtotime($row['date'])) . "</td>";
            echo "<td>
                    <a href='edit_transaction.php?id={$row['id']}'>Sửa</a> |
                    <a href='delete_transaction.php?id={$row['id']}' onclick=\"return confirm('Bạn có chắc chắn muốn xóa?')\">Xóa</a>
                  </td>";
            echo "</tr>";
        }
        ?>
    </table>
</body>
</html>
