<?php
session_start();
include "db.php"; // đảm bảo file db.php có kết nối pg_connect()
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$id) {
    header("Location: dashboard.php");
    exit();
}

// 👉 Khi người dùng cập nhật giao dịch
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type        = $_POST['type'];
    $type_code = ($type === 'thu') ? 1 : 2;
    $rawAmount   = $_POST['amount'] ?? '0';
    $description = trim($_POST['content'] ?? '');

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

    $account_id = intval($_POST['account_id']);
    $date_input = $_POST['transaction_date'] ?? date('d/m/Y', strtotime($transaction['date']));
    $time = $_POST['transaction_time'] ?? date('H:i', strtotime($transaction['date'])); 
    
    // Chuyển định dạng từ d/m/Y sang Y-m-d
    $dateObj = DateTime::createFromFormat('d/m/Y', $date_input);
    $formattedDate = $dateObj ? $dateObj->format('Y-m-d') : date('Y-m-d');
    
    $datetime = $formattedDate . ' ' . $time;


    $query = "UPDATE transactions 
              SET type = $1, amount = $2, description = $3, date = $4, account_id = $5 
              WHERE id = $6 AND user_id = $7";
    $result = pg_query_params($conn, $query, array($type_code, $amount, $description, $datetime, $account_id, $id, $user_id));
   
    $_SESSION['message'] = "✅ Giao dịch đã được cập nhật thành công.";
    header("Location: dashboard.php");
    exit();
}


// 👉 Lấy thông tin giao dịch để hiển thị form
$query = "SELECT t.*, a.name AS account_name, a.balance AS current_balance
          FROM transactions t
          JOIN accounts a ON t.account_id = a.id
          WHERE t.id = $1 AND t.user_id = $2";
$result = pg_query_params($conn, $query, array($id, $user_id));
$transaction = pg_fetch_assoc($result);

if (!$transaction) {
    echo "Giao dịch không tồn tại hoặc không thuộc quyền truy cập.";
    exit();
}

// Gán biến để sử dụng trong HTML
$account_name = $transaction['account_name'] ?? 'Không xác định';
$current_balance = floatval($transaction['current_balance'] ?? 0);
$transaction_type = $transaction['type'] ?? 'thu';
$amount = floatval($transaction['amount'] ?? 0);
$selected_content = $transaction['description'] ?? '';
$datetime = $transaction['date'] ?? date('Y-m-d H:i');
$date = date('Y-m-d', strtotime($datetime));
$time = date('H:i', strtotime($datetime));
$account_id = $transaction['account_id'] ?? 0;

