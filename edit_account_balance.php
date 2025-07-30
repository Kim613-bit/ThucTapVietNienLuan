<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;

// 🔹 Lấy thông tin tài khoản
$sql = "SELECT * FROM accounts WHERE id = $1 AND user_id = $2";
$result = pg_query_params($conn, $sql, array($account_id, $user_id));
$account = pg_fetch_assoc($result);

if (!$account) {
    echo "Tài khoản không tồn tại.";
    exit();
}

$success = "";
$error = "";

// 🔹 Gợi ý mô tả
$descriptions = [];
$sql_desc = "SELECT DISTINCT description FROM transactions 
             WHERE user_id = $1 AND account_id = $2 AND type IN (0, 1) AND description <> '' 
             ORDER BY date DESC LIMIT 30";
$result_desc = pg_query_params($conn, $sql_desc, array($user_id, $account_id));
while ($row = pg_fetch_assoc($result_desc)) {
    $descriptions[] = $row['description'];
}

// 🔹 Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xóa tài khoản và toàn bộ giao dịch liên quan
    if (isset($_POST['delete_account']) && $_POST['delete_account'] === 'yes') {
        pg_query($conn, "BEGIN");
        try {
            pg_query_params($conn, "DELETE FROM transactions WHERE account_id = $1 AND user_id = $2", array($account_id, $user_id));
            pg_query_params($conn, "DELETE FROM accounts WHERE id = $1 AND user_id = $2", array($account_id, $user_id));
            pg_query($conn, "COMMIT");
            header("Location: dashboard.php?deleted=1");
            exit();
        } catch (Exception $e) {
            pg_query($conn, "ROLLBACK");
            $error = "❌ Không thể xóa tài khoản: " . $e->getMessage();
        }
    }

    $new_name = trim($_POST['name']);
    $type = $_POST['type'];
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $name_changed = $new_name !== $account['name'];

    pg_query($conn, "BEGIN");

    try {
        // Cập nhật tên tài khoản nếu thay đổi
        if ($name_changed) {
            pg_query_params($conn, "UPDATE accounts SET name = $1 WHERE id = $2 AND user_id = $3", array($new_name, $account_id, $user_id));
            $log_desc = "Đổi tên tài khoản từ '{$account['name']}' thành '{$new_name}'";
            $now = date("Y-m-d H:i:s");
            pg_query_params($conn, "INSERT INTO transactions (account_id, user_id, type, amount, description, remaining_balance, date) 
                                    VALUES ($1, $2, 2, 0, $3, $4, $5)", 
                            array($account_id, $user_id, $log_desc, $account['balance'], $now));
        }

        // Thêm giao dịch thu/chi
        if ($type === 'thu' || $type === 'chi') {
            $type_value = ($type === 'chi') ? 1 : 0;
            $new_balance = $type_value === 0 ? $account['balance'] + $amount : $account['balance'] - $amount;

            pg_query_params($conn, "UPDATE accounts SET balance = $1 WHERE id = $2 AND user_id = $3", array($new_balance, $account_id, $user_id));

            if (empty($description)) {
                $description = ($type_value == 0) ? 'Giao dịch thu không có nội dung' : 'Giao dịch chi không có nội dung';
            }

            $now = date("Y-m-d H:i:s");
            pg_query_params($conn, "INSERT INTO transactions (account_id, user_id, type, amount, description, remaining_balance, date)
                                    VALUES ($1, $2, $3, $4, $5, $6, $7)",
                            array($account_id, $user_id, $type_value, $amount, $description, $new_balance, $now));

            $account['balance'] = $new_balance;
        }

        pg_query($conn, "COMMIT");
        $success = "✅ Cập nhật thành công!";
        $account['name'] = $new_name;
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        $error = "❌ Lỗi: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sửa khoản tiền</title>
    <style>
        body { font-family: Arial; margin: 0; padding: 0; background: #f2f2f2; }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            margin-top: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
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
        .success { color: green; }
        .error { color: red; }
        .back {
            margin-top: 20px;
            display: block;
            text-align: center;
            color: #007BFF;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>✏️ Sửa khoản tiền</h2>

    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <form method="post" id="balanceForm" onsubmit="return confirm('Bạn có chắc chắn muốn lưu thay đổi không?');">
        <label>Tên khoản tiền:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($account['name']) ?>" required class="form-control">

        <label>Số dư hiện tại:</label>
        <input type="text" value="<?= number_format($account['balance'], 0, ',', '.') ?> VND" readonly class="form-control">

        <label>Loại giao dịch:</label>
        <select name="type" id="transactionType" onchange="toggleFields()" class="form-control">
            <option value="">-- Không thay đổi số dư --</option>
            <option value="thu">Thu</option>
            <option value="chi">Chi</option>
        </select>

        <div id="transactionFields" style="display: none;">
            <label>Số tiền:</label>
            <input type="number" name="amount" step="0.01" min="0" class="form-control">
            <label>Nội dung giao dịch:</label>
            <input list="suggestions" name="description" placeholder="Nhập hoặc chọn nội dung"
                value="<?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?>" class="form-control">
            <datalist id="suggestions">
                <?php foreach ($descriptions as $desc): ?>
                    <option value="<?= htmlspecialchars($desc) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <button type="submit" class="form-control">💾 Lưu thay đổi</button>
    </form>

    <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa khoản tiền này không?');">
        <input type="hidden" name="delete_account" value="yes">
        <button type="submit" class="form-control danger">🗑️ Xóa khoản tiền</button>
    </form>

    <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
</div>

<script>
function toggleFields() {
    const type = document.getElementById("transactionType").value;
    const fields = document.getElementById("transactionFields");
    fields.style.display = (type === "thu" || type === "chi") ? "block" : "none";
}
document.addEventListener("DOMContentLoaded", toggleFields);
</script>

</body>
</html>
