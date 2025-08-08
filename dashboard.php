<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('bcadd')) {
    function bcadd($left_operand, $right_operand, $scale = 2) {
        // Fallback dùng toán học thường (không hoàn toàn chính xác với số lớn)
        return number_format($left_operand + $right_operand, $scale, '.', '');
    }
}

// 1. Chuyển admin nếu user_id = 1
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    header("Location: admin_feedback.php");
    exit();
}

// 2. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$filter_account     = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
$filter_type        = isset($_GET['type'])       ? $_GET['type']           : 'all';
$filter_description = isset($_GET['description'])? trim($_GET['description']) : '';
$from_date          = $_GET['from_date'] ?? '';
$to_date            = $_GET['to_date']   ?? '';

// 3. Lấy thông tin user
$user_result = pg_query_params(
    $conn,
    "SELECT username, fullname AS full_name, avatar, role 
     FROM users 
     WHERE id = $1",
    [$user_id]
);
$user = pg_fetch_assoc($user_result);
$avatarPath = 'uploads/' . (!empty($user['avatar']) ? $user['avatar'] : 'default-avatar.png');

// 4. Lấy danh sách tài khoản và tính tổng số dư
$accounts = [];
$totalAccountBalance = 0.0;

$account_q = pg_query_params(
    $conn,
    "SELECT id, name, balance 
     FROM accounts 
     WHERE user_id = $1",
    [$user_id]
);
while ($acc = pg_fetch_assoc($account_q)) {
    $accounts[] = $acc;
    // Ép float để cộng đúng
    $totalAccountBalance = bcadd($totalAccountBalance, $acc['balance'], 2);
}

// 5. Lấy danh sách giao dịch theo filter
$sql = "
    SELECT t.*, COALESCE(a.name,'[Không xác định]') AS account_name
    FROM transactions t
    LEFT JOIN accounts a ON t.account_id = a.id
    WHERE t.user_id = $1
";
$params = [$user_id];
$idx    = 2;

if ($filter_account > 0) {
    $sql    .= " AND t.account_id = \${$idx}";
    $params[] = $filter_account;
    $idx++;
}
if ($filter_type !== 'all') {
    $sql    .= " AND t.type = \${$idx}";
    $params[] = intval($filter_type);
    $idx++;
}
if ($filter_description !== '') {
    $sql    .= " AND t.description ILIKE \${$idx}";
    $params[] = "%{$filter_description}%";
    $idx++;
}
if ($from_date) {
    $sql    .= " AND DATE(t.date) >= \${$idx}";
    $params[] = $from_date;
    $idx++;
}
if ($to_date) {
    $sql    .= " AND DATE(t.date) <= \${$idx}";
    $params[] = $to_date;
    $idx++;
}

$sql .= " ORDER BY t.date DESC";
$resTrans = pg_query_params($conn, $sql, $params);

$transactions = [];
while ($row = pg_fetch_assoc($resTrans)) {
    $transactions[] = $row;
}

// 6. Tính tổng thu/chi
$totalThuAll = 0;
$totalChiAll = 0;
foreach ($transactions as $t) {
    if ($t['type'] == 0) $totalThuAll = bcadd($totalThuAll, $t['amount'], 2);
    if ($t['type'] == 1) $totalChiAll = bcadd($totalChiAll, $t['amount'], 2);
}

// 7. Nhóm giao dịch theo ngày
$grouped = [];
foreach ($transactions as $t) {
    $dateKey = date('d/m/Y', strtotime($t['date']));
    $grouped[$dateKey][] = $t;
}

