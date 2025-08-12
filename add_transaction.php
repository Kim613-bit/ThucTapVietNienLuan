<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// Xử lý form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $type        = $_POST['type'];
    $rawAmount   = $_POST['amount'] ?? '0';
    $description = trim($_POST['description']);
    $date        = $_POST['date'];

    // 👉 1. Lọc số tiền nhập
    $sanitized = preg_replace('/[^\d\.]/', '', $rawAmount);
    if ($sanitized === '' || !is_numeric($sanitized)) {
        $error = "Số tiền không hợp lệ. Vui lòng nhập số.";
    } else {
        $amount = floatval($sanitized);

        // 👉 2. Kiểm tra giới hạn
        if ($amount <= 0) {
            $error = "Số tiền phải lớn hơn 0.";
        } elseif ($amount > 1000000000000) {
            $error = "Số tiền vượt quá giới hạn (tối đa 1,000,000,000,000 VND).";
        } else {
            // 👉 3. Thêm giao dịch vào DB (dùng prepared statement)
            $stmt = mysqli_prepare($conn, "INSERT INTO transactions (user_id, type, amount, description, date) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isdss", $user_id, $type, $amount, $description, $date);
            mysqli_stmt_execute($stmt);

            header("Location: transactions.php");
            exit();
        }
    }

    // 👉 Nếu có lỗi thì hiển thị
    if ($error !== "") {
        echo "<p style='color:red;'>$error</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Thêm giao dịch</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 560px;
      margin: 60px auto;
      padding: 30px 24px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    h2 {
      text-align: center;
      margin-bottom: 26px;
    }
    label {
      display: block;
      font-weight: bold;
      margin-bottom: 6px;
      font-size: 15px;
    }
    .form-control {
      width: 100%;
      padding: 10px 12px;
      font-size: 16px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
      margin-bottom: 18px;
    }
    button.form-control {
      background-color: #007BFF;
      color: white;
      border: none;
      cursor: pointer;
    }
    button.form-control:hover {
      background-color: #0056b3;
    }
    .back {
      display: block;
      text-align: center;
      margin-top: 22px;
      color: #007BFF;
      text-decoration: none;
    }
    .back:hover {
      text-decoration: underline;
    }
    .flatpickr-wrapper {
      position: relative;
    }
    .calendar-btn {
      position: absolute;
      top: 6px;
      right: 10px;
      background: none;
      border: none;
      font-size: 20px;
      color: #333;
      cursor: pointer;
    }
    @media (max-width: 480px) {
      .form-control, .container > div {
        width: 100%;
        margin-bottom: 14px;
      }
      .flatpickr-wrapper {
        display: block;
        width: 100%;
        margin-bottom: 10px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>➕ Thêm giao dịch</h2>
    <form method="post" action="add_transaction.php">
      <label for="account_id">Khoản tiền:</label>
      <select name="account_id" id="account_id" class="form-control" required>
        <!-- PHP render danh sách tài khoản -->
      </select>

      <label for="type">Loại giao dịch:</label>
      <select name="type" id="type" class="form-control" required>
        <option value="thu">Thu</option>
        <option value="chi">Chi</option>
      </select>

      <label for="amount">Số tiền:</label>
      <input type="text" name="amount" id="amount" class="form-control" placeholder="VD: 500000" required>

      <label for="description">Mô tả:</label>
      <input type="text" name="description" id="description" class="form-control" maxlength="255" placeholder="Nhập mô tả">

      <label>Thời gian giao dịch:</label>
      <div style="display: flex; gap: 12px;">
        <div style="flex: 1; position: relative;">
          <div class="flatpickr-wrapper">
            <input type="text" name="transaction_date" class="form-control" data-input placeholder="Chọn ngày" required>
            <button type="button" class="calendar-btn" data-toggle title="Chọn ngày">📅</button>
          </div>
        </div>
        <div style="flex: 1;">
          <input type="time" name="transaction_time" class="form-control" value="<?= date('H:i') ?>" required>
        </div>
      </div>

      <button type="submit" class="form-control">💾 Lưu giao dịch</button>
    </form>
    <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
  </div>

  <script>
    flatpickr(".flatpickr-wrapper", {
      dateFormat: "d/m/Y",
      locale: "vi",
      defaultDate: new Date(),
      wrap: true,
      allowInput: true
    });
    document.querySelector("[data-toggle]").addEventListener("click", function() {
      document.querySelector("[name='transaction_date']")._flatpickr.open();
    });
  </script>
</body>
</html>

