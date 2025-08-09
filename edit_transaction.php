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
    $type        = $_POST['type'];
    $rawAmount   = $_POST['amount'] ?? '0';
    $description = trim($_POST['description']);
    $date        = $_POST['date'];

    // ✅ Kiểm tra & lọc số tiền
    $sanitized = preg_replace('/[^\d\.]/', '', $rawAmount);
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

    // ✅ Thực hiện truy vấn cập nhật
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
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sửa giao dịch</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .form-panel {
      background: var(--color-card);
      padding: 24px;
      border-radius: var(--border-radius);
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      max-width: 600px;
      margin: 40px auto;
    }
    .form-group {
      margin-bottom: 16px;
      display: flex;
      flex-direction: column;
    }
    .form-group label {
      font-weight: 600;
      margin-bottom: 6px;
    }
    .form-group input,
    .form-group select {
      padding: 10px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 1rem;
    }
    .form-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 24px;
    }
    .form-actions button {
      background: var(--color-primary);
      color: white;
      padding: 10px 16px;
      border: none;
      border-radius: var(--border-radius);
      cursor: pointer;
    }
    .form-actions .delete {
      color: var(--color-danger);
      text-decoration: none;
      font-weight: 500;
    }
  </style>
</head>
<body>
  <main class="main">
    <h2 style="text-align:center;">✏️ Sửa giao dịch</h2>
    <form method="post" class="form-panel">
      <div class="form-group">
        <label for="type">Loại giao dịch</label>
        <select name="type" id="type" required>
          <option value="0" <?= $transaction['type'] == 0 ? 'selected' : '' ?>>Thu</option>
          <option value="1" <?= $transaction['type'] == 1 ? 'selected' : '' ?>>Chi</option>
          <option value="2" <?= $transaction['type'] == 2 ? 'selected' : '' ?>>Cập nhật tài khoản</option>
        </select>
      </div>

      <div class="form-group">
        <label for="amount">Số tiền</label>
        <input type="number" name="amount" id="amount" min="0" required value="<?= htmlspecialchars($transaction['amount']) ?>">
      </div>

      <div class="form-group">
        <label for="description">Mô tả</label>
        <input type="text" name="description" id="description" maxlength="100" value="<?= htmlspecialchars($transaction['description']) ?>">
      </div>

      <div class="form-group">
        <label for="transaction_date">Ngày giao dịch</label>
        <input type="date" name="transaction_date" id="transaction_date" required value="<?= date('Y-m-d', strtotime($transaction['date'])) ?>">
      </div>

      <div class="form-group">
        <label for="transaction_time">Giờ giao dịch</label>
        <input type="time" name="transaction_time" id="transaction_time" required value="<?= date('H:i', strtotime($transaction['date'])) ?>">
      </div>

      <div class="form-actions">
        <button type="submit">💾 Lưu thay đổi</button>
        <a href="delete_transaction.php?id=<?= $id ?>" class="delete" onclick="return confirm('Bạn có chắc muốn xoá giao dịch này?')">🗑️ Xoá giao dịch</a>
      </div>
    </form>
  </main>
</body>
</html>


