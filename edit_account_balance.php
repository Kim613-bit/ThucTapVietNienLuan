<?php
session_start();
include "db.php";
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
    if (isset($_POST['delete_account']) && $_POST['delete_account'] === 'yes') {
        $input_password = $_POST['confirm_password'] ?? '';

        $sql = "SELECT password FROM users WHERE id = $1";
        $res = pg_query_params($conn, $sql, [ $user_id ]);
        $user = pg_fetch_assoc($res);

        if (! $user || !password_verify($input_password, $user['password'])) {
            $error = "❌ Mật khẩu không đúng. Không thể xóa khoản tiền.";
        } else {
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
    } elseif (isset($_POST['name'])) {
        $new_name = trim($_POST['name']);
        if ($new_name !== '' && $new_name !== $account['name']) {
            try {
                pg_query_params($conn,
                  "INSERT INTO transactions (user_id, account_id, type, description, amount, created_at)
                   VALUES ($1, $2, $3, $4, $5, NOW())",
                  [ $user_id, $account_id, 'update', 'Đổi tên khoản tiền thành: ' . $new_name, 0 ]
                );
                $account['name'] = $new_name;
                $success = "✅ Đã đổi tên khoản tiền!";
            } catch (Exception $e) {
                $error = "❌ Lỗi cập nhật: " . htmlspecialchars($e->getMessage());
            }
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
          .form-control,
          .container > div {
            width: 100%;
            margin-bottom: 14px;
          }
        
          .flatpickr-wrapper {
            display: block;
            width: 100%;
            margin-bottom: 10px;
          }
        
          #transaction-time {
            display: block;
            width: 100%;
            margin-bottom: 10px;
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
    label {
      font-weight: bold;
      margin-bottom: 6px;
      display: inline-block;
    }
  </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
  <div class="container">
    <h2>✏️ Đổi tên khoản tiền</h2>

    <?php if ($success): ?>
      <p class="success"><?= $success ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
      <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="post" id="balanceForm" onsubmit="return confirm('Bạn có chắc chắn muốn lưu thay đổi không?');">
      <!-- Tên khoản tiền -->
      <label>Tên khoản tiền:</label>
      <input type="text" name="name" id="accountName" maxlength="30"
             value="<?= htmlspecialchars($account['name']) ?>"
             required class="form-control">

      <!-- Số dư hiện tại -->
      <label>Số dư hiện tại:</label>
      <input type="text" readonly
             value="<?= number_format($account['balance'], 0, ',', '.') ?> VND"
             class="form-control">

      <button type="submit" class="form-control">💾 Lưu thay đổi</button>
    </form>

    <form method="post" id="deleteForm" onsubmit="return confirm('Bạn có chắc chắn muốn xóa khoản tiền này không?');">
      <input type="hidden" name="delete_account" value="yes">
    
        <script>
          function togglePassword() {
            const input = document.getElementById("confirmPassword");
            input.type = input.type === "password" ? "text" : "password";
          }
        </script>
    
      <button type="submit" class="form-control danger">🗑️ Xóa khoản tiền</button>
    </form>


    <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
  </div>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("balanceForm");
        const submitBtn = document.querySelector('button[type="submit"]');
    
        // ✅ Xử lý nút submit
        form.addEventListener("submit", function() {
          submitBtn.disabled = true;
          submitBtn.textContent = "⏳ Đang xử lý...";
        });
      });
    </script>
    <div id="passwordModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
         background:rgba(0,0,0,0.5); z-index:999; justify-content:center; align-items:center;">
      <div style="background:#fff; padding:24px; border-radius:8px; max-width:400px; width:90%;">
        <h3>🔐 Xác nhận mật khẩu</h3>
        <p>Vui lòng nhập mật khẩu để tiếp tục:</p>
        <input type="password" id="modalPassword" class="form-control" required>
        <div style="margin-top:12px; display:flex; gap:12px;">
          <button onclick="submitAction()" class="form-control">✅ Xác nhận</button>
          <button onclick="closeModal()" class="form-control danger">❌ Hủy</button>
        </div>
      </div>
    </div>
    <script>
      let actionType = "";
    
      function openModal(type) {
        actionType = type;
        document.getElementById("passwordModal").style.display = "flex";
        document.getElementById("modalPassword").value = "";
        document.getElementById("modalPassword").focus();
      }
    
      function closeModal() {
          document.getElementById("passwordModal").style.display = "none";
        
          // ✅ Reset lại nút submit nếu đang xử lý
          const submitBtn = document.querySelector('#balanceForm button[type="submit"]');
          if (submitBtn.disabled) {
            submitBtn.disabled = false;
            submitBtn.textContent = "💾 Lưu thay đổi";
          }
        }
    
      function submitAction() {
        const password = document.getElementById("modalPassword").value;
        if (!password) return alert("Vui lòng nhập mật khẩu.");
    
        const form = (actionType === "save")
          ? document.getElementById("balanceForm")
          : document.getElementById("deleteForm");
    
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "confirm_password";
        input.value = password;
        form.appendChild(input);
    
        form.submit();
      }
    
      document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("balanceForm").addEventListener("submit", function(e) {
          e.preventDefault();
          openModal("save");
        });
    
        document.getElementById("deleteForm").addEventListener("submit", function(e) {
          e.preventDefault();
          openModal("delete");
        });
      });
    </script>
</body>
</html>
