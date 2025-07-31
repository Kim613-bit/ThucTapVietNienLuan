<?php
session_start();
include "db.php";
define('MAX_BALANCE', 100_000_000_000);  // 100 tỷ
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// Lấy account_id từ GET (nếu POST có gửi thì ghi đè ngay bên dưới)
$account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;

// 🔹 Lấy thông tin tài khoản (dành cho hiển thị và kiểm tra tồn tại)
$sql      = "SELECT * FROM accounts WHERE id = $1 AND user_id = $2";
$result   = pg_query_params($conn, $sql, [ $account_id, $user_id ]);
$account  = pg_fetch_assoc($result);
if (!$account) {
    echo "Tài khoản không tồn tại.";
    exit();
}

$success = "";
$error   = "";

// 🔹 Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nếu form XÓA gửi kèm account_id thì ghi đè
    if (!empty($_POST['account_id'])) {
        $account_id = intval($_POST['account_id']);
    }

    // 1. Xử lý xóa tài khoản
    if (isset($_POST['delete_account']) && $_POST['delete_account'] === 'yes') {
        try {
            pg_query($conn, 'BEGIN');

            // Xóa mọi giao dịch liên quan
            pg_query_params(
                $conn,
                'DELETE FROM transactions WHERE account_id = $1 AND user_id = $2',
                [ $account_id, $user_id ]
            );

            // Xóa bản ghi account
            $res = pg_query_params(
                $conn,
                'DELETE FROM accounts WHERE id = $1 AND user_id = $2',
                [ $account_id, $user_id ]
            );

            // Nếu không xóa được (user_id sai hoặc account không tồn tại)
            if (pg_affected_rows($res) === 0) {
                throw new Exception('Không tìm thấy tài khoản hoặc không có quyền xóa.');
            }

            pg_query($conn, 'COMMIT');
            header('Location: dashboard.php');
            exit();
        } catch (Exception $e) {
            pg_query($conn, 'ROLLBACK');
            $error = '❌ Xóa không thành công: ' . $e->getMessage();
        }
    }
    // 2. Nếu không phải XÓA thì chạy phần cập nhật như trước
    else {
        // ... phần xử lý cập nhật tên / thu chi giữ nguyên ...
    }
}

// 🔹 Gợi ý mô tả (vẫn như cũ) …
$descriptions = [];
$sql_desc = "SELECT description FROM transactions 
             WHERE user_id = $1 AND account_id = $2 AND type IN (0, 1) AND description <> '' 
             GROUP BY description 
             ORDER BY MAX(date) DESC 
             LIMIT 30";
$result_desc = pg_query_params($conn, $sql_desc, [ $user_id, $account_id ]);
while ($row = pg_fetch_assoc($result_desc)) {
    $descriptions[] = $row['description'];
}
?>
<!DOCTYPE html>
<html>
<head>…</head>
<body>
<div class="container">
    <h2>✏️ Sửa khoản tiền</h2>

    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if ($error):   ?><p class="error"><?= $error   ?></p><?php endif; ?>

    <!-- Form cập nhật -->
    <form method="post" id="balanceForm" …>
        <!-- … các input name, type, và JS format như bạn đã có … -->
    </form>

    <!-- Form xóa -->
    <form method="post"
          onsubmit="return confirm('Bạn có chắc chắn muốn xóa khoản tiền này không?');">
        <input type="hidden" name="delete_account" value="yes">
        <input type="hidden" name="account_id"     value="<?= $account_id ?>">
        <button type="submit" class="form-control danger">
            🗑️ Xóa khoản tiền
        </button>
    </form>

    <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
</div>

<script>…JS show/hide & format number…</script>
</body>
</html>
