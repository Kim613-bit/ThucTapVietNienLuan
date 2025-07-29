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

// Lấy thông tin tài khoản
$sql = "SELECT * FROM accounts WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $account_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$account = mysqli_fetch_assoc($result);

if (!$account) {
    echo "Tài khoản không tồn tại.";
    exit();
}

$success = "";
$error = "";

// Gợi ý mô tả
$descriptions = [];
$sql_desc = "SELECT DISTINCT description FROM transactions 
             WHERE user_id = ? AND account_id = ? AND type IN (0, 1) AND description <> '' 
             ORDER BY date DESC LIMIT 30";
$stmt_desc = mysqli_prepare($conn, $sql_desc);
mysqli_stmt_bind_param($stmt_desc, "ii", $user_id, $account_id);
mysqli_stmt_execute($stmt_desc);
$result_desc = mysqli_stmt_get_result($stmt_desc);
while ($row = mysqli_fetch_assoc($result_desc)) {
    $descriptions[] = $row['description'];
}

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xóa tài khoản và toàn bộ giao dịch liên quan
    if (isset($_POST['delete_account']) && $_POST['delete_account'] === 'yes') {
        try {
            $conn->begin_transaction();

            $sql_del_trans = "DELETE FROM transactions WHERE account_id = ? AND user_id = ?";
            $stmt_del_trans = mysqli_prepare($conn, $sql_del_trans);
            mysqli_stmt_bind_param($stmt_del_trans, "ii", $account_id, $user_id);
            mysqli_stmt_execute($stmt_del_trans);

            $sql_del_acc = "DELETE FROM accounts WHERE id = ? AND user_id = ?";
            $stmt_del_acc = mysqli_prepare($conn, $sql_del_acc);
            mysqli_stmt_bind_param($stmt_del_acc, "ii", $account_id, $user_id);
            mysqli_stmt_execute($stmt_del_acc);

            $conn->commit();
            header("Location: dashboard.php?deleted=1");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ Không thể xóa tài khoản: " . $e->getMessage();
        }
    }

    $new_name = trim($_POST['name']);
    $type = $_POST['type'];
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $name_changed = $new_name !== $account['name'];

    $conn->begin_transaction();

    try {
        // Cập nhật tên tài khoản nếu có thay đổi
        if ($name_changed) {
            $sql_update_name = "UPDATE accounts SET name = ? WHERE id = ? AND user_id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update_name);
            mysqli_stmt_bind_param($stmt_update, "sii", $new_name, $account_id, $user_id);
            mysqli_stmt_execute($stmt_update);

            $log_desc = "Đổi tên tài khoản từ '{$account['name']}' thành '{$new_name}'";
            $now = date("Y-m-d H:i:s");
            $current_balance = $account['balance'];
            $sql_log = "INSERT INTO transactions (account_id, user_id, type, amount, description, remaining_balance, date)
                        VALUES (?, ?, 2, 0, ?, ?, ?)";
            $stmt_log = mysqli_prepare($conn, $sql_log);
            mysqli_stmt_bind_param($stmt_log, "iisds", $account_id, $user_id, $log_desc, $current_balance, $now);
            mysqli_stmt_execute($stmt_log);
        }

        // Nếu có thêm giao dịch thu/chi
        if ($type === 'thu' || $type === 'chi') {
            $type_value = ($type === 'chi') ? 1 : 0;
            $new_balance = $type_value === 0 ? $account['balance'] + $amount : $account['balance'] - $amount;

            // Cập nhật số dư mới
            $sql_update_balance = "UPDATE accounts SET balance = ? WHERE id = ? AND user_id = ?";
            $stmt_balance = mysqli_prepare($conn, $sql_update_balance);
            mysqli_stmt_bind_param($stmt_balance, "dii", $new_balance, $account_id, $user_id);
            mysqli_stmt_execute($stmt_balance);

            // Giao dịch thu/chi
            if (empty($description)) {
                $description = ($type_value == 0) ? 'Giao dịch thu không có nội dung' : 'Giao dịch chi không có nội dung';
            }

            $now = date("Y-m-d H:i:s");
            $sql_insert = "INSERT INTO transactions (account_id, user_id, type, amount, description, remaining_balance, date)
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "iiidsss", $account_id, $user_id, $type_value, $amount, $description, $new_balance, $now);
            mysqli_stmt_execute($stmt_insert);

            $account['balance'] = $new_balance;
        }

        $conn->commit();
        $success = "✅ Cập nhật thành công!";
        $account['name'] = $new_name;
    } catch (Exception $e) {
        $conn->rollback();
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
