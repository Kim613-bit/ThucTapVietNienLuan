<?php
session_start();
include "db.php";
define('MAX_BALANCE', 100000000);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;

// 🔹 Lấy thông tin tài khoản
$sql    = "SELECT * FROM accounts WHERE id = $1 AND user_id = $2";
$result = pg_query_params($conn, $sql, [ $account_id, $user_id ]);
$account = pg_fetch_assoc($result);

if (! $account) {
    echo "Tài khoản không tồn tại.";
    exit();
}

$success = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 🔸 Xóa tài khoản
    if (isset($_POST['delete_account']) && $_POST['delete_account'] === 'yes') {
        pg_query($conn, 'BEGIN');
        try {
            pg_query_params($conn,
                "DELETE FROM transactions WHERE account_id = $1 AND user_id = $2",
                [ $account_id, $user_id ]
            );
            pg_query_params($conn,
                "DELETE FROM accounts WHERE id = $1 AND user_id = $2",
                [ $account_id, $user_id ]
            );
            pg_query($conn, 'COMMIT');
            header("Location: dashboard.php?deleted=1");
            exit();
        } catch (Exception $e) {
            pg_query($conn, 'ROLLBACK');
            $error = "❌ Lỗi xoá: " . $e->getMessage();
        }
    }
    else {
        // 🔸 Cập nhật tên và giao dịch
        $new_name    = trim($_POST['name']);
        $type        = $_POST['type'] ?? '';
        $rawAmount   = $_POST['amount'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $name_changed = $new_name !== $account['name'];
                
        try {
            pg_query($conn, 'BEGIN');

            // 🔸 Đổi tên nếu cần
            if ($name_changed) {
                pg_query_params($conn,
                    "UPDATE accounts SET name = $1 WHERE id = $2 AND user_id = $3",
                    [ $new_name, $account_id, $user_id ]
                );
                $now = date('Y-m-d H:i:s'); 
                pg_query_params($conn,
                    "INSERT INTO transactions
                     (account_id, user_id, type, amount, description, remaining_balance, date)
                     VALUES ($1, $2, 2, 0, $3, $4, $5)",
                    [ $account_id, $user_id,
                      "Đổi tên từ '{$account['name']}' thành '{$new_name}'",
                      $account['balance'], $now ]
                );
            }

            // 🔸 Giao dịch thu/chi nếu có
            if ($type === 'thu' || $type === 'chi') {
                $date_input = $_POST['transaction_date'] ?? '';
                $time_input = $_POST['transaction_time'] ?? date('H:i');
                
                // Kiểm tra định dạng dd/mm/yyyy
                if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date_input) || !preg_match('/^\d{2}:\d{2}$/', $time_input)) {
                    throw new Exception("Ngày giờ không hợp lệ (dd/mm/yyyy & HH:mm).");
                }
                
                // Chuyển đổi định dạng ngày sang yyyy-mm-dd
                $dtObj = DateTime::createFromFormat('d/m/Y H:i', "$date_input $time_input");
                if (!$dtObj) {
                    throw new Exception("Ngày giờ không hợp lệ.");
                }
                $datetime = $dtObj->format('Y-m-d H:i:s');

                $test = DateTime::createFromFormat('Y-m-d H:i', $datetime);
                if (!$test) {
                    throw new Exception("Ngày giờ không hợp lệ.");
                }

                $sanitized = preg_replace('/[^\d\.\-]/', '', $rawAmount);
                if (!is_numeric($sanitized)) throw new Exception("Số tiền không hợp lệ.");

                $amount = floatval($sanitized);
                if ($amount <= 0) throw new Exception("Số tiền phải > 0.");
                if ($amount > MAX_BALANCE) throw new Exception("Số tiền vượt giới hạn.");

                $type_value = ($type === 'chi') ? 1 : 0;
                $new_balance = ($type_value === 0)
                             ? $account['balance'] + $amount
                             : $account['balance'] - $amount;

                if ($new_balance < 0 || $new_balance > MAX_BALANCE) {
                    throw new Exception("Số dư sau giao dịch >99,999,999 .");
                }

                pg_query_params($conn,
                    "UPDATE accounts SET balance = $1 WHERE id = $2 AND user_id = $3",
                    [ $new_balance, $account_id, $user_id ]
                );

                if ($description === '') {
                    $description = $type_value === 0 ? 'Giao dịch thu' : 'Giao dịch chi';
                }

                pg_query_params($conn,
                    "INSERT INTO transactions
                     (account_id, user_id, type, amount, description, remaining_balance, date)
                     VALUES ($1, $2, $3, $4, $5, $6, $7)",
                    [ $account_id, $user_id, $type_value, $amount, $description, $new_balance, $datetime ]
                );

                $account['balance'] = $new_balance;
            }

            pg_query($conn, 'COMMIT');
            $account['name'] = $new_name;
            $success = "✅ Cập nhật thành công!";
        } catch (Exception $e) {
            pg_query($conn, 'ROLLBACK');
            $error = "❌ Lỗi cập nhật: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sửa khoản tiền</title>
  <style>
      @media (max-width: 480px) {
          .container div[style*="display: flex"] {
            flex-direction: column;
          }
        }
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
    .danger {
      background-color: #dc3545;
    }
    .danger:hover {
      background-color: #b02a37;
    }
    .success {
      color: green;
      text-align: center;
      margin-bottom: 16px;
    }
    .error {
      color: red;
      text-align: center;
      margin-bottom: 16px;
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
    .calendar-btn {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
    }
  </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
  <div class="container">
    <h2>✏️ Sửa khoản tiền</h2>

    <?php if ($success): ?>
      <p class="success"><?= $success ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
      <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="post" id="balanceForm"
          onsubmit="return confirm('Bạn có chắc chắn muốn lưu thay đổi không?');">
      <!-- Tên tài khoản -->
      <label>Tên khoản tiền:</label>
      <input
        type="text"
        name="name"
        maxlength="30"
        value="<?= htmlspecialchars($account['name']) ?>"
        required
        class="form-control"
      >

      <!-- Số dư hiện tại -->
      <label>Số dư hiện tại:</label>
      <input
        type="text"
        readonly
        value="<?= number_format($account['balance'], 0, ',', '.') ?> VND"
        class="form-control"
      >

      <!-- Loại giao dịch -->
      <label>Loại giao dịch:</label>
      <select
        name="type"
        id="transactionType"
        onchange="toggleFields()"
        class="form-control"
      >
        <option value="">-- Đổi tên khoản tiền --</option>
        <option value="thu">Thu</option>
        <option value="chi">Chi</option>
      </select>

      <!-- Nhóm trường giao dịch (ẩn/hiện) -->
      <div id="transactionFields" style="display: none;">
        <label>Số tiền:</label>
        <input
          type="text"
          id="amount"
          name="amount"
          maxlength="10"
          class="form-control"
          value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
        >

        <?php if (!empty($error)): ?>
              <p class="error"><?= $error ?></p>
            <?php endif; ?>
        <label>Nội dung giao dịch:</label>
        <input
          list="suggestions"
          name="description"
          maxlength="30"
          placeholder="Nhập hoặc chọn nội dung"
          value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
          class="form-control"
        >
        <datalist id="suggestions">
          <?php foreach ($descriptions as $desc): ?>
            <option value="<?= htmlspecialchars($desc) ?>">
          <?php endforeach; ?>
        </datalist>
          <div style="display: flex; gap: 12px;">
              <div style="flex: 1;" >
                <label>Ngày giao dịch (dd/mm/yyyy):</label>
                <div class="flatpickr-wrapper" style="position: relative;">
                  <input
                    type="text"
                    id="datepicker"
                    name="transaction_date"
                    class="form-control"
                    data-input
                    placeholder="VD: 05/08/2025"
                    required
                  >
                  <button type="button" class="calendar-btn" data-toggle title="Chọn ngày">📅</button>
                </div>
              </div>
            
              <div style="flex: 1;">
                <label>Giờ giao dịch (HH:mm):</label>
                <input
                  type="time"
                  name="transaction_time"
                  class="form-control"
                  value="<?= htmlspecialchars($_POST['transaction_time'] ?? date('H:i')) ?>"
                  required
                >
      </div>
    
    </div>
      <button type="submit" class="form-control">💾 Lưu thay đổi</button>
    </form>

    <form method="post"
          onsubmit="return confirm('Bạn có chắc chắn muốn xóa khoản tiền này không?');">
      <input type="hidden" name="delete_account" value="yes">
      <button type="submit" class="form-control danger">
        🗑️ Xóa khoản tiền
      </button>
    </form>

    <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
  </div>
  <?php $currentBalance = $account['balance']; ?>

    <script>
    const currentBalance = <?= $currentBalance ?>;

    function toggleFields() {
      const type   = document.getElementById("transactionType").value;
      const fields = document.getElementById("transactionFields");
      const amt    = document.getElementById("amount");
      const desc   = document.querySelector('input[name="description"]');
    
      if (type === "thu" || type === "chi") {
        fields.style.display = "block";
        amt.required  = true;
        desc.required = true;
    
        // ✅ Cập nhật placeholder tùy theo loại giao dịch
        const maxLimit = (type === "thu")
          ? 99999999 - currentBalance
          : currentBalance;
    
        amt.placeholder = "Tối đa " + maxLimit.toLocaleString("vi-VN") + " VND";
      } else {
        fields.style.display = "none";
        amt.required  = false;
        desc.required = false;
        amt.placeholder = ""; // Ẩn placeholder nếu không chọn loại
      }
    }

  function formatWithCommas(value) {
    const parts = value.split('.');
    parts[0] = parts[0]
      .replace(/^0+(?=\d)|\D/g, '')
      .replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
  }

  document.addEventListener("DOMContentLoaded", function() {
    toggleFields();

    const form       = document.getElementById("balanceForm");
    const amt        = document.getElementById("amount");
    const type       = document.getElementById("transactionType");
    const submitBtn  = document.querySelector('button[type="submit"]');
    const warning    = document.getElementById("amountWarning") || document.createElement('small');

    // 💵 Tự động format số tiền khi nhập
    amt.addEventListener("input", function() {
      const oldPos = this.selectionStart;
      let raw = this.value.replace(/,/g, '');
      if (raw === '' || raw === '.') {
        this.value = raw;
        return;
      }
      const [intPart, decPart] = raw.split('.');
      let formatted = formatWithCommas(intPart);
      if (decPart !== undefined) {
        formatted += '.' + decPart.replace(/\D/g, '');
      }
      this.value = formatted;
      const newPos = oldPos + (this.value.length - raw.length);
      setTimeout(() => this.setSelectionRange(newPos, newPos), 0);
    });

    // ✅ Xử lý kiểm tra trước khi submit
    form.addEventListener("submit", function(e) {
      const raw = amt.value.replace(/,/g, '');
      const number = parseFloat(raw);
      const selectedType = type.value;
      const maxLimit = (selectedType === "thu")
        ? 99999999 - currentBalance
        : currentBalance; // 👈 điều kiện cho "chi"

      if ((selectedType === "thu" || selectedType === "chi") &&
          (!raw || isNaN(number) || number <= 0 || number > maxLimit)) {
        e.preventDefault();
        warning.textContent = "⚠️ Số tiền không hợp lệ. Vui lòng nhập ≤ " + maxLimit.toLocaleString("vi-VN") + " VND.";
        warning.classList.add("error");
        warning.style.display = "block";
        amt.style.borderColor = "red";

        if (!document.getElementById("amountWarning")) {
          warning.id = "amountWarning";
          amt.parentNode.insertBefore(warning, amt.nextSibling);
        }
        amt.focus();
      } else {
        warning.style.display = "none";
        amt.style.borderColor = "#ccc";
        submitBtn.disabled = true;
        submitBtn.textContent = "⏳ Đang xử lý...";
      }
    });
  });
    flatpickr(".flatpickr-wrapper", {
      dateFormat: "d/m/Y",
      locale: "vn",
      defaultDate: "<?= date('d/m/Y') ?>",
      wrap: true,
      allowInput: true
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
</body>
</html>
