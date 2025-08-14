<?php
session_start();
include "db.php"; // Kết nối bằng pg_connect()
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$transaction_id) {
    echo "ID giao dịch không hợp lệ.";
    exit();
}

// Truy vấn thông tin giao dịch
$info_query = "SELECT amount, type, account_id, description FROM transactions WHERE id = $1 AND user_id = $2";
$info_result = pg_query_params($conn, $info_query, [$transaction_id, $user_id]);
$info = pg_fetch_assoc($info_result);

if (!$info) {
    echo "Giao dịch không tồn tại hoặc không thuộc quyền truy cập.";
    exit();
}

$amount = floatval($info['amount']);
$type = intval($info['type']);
$account_id = intval($info['account_id']);

// Truy vấn số dư hiện tại
$balance_query = "SELECT balance FROM accounts WHERE id = $1 AND user_id = $2";
$balance_result = pg_query_params($conn, $balance_query, [$account_id, $user_id]);
$balance_data = pg_fetch_assoc($balance_result);
$current_balance = floatval($balance_data['balance']);

// Tính số dư sau khi xoá
$new_balance = ($type == 1) ? $current_balance + $amount : $current_balance - $amount;


// Truy vấn tên tài khoản để hiển thị
$account_name_query = "SELECT name FROM accounts WHERE id = $1 AND user_id = $2";
$account_name_result = pg_query_params($conn, $account_name_query, [$account_id, $user_id]);
$account_name = pg_fetch_result($account_name_result, 0, 0);
if ($account_name === false) {
    echo "Tài khoản không tồn tại hoặc không thuộc quyền truy cập.";
    exit();
}

$step = $_POST['step'] ?? 'info';

if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === "confirm") {
    $entered_password = $_POST['password'] ?? '';

    // Truy vấn mật khẩu đã mã hoá
    $user_query = "SELECT password FROM users WHERE id = $1";
    $user_result = pg_query_params($conn, $user_query, [$user_id]);
    $user_data = pg_fetch_assoc($user_result);

    if (!$user_data || !password_verify($entered_password, $user_data['password'])) {
        echo "<p style='color:red;'>Mật khẩu không đúng. Không thể xoá giao dịch.</p>";
        exit();
    }

    // Thực hiện xoá trong transaction
    pg_query($conn, 'BEGIN');
    try {
        $adjust_query = ($type == 1)
            ? "UPDATE accounts SET balance = balance + $1 WHERE id = $2 AND user_id = $3"
            : "UPDATE accounts SET balance = balance - $1 WHERE id = $2 AND user_id = $3";
        pg_query_params($conn, $adjust_query, [$amount, $account_id, $user_id]);

        pg_query_params($conn, "DELETE FROM transactions WHERE id = $1 AND user_id = $2", [$transaction_id, $user_id]);

        pg_query($conn, 'COMMIT');
        header("Location: dashboard.php?deleted=1");
        exit();
    } catch (Exception $e) {
        pg_query($conn, 'ROLLBACK');
        echo "<p style='color:red;'>Lỗi khi xoá: " . htmlspecialchars($e->getMessage()) . "</p>";
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
      font-size: 22px;
      margin-bottom: 20px;
      color: #dc3545;
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

    .actions button,
    .actions a {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      text-decoration: none;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .actions button {
      background-color: #dc3545;
    }
    .actions a {
      background-color: #6c757d;
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
    .actions button:hover {
      background-color: #c82333;
    }
    
    .actions a:hover {
      background-color: #5a6268;
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
      .overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
      }
      .confirm-box {
        background: white;
        padding: 30px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        text-align: center;
        max-width: 400px;
        font-family: 'Roboto', sans-serif;
      }
      .confirm-box h3 {
        color: #dc3545;
        margin-bottom: 20px;
      }
      .confirm-box p {
        margin: 10px 0;
        font-size: 16px;
      }
      .confirm-actions {
        margin-top: 25px;
        display: flex;
        justify-content: space-between;
      }
      .confirm-actions button,
      .confirm-actions a {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: bold;
        text-decoration: none;
        color: white;
        cursor: pointer;
        transition: background-color 0.3s ease;
      }
      .confirm-actions button {
        background-color: #dc3545;
      }
      .confirm-actions a {
        background-color: #6c757d;
      }
      .confirm-actions button:hover {
        background-color: #c82333;
      }
      .confirm-actions a:hover {
        background-color: #5a6268;
      }
  </style>
</head>
<body>
    <form method="post">
      <h2>🗑️ Xóa giao dịch</h2>
    
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
        <?php if ($step === 'info' && $new_balance < 0): ?>
          <div class="overlay">
            <div class="confirm-box">
              <h3>⚠️ Số dư sẽ bị âm nếu xoá giao dịch này</h3>
              <p>Số dư hiện tại: <?= number_format($current_balance, 0, ',', '.') ?> VND</p>
              <p>Số dư sau khi xoá: <?= number_format($new_balance, 0, ',', '.') ?> VND</p>
              <input type="hidden" name="step" value="confirm">
              <div class="confirm-actions">
                <button type="submit">✅ Xóa giao dịch</button>
                <a href="dashboard.php">← Quay lại</a>
              </div>
            </div>
          </div>
        <?php endif; ?>

      <?php if ($step === 'confirm'): ?>
        <?php if (isset($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
        <label for="password">🔐 Nhập mật khẩu để xác nhận:</label>
        <input type="password" name="password" id="password" required>
        <input type="hidden" name="step" value="confirm">
        <div class="actions">
          <button type="submit">✅ Xác nhận xóa</button>
          <a href="dashboard.php">← Quay lại Dashboard</a>
        </div>
      <?php else: ?>
        <input type="hidden" name="step" value="confirm">
        <div class="actions">
          <button type="submit">🗑️ Xóa giao dịch</button>
          <a href="dashboard.php">← Quay lại Dashboard</a>
        </div>
      <?php endif; ?>
    </form>
</body>
</html>

