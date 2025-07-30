<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');


// Chuyển admin nếu là user_id = 1
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    header("Location: admin_feedback.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$filter_account = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_description = isset($_GET['description']) ? trim($_GET['description']) : '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Lấy thông tin người dùng
$user_result = pg_query_params($conn,
    "SELECT username, fullname AS full_name, avatar, role FROM users WHERE id = $1",
    [$user_id]
);
$user = pg_fetch_assoc($user_result);

// Lấy danh sách tài khoản
$account_result = pg_query_params($conn,
    "SELECT id, name, balance FROM accounts WHERE user_id = $1",
    [$user_id]
);
$accounts = [];
while ($row = pg_fetch_assoc($account_result)) {
    $accounts[] = $row;
}

// Lấy danh sách giao dịch theo điều kiện lọc
$sql2 = "
    SELECT t.*, COALESCE(a.name, '[Không xác định]') AS account_name
    FROM transactions t
    LEFT JOIN accounts a ON t.account_id = a.id
    WHERE t.user_id = $1";
$params = [$user_id];
$i = 2;

if ($filter_account > 0) {
    $sql2 .= " AND t.account_id = $" . $i;
    $params[] = $filter_account;
    $i++;
}
if ($filter_type !== 'all') {
    $sql2 .= " AND t.type = $" . $i;
    $params[] = intval($filter_type);
    $i++;
}
if (!empty($filter_description)) {
    $sql2 .= " AND t.description ILIKE $" . $i;
    $params[] = '%' . $filter_description . '%';
    $i++;
}
if (!empty($from_date)) {
    $sql2 .= " AND DATE(t.date) >= $" . $i;
    $params[] = $from_date;
    $i++;
}
if (!empty($to_date)) {
    $sql2 .= " AND DATE(t.date) <= $" . $i;
    $params[] = $to_date;
    $i++;
}

$sql2 .= " ORDER BY t.date DESC";
$trans_result = pg_query_params($conn, $sql2, $params);
$transactions = [];
while ($row = pg_fetch_assoc($trans_result)) {
    $transactions[] = $row;
}

// Tổng thu chi
$totalThuAll = 0;
$totalChiAll = 0;
foreach ($transactions as $row) {
    if ($row['type'] == 0) $totalThuAll += $row['amount'];
    elseif ($row['type'] == 1) $totalChiAll += $row['amount'];
}

// Nhóm giao dịch theo ngày
$grouped = [];
foreach ($transactions as $row) {
    $dateKey = date('d/m/Y', strtotime($row['date']));
    $grouped[$dateKey][] = $row;
}

$typeLabels = [0 => 'Thu', 1 => 'Chi', 2 => 'Cập nhật tài khoản'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial;
            margin: 0;
            padding: 0;
        }

        .header {
            background: #007BFF;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .user a {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
        }

        .header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
            object-fit: cover;
        }

        .main {
            display: flex;
            flex-direction: row;
            height: calc(100vh - 60px); /* Trừ phần header */
            overflow: hidden;
        }

        .sidebar {
            width: 280px;
            background: #f0f0f0;
            padding: 20px;
            overflow-y: auto;
            border-right: 1px solid #ccc;
        }

        .content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .account {
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ccc;
        }

        h3.date-heading {
            margin-top: 30px;
            background: #e2e2e2;
            padding: 10px;
            border-left: 4px solid #007BFF;
        }

        .heading-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #eee;
        }

        .filter-form select,
        .filter-form input,
        .filter-form button {
            padding: 8px;
            font-size: 14px;
            margin: 5px 10px 5px 0;
        }

        .amount-income {
            color: green;
            font-weight: bold;
        }

        .amount-expense {
            color: red;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .main {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
            }

            .content {
                height: auto;
            }
        }
    </style>

</head>
<body>
<div class="header">
    <h2>Quản lý thu chi</h2>
    <div class="user">
        <a href="profile.php">
            <span>Xin chào, <?= htmlspecialchars($user['full_name']) ?></span>
            <img src="<?= !empty($user['avatar']) ? 'uploads/' . htmlspecialchars($user['avatar']) : 'default-avatar.png' ?>">
        </a>
    </div>
