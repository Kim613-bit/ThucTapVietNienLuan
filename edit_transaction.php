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

    $account = $_POST['account'];
    $time = $_POST['time'];
    $datetime = $date . ' ' . $time;

    $query = "UPDATE transactions 
              SET type = $1, amount = $2, description = $3, date = $4, account = $5 
              WHERE id = $6 AND user_id = $7";
    $result = pg_query_params($conn, $query, array($type, $amount, $description, $datetime, $account, $id, $user_id));

    
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
    .form-box {
      background-color: var(--color-card);
      border: 1px solid #e2e8f0;
      border-radius: var(--border-radius);
      padding: var(--spacing);
      max-width: 600px;
      margin: auto;
    }
    .form-box label {
      font-weight: 600;
      margin-top: 12px;
      display: block;
      font-size: 0.95rem;
    }
    .form-box input,
    .form-box select {
      width: 100%;
      padding: 10px;
      margin-top: 6px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    .datetime-group {
      display: flex;
      gap: 12px;
    }
    .datetime-group input {
      flex: 1;
    }
    .form-box button {
      padding: 10px 16px;
      border: none;
      border-radius: var(--border-radius);
      font-size: 1rem;
      cursor: pointer;
      margin-top: 16px;
      width: 100%;
      background-color: var(--color-primary);
      color: white;
    }
    .form-box button:hover {
      background-color: #1565c0;
    }
    .btn-delete {
      background-color: var(--color-danger);
    }
    .btn-delete:hover {
      background-color: #b71c1c;
    }
    .back-link {
      display: block;
      margin-top: 16px;
      text-align: center;
      color: var(--color-muted);
      font-size: 0.9rem;
    }
    .back-link:hover {
      color: var(--color-primary);
    }
  </style>
</head>
<body>
  <main class="main">
    <div class="content">
      <h2>✏️ Sửa giao dịch</h2>
    
      <form method="post" class="form-box">
        <label for="type">Loại giao dịch:</label>
        <select name="type" id="type" required>
          <option value="income" <?= $transaction['type'] === 'income' ? 'selected' : '' ?>>Thu</option>
          <option value="expense" <?= $transaction['type'] === 'expense' ? 'selected' : '' ?>>Chi</option>
        </select>
    
        <label for="amount">Số tiền:</label>
        <input type="number" name="amount" id="amount" value="<?= htmlspecialchars($transaction['amount']) ?>" maxlength="10" step="1000" required>

        <label for="description">Nội dung giao dịch:</label>
        <input type="text" name="description" id="description" value="<?= htmlspecialchars($transaction['description']) ?>" maxlength="30">    
        <label for="account">Khoản tiền:</label>
        <select name="account" id="account" required>
          <option value="Bank" <?= $transaction['account'] === 'Bank' ? 'selected' : '' ?>>Bank</option>
          <option value="Tiền mặt" <?= $transaction['account'] === 'Tiền mặt' ? 'selected' : '' ?>>Tiền mặt</option>
        </select>

    
        <label for="date">Thời gian giao dịch:</label>
        <div class="datetime-group">
          <?php
            $datetime = strtotime($transaction['date']);
            $dateValue = date('Y-m-d', $datetime);
            $timeValue = date('H:i', $datetime);
            ?>
            <input type="date" name="date" id="date" value="<?= $dateValue ?>" required>
            <input type="time" name="time" id="time" value="<?= $timeValue ?>" required>
        </div>
    
        <button type="submit">💾 Lưu thay đổi</button>
        <a href="dashboard.php" class="back-link">← Quay lại Dashboard</a>
      </form>
    </div>
  </main>
</body>
</html>