// Nhãn cho type
$typeLabels = [
    0 => 'Thu',
    1 => 'Chi',
    2 => 'Cập nhật tài khoản'
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard</title>

  <style>
    /* 1. Biến toàn cục */
    :root {
      --sidebar-width: 280px;
      --color-primary: #1e88e5;
      --color-secondary: #66bb6a;
      --color-danger: #e53935;
      --color-bg: #f9fafb;
      --color-card: #ffffff;
      --color-text: #2e3d49;
      --color-muted: #64748b;
      --border-radius: 8px;
      --spacing: 16px;
      --transition-speed: 0.3s;
    }

    /* 2. Reset + Global */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0; padding: 0;
    }
    body {
      padding-top: 60px;
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: var(--color-bg);
      color: var(--color-text);
      line-height: 1.6;
    }
    a { text-decoration: none; color: inherit; }
    .brand {
      font-size: 1.75rem;      
      font-weight: 700;        
      letter-spacing: 1px;     
      text-transform: uppercase;  
      color: #fff;         
    }
    /* 3. Layout chính */
    .dashboard-wrapper {
      display: grid;
      grid-template-columns: var(--sidebar-width) 1fr;
      width: 100%;         
      max-width: none;    
      margin: 0;       
      gap: var(--spacing);
      padding: var(--spacing);
      border-radius: var(--border-radius);
    }

    .dashboard-wrapper .sidebar {
      position: sticky;
      top: calc(60px + var(--spacing));
      height: calc(100vh - 60px - 2 * var(--spacing));
      overflow-y: auto;
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      transition: transform var(--transition-speed);
    }

    .dashboard-wrapper .content {
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      min-height: calc(100vh - 60px - 2 * var(--spacing));
      overflow-y: auto;
    }

    /* 4. Header */
    .header {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 60px;
      background: var(--color-primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 var(--spacing);
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      z-index: 1001;
    }
    #sidebar-toggle { /* giữ nguyên */ }
    .brand { /* giữ nguyên */ }
    .profile-link { /* giữ nguyên */ }

    /* 5. Module (Filter panel, Table, Sidebar items…) */
    .sidebar h3 {
      font-size: 0.9rem;
      color: var(--color-muted);
      margin-bottom: 8px;
    }
    .account-card {
      display: block;
      background: var(--color-bg);
      padding: 12px;
      border-radius: var(--border-radius);
      margin-bottom: 8px;
      border: 1px solid #e2e8f0;
      transition: background var(--transition-speed);
    }
    .account-card:hover {
      background: #ebf4ff;
    }
    .account-name {
      font-weight: 600;
      margin-bottom: 4px;
    }
    .account-balance {
      font-size: 0.85rem;
      color: var(--color-text);
    }
    .add-account {
      display: inline-block;
      margin: var(--spacing) 0;
      color: var(--color-primary);
      font-weight: 500;
    }
    .account-total {
      margin-top: auto;
      padding: 12px;
      background: #ecfdf3;
      border: 1px solid #bbf7d0;
      border-radius: var(--border-radius);
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--color-text);
    }
    .sidebar hr {
      margin: var(--spacing) 0;
      border: none;
      height: 1px;
      background: #e2e8f0;
    }
    
    
    /* ——— Module: filter form ——— */
    .filter-panel {
      display: grid;
      grid-template-columns: repeat(6, 1fr));
      gap: var(--spacing);
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      margin-bottom: var(--spacing);
    }
    .filter-panel .form-group {
      display: flex;
      flex-direction: column;
    }
    .filter-panel label {
      font-size: 0.85rem;
      color: var(--color-muted);
      margin-bottom: 6px;
    }
    .filter-panel input,
    .filter-panel select {
      padding: 8px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      font-size: 0.95rem;
    }
    .filter-summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-top: 12px;
      padding: 0 8px;
    }
    .stats-inline {
      ddisplay: flex;
      gap: 24px;
      font-size: 0.95rem;
      color: var(--color-text);
      align-items: center;
    }
    .stats-inline span {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .filter-buttons {
      display: flex;
      gap: 12px;
    }
      .filter-buttons button,
    .filter-buttons .reset {
      padding: 10px 16px;
      border-radius: var(--border-radius);
      font-size: 0.95rem;
      cursor: pointer;
    }
    .filter-buttons button {
      background: var(--color-primary);
      color: #fff;
      border: none;
      padding: 10px 16px;
      border-radius: var(--border-radius);
      cursor: pointer;
      transition: background var(--transition-speed);
    }
    .filter-buttons button:hover {
      background: #1565c0;
    }
    .filter-buttons .reset {
      background: #f1f5f9;
      color: var(--color-text);
      padding: 10px 16px;
      border: 1px solid #cbd5e1;
      border-radius: var(--border-radius);
    }
    
    
    /* ——— Module: bảng giao dịch ——— */
    .table-wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--color-card);
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    th, td {
      padding: 12px 8px;
      text-align: left;
    }
    th {
      background: #f1f5f9;
      font-weight: 600;
    }
    tr:nth-child(even) {
      background: #f8fafc;
    }
    tr:hover {
      background: #eef2f7;
    }
    .amount-income {
      color: var(--color-secondary);
      font-weight: 600;
    }
    .amount-expense {
      color: var(--color-danger);
      font-weight: 600;
    }
    
    
    /* ——— Module: nhóm ngày ——— */
    .date-group {
      margin-top: 24px;
    }
    .date-heading {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #e2e8f0;
      padding: 8px 12px;
      border-left: 4px solid var(--color-primary);
      font-weight: 600;
      border-radius: var(--border-radius) 0 0 var(--border-radius);
    }
    .filter-panel {
      margin-bottom: 20px;
    }
    
    .filter-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    
    .filters {
      display: grid;
      grid-template-columns: repeat(5, 1fr); /* 5 cột đều nhau */
      gap: 16px;
      width: 100%;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .stats-inline {
      display: flex;
      flex-direction: column;
      gap: 5px;
      min-width: 180px;
      justify-content: center;
    }
    
    .filter-buttons {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    /* 6. Responsive */
    @media (max-width: 992px) {
      .dashboard-wrapper .sidebar {
        transform: translateX(-100%);
      }
      .dashboard-wrapper .sidebar.open {
        transform: translateX(0);
      }
        .main.full {
        margin-left: calc(-1 * var(--sidebar-width));
        width: calc(100% + var(--sidebar-width));
      }
    }

    @media (max-width: 800px) {
      .dashboard-wrapper {
        display: block;
        padding: 0;
      }
      .dashboard-wrapper .sidebar {
        position: relative;
        top: 0;
        height: auto;
        margin-bottom: var(--spacing);
        transform: translateX(0);
      }
    }
    @media (max-width: 768px) {
      .filter-summary-row {
        flex-direction: column;
        align-items: flex-start;
      }
      .filter-buttons {
        margin-top: 8px;
      }
        .stats-inline {
        flex-direction: column;
        gap: 4px;
      }
    }
    @media (max-width: 600px) {
      .filter-panel {
        grid-template-columns: 1fr;
      }
      table th:nth-child(6),
      table td:nth-child(6) {
        display: none;
      }
      .stats-inline,
      .filter-buttons {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <div class="brand">Quản lý thu chi</div>
    <a href="profile.php" class="profile-link">
      <span>Xin chào, <?= htmlspecialchars($user['full_name']) ?></span>
      <img src="<?= $avatarPath ?>" alt="Avatar">
    </a>
  </div>

  <div class="dashboard-wrapper">  
      <!-- Sidebar -->
      <nav class="sidebar closed">
        <h3><a href="advanced_statistics.php">📊 Thống kê nâng cao</a></h3>
        <h3>Các khoản tiền</h3>
        <?php foreach ($accounts as $acc): ?>
          <a href="edit_account_balance.php?account_id=<?= $acc['id'] ?>" class="account-card">
            <div class="account-name"><?= htmlspecialchars($acc['name']) ?></div>
            <div class="account-balance">
              Số dư: <?= number_format($acc['balance'] ?? 0, 0, ',', '.') ?> VND
            </div>
          </a>
        <?php endforeach; ?>
        <a href="create_account.php" class="add-account">+ Thêm khoản tiền</a>
        <div class="account-total">
          <strong>Tổng số dư:</strong>
          <?= number_format($totalAccountBalance, 0, ',', '.') ?> VND
        </div>
        <hr>
        <a href="feedback.php">📩 Gửi phản hồi</a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="admin_feedback.php">📬 Xem phản hồi</a>
        <?php endif; ?>
      </nav>
    <div class="content">
      <!-- Main Content -->
      <main class="main">
        <div class="content-header">
          <h2>Lịch sử thu chi</h2>
        </div>
    
        <!-- Filter Form -->
        <form method="get" class="filter-panel">
          <div class="filter-row">
            <!-- Các bộ lọc -->
            <div class="filters">
              <div class="form-group">
                <label for="from_date">Từ ngày</label>
                <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
              </div>
              <div class="form-group">
                <label for="to_date">Đến ngày</label>
                <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
              </div>
              <div class="form-group">
                <label for="type">Loại</label>
                <select id="type" name="type">
                  <option value="all" <?= $filter_type === 'all'? 'selected':'' ?>>Tất cả</option>
                  <option value="0" <?= $filter_type === '0'? 'selected':'' ?>>Thu</option>
                  <option value="1" <?= $filter_type === '1'? 'selected':'' ?>>Chi</option>
                  <option value="2" <?= $filter_type === '2'? 'selected':'' ?>>Cập nhật</option>
                </select>
              </div>
              <div class="form-group">
                <label for="description">Mô tả</label>
                <select id="description" name="description">
                  <option value="">Tất cả</option>
                  <!-- PHP render mô tả -->
                </select>
              </div>
              <div class="form-group">
                <label for="account_id">Khoản tiền</label>
                <select id="account_id" name="account_id">
                  <option value="0" <?= $filter_account===0? 'selected':'' ?>>Tất cả</option>
                  <!-- PHP render tài khoản -->
                </select>
              </div>
            </div>
              
            <!-- Tổng thu/chi -->
            <div class="filter-summary-row">
              <div class="stats-inline">
                <span>🔼 Tổng thu: <strong><?= number_format($totalThuAll ?? 0,0,',','.') ?> VND</strong></span>
                <span>🔽 Tổng chi: <strong><?= number_format($totalChiAll ?? 0,0,',','.') ?> VND</strong></span>
              </div>
              <div class="filter-buttons">
                <button type="submit">🔍 Lọc</button>
                <a href="dashboard.php" class="reset">🧹 Làm mới</a>
              </div>
            </div>  
          </div>
        </form>

        <!-- Grouped Transactions -->
        <?php if (empty($grouped)): ?>
          <p>Không có giao dịch nào.</p>
        <?php else: ?>
          <?php foreach ($grouped as $date => $entries): ?>
            <?php
              $totalThu = 0; $totalChi = 0;
              foreach ($entries as $row) {
                if ($row['type']==0) $totalThu += $row['amount'];
                elseif ($row['type']==1) $totalChi += $row['amount'];
              }
            ?>
            <div class="date-group">
              <div class="date-heading">
                <span><?= $date ?></span>
                <span>
                  🔼 Tổng thu: <?= number_format($totalThu,0,',','.') ?> VND
                  &nbsp;&nbsp;
                  🔽 Tổng chi: <?= number_format($totalChi,0,',','.') ?> VND
                </span>
              </div>
            </div>
            <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Giờ</th>
                    <th>Loại</th>
                    <th>Số tiền</th>
                    <th>Mô tả</th>
                    <th>Số dư còn lại</th>
                    <th>Khoản tiền</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($entries as $row): ?>
                    <?php if (!is_array($row)) continue; ?>
                    <?php
                      // fix mô tả khởi tạo
                      $d = $row['description'];
                      if (strpos($d, 'Tạo tài khoản mới:')===0) {
                        $d = 'Tạo khoản tiền mới';
                      }
                    ?>
                    <tr>
                      <td><?= date('H:i:s', strtotime($row['date'])) ?></td>
                      <td><?= $typeLabels[$row['type']] ?? '-' ?></td>
                      <td class="<?= $row['type']==0? 'amount-income':
                                    ($row['type']==1? 'amount-expense':'') ?>">
                        <?= $row['type']==2? '0': number_format($row['amount']??0,0,',','.') ?>
                        VND
                      </td>
                      <td><?= htmlspecialchars($d ?: '-') ?></td>
                      <td><?= number_format($row['remaining_balance']??0,0,',','.') ?> VND</td>
                      <td><?= htmlspecialchars($row['account_name']) ?></td>
                      <td>
                        <a href="edit_transaction.php?id=<?= $row['id'] ?>">✏️ Sửa</a>
                        |
                        <a href="delete_transaction.php?id=<?= $row['id'] ?>"
                           onclick="return confirm('Bạn có chắc muốn xoá giao dịch này?')">
                          🗑️ Xoá
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </main>
    </div>
  </div>
  <script>
    const btn = document.getElementById('sidebar-toggle'),
          sb  = document.querySelector('.sidebar'),
          mn  = document.querySelector('.main');
      btn.addEventListener('click', () => {
      sb.classList.toggle('open');
      sb.classList.toggle('closed');
      mn.classList.toggle('full');
    });
  </script>
</body>
</html>
