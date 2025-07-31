<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// Xử lý form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST['name']);
    $rawBal   = $_POST['balance'] ?? '0';

    // 1. Sanitize số tiền: bỏ hết dấu phẩy và ký tự lạ
    $sanitized = preg_replace('/[^\d\.]/', '', $rawBal);
    if ($sanitized === '' || !is_numeric($sanitized)) {
        $error = "Số dư không hợp lệ. Vui lòng nhập số.";
    } else {
        $balance = floatval($sanitized);

        if ($balance < 0) {
            $error = "Số dư không được âm.";
        } elseif (empty($name)) {
            $error = "Vui lòng nhập tên tài khoản.";
        } else {
            // 2. Tạo tài khoản
            $insert = pg_query_params($conn,
                "INSERT INTO accounts (user_id, name, balance) VALUES ($1, $2, $3) RETURNING id",
                [$user_id, $name, $balance]
            );

            if ($insert && pg_num_rows($insert) === 1) {
                $row = pg_fetch_assoc($insert);
                $account_id = $row['id'];

                // 3. Ghi vào lịch sử giao dịch
                $description = "Tạo tài khoản mới: {$name}";
                $now = date('Y-m-d H:i:s');
                pg_query_params($conn,
                    "INSERT INTO transactions
                     (user_id, account_id, type, amount, description, date)
                     VALUES ($1, $2, 2, $3, $4, $5)",
                    [$user_id, $account_id, $balance, $description, $now]
                );

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Không thể tạo tài khoản. Vui lòng thử lại.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>➕ Tạo tài khoản mới</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 480px;
            margin: 60px auto;
            background: #fff;
            border-radius: 14px;
            padding: 30px 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        h2::before { content: "➕ "; }
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-add {
            background-color: #007bff;
            color: white;
        }
        .btn-add:hover {
            background-color: #0056b3;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .error {
            color: red;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Tạo tài khoản mới</h2>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" id="createAccountForm">
            <div class="form-group">
                <label for="name">Tên khoản tiền:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="balance">Số dư ban đầu:</label>
                <!-- Đổi về text để chèn dấu phẩy bằng JS -->
                <input
                    type="text"
                    id="balance"
                    name="balance"
                    inputmode="decimal"
                    placeholder="0"
                    value="<?= isset($_POST['balance']) ? htmlspecialchars($_POST['balance']) : '0' ?>"
                    required
                >
            </div>

            <button type="submit" class="btn-add">💾 Tạo tài khoản</button>
        </form>

        <a class="back-link" href="dashboard.php">← Quay lại Dashboard</a>
    </div>

    <!-- JS tự động thêm dấu phẩy -->
    <script>
    function formatWithCommas(value) {
        const parts = value.split('.');
        parts[0] = parts[0]
            .replace(/^0+(?=\d)|\D/g, '')           // bỏ số 0 dư và ký tự lạ
            .replace(/\B(?=(\d{3})+(?!\d))/g, ','); // chèn dấu phẩy
        return parts.join('.');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const inp = document.getElementById('balance');
        inp.addEventListener('input', () => {
            const pos    = inp.selectionStart;
            let raw      = inp.value.replace(/,/g, '');
            if (raw === '' || raw === '.') {
                inp.value = raw;
                return;
            }
            const [intP, decP] = raw.split('.');
            let formatted = formatWithCommas(intP);
            if (decP !== undefined) {
                formatted += '.' + decP.replace(/\D/g, '');
            }
            inp.value = formatted;
            // Giữ vị trí con trỏ
            const newPos = pos + (formatted.length - raw.length);
            inp.setSelectionRange(newPos, newPos);
        });
    });
    </script>
</body>
</html>
