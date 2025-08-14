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

// Truy vấn thông tin giao dịch
$info_query = "SELECT amount, type, account_id FROM transactions WHERE id = $1 AND user_id = $2";
$info_result = pg_query_params($conn, $info_query, array($transaction_id, $user_id));
$info = pg_fetch_assoc($info_result);

if (!$info) {
    echo "Giao dịch không tồn tại hoặc không thuộc quyền truy cập.";
    exit();
}

// Truy vấn tên tài khoản để hiển thị
$account_id = intval($info['account_id']);
$account_name_query = "SELECT name FROM accounts WHERE id = $1 AND user_id = $2";
$account_name_result = pg_query_params($conn, $account_name_query, array($account_id, $user_id));
$account_name = pg_fetch_result($account_name_result, 0, 0);

if ($account_name === false) {
    echo "Tài khoản không tồn tại hoặc không thuộc quyền truy cập.";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm']) && $_POST['confirm'] === "yes") {
        $entered_password = $_POST['password'] ?? '';

        // Truy vấn mật khẩu đã mã hóa từ DB
        $user_query = "SELECT password FROM users WHERE id = $1";
        $user_result = pg_query_params($conn, $user_query, array($user_id));
        $user_data = pg_fetch_assoc($user_result);

        if (!$user_data || !password_verify($entered_password, $user_data['password'])) {
            echo "<p style='color:red;'>Mật khẩu không đúng. Không thể xóa giao dịch.</p>";
            exit();
        }

        // Nếu mật khẩu đúng, tiếp tục xử lý xóa
        $amount = floatval($info['amount']);
        $type = intval($info['type']);
        $adjust_query = ($type == 1)
            ? "UPDATE accounts SET balance = balance + $1 WHERE id = $2 AND user_id = $3"
            : "UPDATE accounts SET balance = balance - $1 WHERE id = $2 AND user_id = $3";
        pg_query_params($conn, $adjust_query, array($amount, $account_id, $user_id));

        $result = pg_query_params($conn,
            "DELETE FROM transactions WHERE id = $1 AND user_id = $2",
            array($transaction_id, $user_id)
        );

        if (!$result) {
            echo "<p style='color:red;'>Lỗi khi xoá giao dịch. Vui lòng thử lại.</p>";
            exit();
        }

        header("Location: dashboard.php");
        exit();
    }
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
      <p><strong>Tài khoản:</strong> <?= htmlspecialchars($account_name) ?></p>
      <p><strong>Loại:</strong> <?= $info['type'] == 0 ? 'Thu' : 'Chi' ?></p>
      <p><strong>Số tiền:</strong> <?= number_format($info['amount'], 2) ?> VND</p>
    
      <label for="password">Nhập mật khẩu để xác nhận:</label><br>
      <input type="password" name="password" required><br><br>
    
      <input type="hidden" name="confirm" value="yes">
      <button type="submit">✅ Đồng ý</button>
      <a href="dashboard.php">❌ Hủy</a>
    
      <p><strong>Mô tả:</strong> <?= htmlspecialchars($info['description'] ?? 'Không có') ?></p>
    </form>
</body>
</html>
