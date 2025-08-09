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

    $account_id = intval($_POST['account_id']);
    $time = $_POST['time'];
    $datetime = $date . ' ' . $time;

    $query = "UPDATE transactions 
              SET type = $1, amount = $2, description = $3, date = $4, account_id = $5 
              WHERE id = $6 AND user_id = $7";
    $result = pg_query_params($conn, $query, array($type, $amount, $description, $datetime, $account_id, $id, $user_id));
   
    $_SESSION['message'] = "✅ Giao dịch đã được cập nhật thành công.";
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
    <title>Sửa giao dịch</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .form-container {
            background: #fff;
            padding: 35px 40px;
            border-radius: 12px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #34495e;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 15px;
            background-color: #fdfdfd;
        }

        textarea {
            resize: vertical;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        button,
        a.button-link {
            padding: 10px 20px;
            font-size: 15px;
            font-weight: bold;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        button {
            background-color: #27ae60;
            color: #fff;
        }

        button:hover {
            background-color: #219150;
        }

        a.button-link {
            background-color: #95a5a6;
            color: #fff;
        }

        a.button-link:hover {
            background-color: #7f8c8d;
        }

        .note {
            font-size: 13px;
            color: #888;
            margin-top: -15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>✏️ Sửa giao dịch</h2>
        <form method="post" action="edit_transaction.php?id=<?= htmlspecialchars($_GET['id']) ?>">
            <label for="account">Tên khoản tiền:</label>
            <input type="text" id="account" name="account" value="Tiền mặt" readonly>

            <label for="balance">Số dư hiện tại:</label>
            <input type="text" id="balance" name="balance" value="1.650.000 VND" readonly>

            <label for="type">Loại giao dịch:</label>
            <select id="type" name="type">
                <option value="1">Thu</option>
                <option value="2">Chi</option>
            </select>

            <label for="amount">Số tiền:</label>
            <input type="number" id="amount" name="amount" value="50000.00" required>
            <div class="note">Tối đa 98.349.999 VND</div>

            <label for="preset-description">Nội dung giao dịch:</label>
            <select id="preset-description" onchange="updateDescription()">
                <option value="">-- Chọn nội dung --</option>
            </select>
            <textarea id="description" name="description" rows="2" placeholder="Nhập hoặc chọn nội dung">Hiện tại</textarea>

            <label for="date">Ngày:</label>
            <input type="text" id="date" name="date" value="09/08/2025">

            <label for="time">Giờ:</label>
            <input type="text" id="time" name="time" value="04:33 PM">

            <div class="button-group">
                <button type="submit">💾 Lưu thay đổi</button>
                <a class="button-link" href="dashboard.php">← Quay lại Dashboard</a>
            </div>
        </form>
    </div>

    <script>
        const presetThu = ["Lương", "Thưởng", "Tiền lãi", "Bán hàng", "Khác"];
        const presetChi = ["Ăn uống", "Di chuyển", "Giải trí", "Mua sắm", "Khác"];

        function updatePresetOptions() {
            const type = document.getElementById("type").value;
            const presetSelect = document.getElementById("preset-description");
            const options = type === "1" ? presetThu : presetChi;
            presetSelect.innerHTML = `<option value="">-- Chọn nội dung --</option>` +
                options.map(item => `<option value="${item}">${item}</option>`).join("");
        }

        function updateDescription() {
            const selected = document.getElementById("preset-description").value;
            if (selected) {
                document.getElementById("description").value = selected;
            }
        }

        document.getElementById("type").addEventListener("change", updatePresetOptions);
        document.addEventListener("DOMContentLoaded", updatePresetOptions);
    </script>
</body>
</html>
