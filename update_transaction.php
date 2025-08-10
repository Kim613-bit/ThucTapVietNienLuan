<?php
session_start();
include "db.php"; // Đảm bảo kết nối CSDL bằng pg_connect()
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ✅ Kiểm tra đăng nhập
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

// ✅ Xử lý khi người dùng gửi form cập nhật
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'];
    $rawAmount = $_POST['amount'] ?? '0';
    $description = trim($_POST['content']); // Tên input là 'content' trong form
    $date = $_POST['date'];
    $time = $_POST['time'];
    $account_id = intval($_POST['account_id'] ?? 0); // Nếu cần, bạn có thể truyền thêm hidden input cho account_id

    // 👉 Ghép ngày và giờ thành datetime
    $datetime = $date . ' ' . $time;

    // ✅ Làm sạch và kiểm tra số tiền
    $sanitized = preg_replace('/[^\\d\\.]/', '', $rawAmount);
    if ($sanitized === '' || !is_numeric($sanitized)) {
        echo "<p style='color:red;'>Số tiền không hợp lệ. Vui lòng nhập số.</p>";
        exit();
    }

    $amount = floatval($sanitized);
    if ($amount <= 0) {
        echo "<p style='color:red;'>Số tiền phải lớn hơn 0.</p>";
        exit();
    } elseif ($amount > 1000000000000) {
        echo "<p style='color:red;'>Số tiền vượt quá giới hạn (tối đa 1,000,000,000,000 VND).</p>";
        exit();
    }

    // ✅ Cập nhật giao dịch
    $query = "UPDATE transactions SET type = $1, amount = $2, description = $3, date = $4, account_id = $5 WHERE id = $6 AND user_id = $7";
    $result = pg_query_params($conn, $query, array($type, $amount, $description, $datetime, $account_id, $id, $user_id));

    if ($result) {
        $_SESSION['message'] = "✅ Giao dịch đã được cập nhật thành công.";
    } else {
        $_SESSION['message'] = "❌ Có lỗi xảy ra khi cập nhật giao dịch.";
    }

    header("Location: transactions.php");
    exit();
}
?>