// Gán danh sách nội dung mẫu
$content_options = ["Ăn uống", "Đi lại", "Lương", "Thưởng"];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Sửa giao dịch</title>
  <link rel="stylesheet" href="styles.css">
  <script>
    function updateMaxAmount() {
      const type = document.getElementById('type').value;
      const balanceRaw = document.getElementById('balance').value.replace(/[^\d]/g, '');
      const balance = parseInt(balanceRaw);
      const amountInput = document.getElementById('amount');
      if (type === 'thu') {
        amountInput.max = 99999999 - balance;
      } else {
        amountInput.max = balance;
      }
    }
  </script>
    <style>
        /* Reset & base styles */
        body {
          margin: 0;
          padding: 0;
          font-family: 'Segoe UI', Tahoma, sans-serif;
          background-color: #f4f6f8;
          color: #333;
        }
        
        /* Container */
        .container {
          max-width: 600px;
          margin: 40px auto;
          background-color: #fff;
          padding: 30px 40px;
          border-radius: 12px;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        /* Heading */
        h1 {
          text-align: center;
          color: #2c3e50;
          margin-bottom: 30px;
        }
        
        /* Labels & inputs */
        label {
          display: block;
          margin-top: 20px;
          font-weight: 600;
          color: #34495e;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="time"],
        select,
        input[list] {
          width: 100%;
          padding: 10px 12px;
          margin-top: 8px;
          border: 1px solid #ccc;
          border-radius: 6px;
          box-sizing: border-box;
          font-size: 15px;
        }
        
        /* Time row */
        div[style*="display: flex"] {
          margin-top: 8px;
        }
        
        /* Submit button */
        input[type="submit"] {
          background-color: #007BFF;;
          color: white;
          padding: 12px;
          margin-top: 30px;
          border: none;
          border-radius: 6px;
          cursor: pointer;
          font-size: 16px;
          width: 100%;
          transition: background-color 0.3s ease;
        }
        
        input[type="submit"]:hover {
          background-color: #2980b9;
        }
        
        /* Back link */
        .back-link {
          display: block;
          text-align: center;
          margin-top: 20px;
          color: #7f8c8d;
          text-decoration: none;
          font-size: 14px;
        }
        
        .back-link:hover {
          text-decoration: underline;
        }

        .btn-save {
          background-color: #2ecc71;
          color: white;
          padding: 12px 20px;
          border: none;
          border-radius: 8px;
          font-size: 16px;
          font-weight: 600;
          cursor: pointer;
          transition: background-color 0.3s ease, transform 0.2s ease;
        }
        
        .btn-save:hover {
          background-color: #0056b3;
          transform: translateY(-2px);
        }
        
        .btn-back {
          display: block;
          text-align: center;
          margin-top: 22px;
          color: #007BFF;
          text-decoration: none;
        }
        
        .btn-back:hover {
          text-decoration: underline;
        }
        .flatpickr-wrapper {
          position: relative;
          width: 100%;
        }
        
        .flatpickr-wrapper input {
          width: 100%;
          height: 38px;
          font-size: 15px;
          padding-right: 40px; /* chừa chỗ cho nút 📅 */
        }
        
        .calendar-btn {
          position: absolute;
          top: 50%;
          right: 10px;
          transform: translateY(-50%);
          background: none;
          border: none;
          font-size: 20px;
          color: #333;
          cursor: pointer;
        }
    </style>
</head>
<body onload="updateMaxAmount()">
  <div class="container">
    <h1>✏️ Sửa giao dịch</h1>
    <form action="edit_transaction.php?id=<?= $id ?>" method="POST">
    <input type="hidden" name="id" value="<?= $id ?>">
      <label>Tên khoản tiền</label>
      <input type="text" name="account" value="<?= $account_name ?>" readonly>
        <input type="hidden" name="account_id" value="<?= $account_id ?>">

      <label>Số dư hiện tại</label>
      <input type="text" id="balance" value="<?= number_format((float)$current_balance, 0, ',', '.') ?> VND" readonly>

      <label>Loại giao dịch</label>
      <select name="type" id="type" onchange="updateMaxAmount()">
        <option value="thu" <?= $transaction_type === 'thu' ? 'selected' : '' ?>>Thu</option>
        <option value="chi" <?= $transaction_type === 'chi' ? 'selected' : '' ?>>Chi</option>
      </select>

      <label>Số tiền</label>
      <input type="text" id="amount" name="amount" value="<?= number_format($amount, 0, ',', ',') ?>" required>

      <label>Nội dung giao dịch</label>
      <input list="content-list" name="content" value="<?= $selected_content ?>">
      <datalist id="content-list">
        <?php foreach ($content_options as $option): ?>
          <option value="<?= $option ?>">
        <?php endforeach; ?>
      </datalist>

      <label>Thời gian giao dịch:</label>
        <div style="display: flex; gap: 12px;">
          <div style="flex: 1; position: relative;">
            <div class="flatpickr-wrapper">
              <input
                type="text"
                id="datepicker"
                name="transaction_date"
                class="form-control"
                data-input
                placeholder="Chọn ngày"
                value="<?= htmlspecialchars($_POST['transaction_date'] ?? date('d/m/Y', strtotime($datetime))) ?>"
                required
              >
              <button type="button" class="calendar-btn" data-toggle title="Chọn ngày">📅</button>
            </div>
          </div>
        
          <div style="flex: 1;">
            <input
              type="time"
              name="transaction_time"
              class="form-control"
              value="<?= htmlspecialchars($_POST['transaction_time'] ?? date('H:i', strtotime($datetime))) ?>"
              required
            >
          </div>
        </div>

      <input type="submit" value="💾 Lưu thay đổi" class="btn-save">
        <a href="dashboard.php" class="btn-back">← Quay lại Dashboard</a>
    </form>
  </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const calendarBtn = document.querySelector(".calendar-btn");
    const datepickerInstance = flatpickr("#datepicker", {
      dateFormat: "d/m/Y",
      defaultDate: "<?= date('d/m/Y', strtotime($datetime)) ?>"
    });
    
    calendarBtn.addEventListener("click", function () {
      datepickerInstance.open();
    });
    const amountInput = document.getElementById('amount');

      amountInput.addEventListener('input', function () {
        let raw = this.value.replace(/,/g, '');
        if (!isNaN(raw) && raw !== '') {
          this.value = parseFloat(raw).toLocaleString('en-US');
        } else {
          this.value = '';
        }
      });
    document.querySelector('form').addEventListener('submit', function () {
    const raw = amountInput.value.replace(/,/g, '');
    amountInput.value = raw;
  });
</script>
</body>
</html>