</div>
<div class="main">
    <div class="sidebar">
        <h3><a href="advanced_statistics.php">📊 Thống kê nâng cao</a></h3>
        <h3>Các khoản tiền</h3>
        <?php foreach ($accounts as $acc): ?>
            <div class="account">
                <strong><?= htmlspecialchars($acc['name']) ?></strong><br>
                Số dư: <?= number_format($acc['balance'] ?? 0, 0, ',', '.') ?> VND
                <a href="edit_account_balance.php?account_id=<?= $acc['id'] ?>"><br>Chỉnh sửa</a>
            </div>
        <?php endforeach; ?>
        <a href="create_account.php">+ Thêm khoản tiền</a>
        <hr>
        <a href="feedback.php">📩 Gửi phản hồi</a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="admin_feedback.php">📬 Xem phản hồi</a>
        <?php endif; ?>
    </div>
    <div class="content">
        <h2>Lịch sử thu chi</h2>
        <form method="get" class="filter-form">
            Từ ngày: <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
            Đến ngày: <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
            
            🔼 <span style="color:green;">Tổng thu: <?= number_format($totalThuAll ?? 0, 0, ',', '.') ?> VND</span>
            🔽 <span style="color:red;">Tổng chi: <?= number_format($totalChiAll ?? 0, 0, ',', '.') ?> VND</span>

            <br><br>
            Loại: 
            <select name="type">
                <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>Tất cả</option>
                <option value="0" <?= $filter_type === '0' ? 'selected' : '' ?>>Thu</option>
                <option value="1" <?= $filter_type === '1' ? 'selected' : '' ?>>Chi</option>
                <option value="2" <?= $filter_type === '2' ? 'selected' : '' ?>>Cập nhật</option>
            </select>

            Mô tả:
            <select name="description">
                <option value="">Tất cả</option>
                <?php
                $sql_desc = "SELECT description FROM transactions 
                             WHERE user_id = $1 AND description IS NOT NULL AND description != '' 
                             GROUP BY description 
                             ORDER BY MAX(date) DESC LIMIT 30";
                $result_desc = pg_query_params($conn, $sql_desc, array($user_id));
                while ($desc = pg_fetch_assoc($result_desc)) {
                    echo '<option value="' . htmlspecialchars($desc['description']) . '"' .
                         ($desc['description'] === $filter_description ? ' selected' : '') .
                         '>' . htmlspecialchars($desc['description']) . '</option>';
                }
                ?>
            </select>

            Khoản tiền:
            <select name="account_id">
                <option value="0" <?= $filter_account === 0 ? 'selected' : '' ?>>Tất cả</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= $filter_account === $acc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($acc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Lọc</button>
            <a href="dashboard.php" style="margin-left:10px;">🧹 Làm mới</a>
        </form>

        <?php if (empty($grouped)): ?>
            <p>Không có giao dịch nào.</p>
        <?php else: ?>
            <?php foreach ($grouped as $date => $entries): ?>
                <?php
                $totalThu = 0;
                $totalChi = 0;
                foreach ($entries as $row) {
                    if ($row['type'] == 0) $totalThu += $row['amount'];
                    elseif ($row['type'] == 1) $totalChi += $row['amount'];
                }
                ?>
                <h3 class="date-heading">
                    <div class="heading-flex">
                        <span><?= $date ?></span>
                        <span>
                            🔼 <span style="color:green;">Tổng thu: <?= number_format($totalThu, 0, ',', '.') ?> VND</span>
                            🔽 <span style="color:red;">Tổng chi: <?= number_format($totalChi, 0, ',', '.') ?> VND</span>
                        </span>
                    </div>
                </h3>
                <table>
                    <tr>
                        <th>Giờ</th><th>Loại</th><th>Số tiền</th><th>Mô tả</th><th>Số dư còn lại</th><th>Khoản tiền</th>
                    </tr>
                    <?php foreach ($entries as $row): ?>
                        <?php if (!is_array($row)) continue; ?>
                        <tr>
                            <td><?= date('H:i:s', strtotime($row['date'])) ?></td>
                            <td><?= $typeLabels[$row['type']] ?? 'Không xác định' ?></td>
                            <td class="<?= $row['type'] == 0 ? 'amount-income' : ($row['type'] == 1 ? 'amount-expense' : '') ?>">
                                <?= (isset($row['type']) && $row['type'] == 2 ? '0' : number_format($row['amount'] ?? 0, 0, ',', '.')) ?> VND
                            </td>
                            <td><?= !empty($row['description']) ? htmlspecialchars($row['description']) : '-' ?></td>
                            <td><?= number_format($row['remaining_balance'] ?? 0, 0, ',', '.') ?> VND</td>
                            <td><?= htmlspecialchars($row['account_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
