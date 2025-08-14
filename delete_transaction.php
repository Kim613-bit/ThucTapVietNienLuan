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
$info_query = "SELECT amount, type, account_id, description FROM transactions WHERE id = $1 AND user_id = $2";
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
  <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Roboto', Arial, sans-serif;
      background-color: #f0f2f5;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    form {
      background-color: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      max-width: 500px;
      width: 100%;
      text-align: left;
    }

    h2 {
      color: #dc3545;
      margin-bottom: 20px;
      font-size: 22px;
    }

    p {
      margin: 10px 0;
      font-size: 16px;
    }

    label {
      display: block;
      margin-top: 20px;
      font-weight: bold;
    }

    input[type="password"] {
      width: 100%;
      padding: 10px;
      margin-top: 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 15px;
    }

    .actions {
      margin-top: 25px;
      display: flex;
      justify-content: space-between;
    }

    button, a {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      text-decoration: none;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    button {
      background-color: #dc3545;
    }

    a {
      background-color: #6c757d;
    }

    button:hover {
      background-color: #c82333;
    }

    a:hover {
      background-color: #5a6268;
    }
  </style>
</head>
<body>
  <form method="post">
    <h2>Xác nhận xóa giao dịch</h2>
    <p><strong>Tài khoản:</strong> <?= htmlspecialchars($account_name) ?></p>
    <p><strong>Loại:</strong> <?= $info['type'] == 0 ? 'Thu' : 'Chi' ?></p>
    <p><strong>Số tiền:</strong> <?= number_format($info['amount'], 2) ?> VND</p>

    <?php
      $desc = trim($info['description'] ?? '');
      if (strpos($desc, 'Tạo tài khoản mới:') === 0) {
          $desc = 'Tạo khoản tiền mới';
      }
    ?>
    <p><strong>Mô tả:</strong> <?= htmlspecialchars($desc ?: 'Không có') ?></p>

    <label for="password">Nhập mật khẩu để xác nhận:</label>
    <input type="password" name="password" id="password" required>

    <input type="hidden" name="confirm" value="yes">
    <div class="actions">
      <button type="submit">✅ Đồng ý</button>
      <a href="dashboard.php">❌ Hủy</a>
    </div>
  </form>
</body>
</html>

