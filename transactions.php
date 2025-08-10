<?php
session_start();
include "db.php"; // Kết nối PostgreSQL bằng pg_connect()

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_SESSION['message'])) {
    echo '<p style="color: green; font-weight: bold; text-align: center;">' . $_SESSION['message'] . '</p>';
    unset($_SESSION['message']);
}

// Lấy danh sách giao dịch của người dùng
$query = "SELECT * FROM transactions WHERE user_id = $1 ORDER BY date DESC";
$result = pg_query_params($conn, $query, array($user_id));

if (!$result) {
    echo "<p style='color:red;'>❌ Lỗi truy vấn: " . pg_last_error($conn) . "</p>";
    exit();
}
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
        while ($row = pg_fetch_assoc($result)) {
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
