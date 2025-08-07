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
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <style>
    :root {
      --primary: #007bff;
      --muted-bg: #f5f7fa;
      --card-bg: #fff;
      --text-dark: #2c3e50;
      --text-light: #555;
      --success: #16a34a;
      --danger: #dc2626;
      --border: #e1e5ea;
      --radius: 6px;
    }

    * { box-sizing: border-box; margin:0; padding:0; }
    body { font-family: 'Segoe UI', sans-serif; background: var(--muted-bg); color: var(--text-dark); }

    /* HEADER */
    .header {
      background: var(--primary);
      color: white;
      padding: 1rem 2rem;
      display: flex; justify-content: space-between; align-items: center;
    }
    .header h2 { font-size: 1.5rem; }
    .header .user {
      display: flex; align-items: center; gap: .75rem;
    }
    .header .user span { font-weight: 500; }
    .header .avatar-header {
      width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
      border: 2px solid white;
    }

    /* LAYOUT */
    .main {
      display: grid;
      grid-template-columns: 280px 1fr;
      height: calc(100vh - 64px);
    }

    /* SIDEBAR */
    .sidebar {
      background: var(--card-bg);
      border-right: 1px solid var(--border);
      padding: 1.5rem;
      overflow-y: auto;
      display: flex; flex-direction: column; gap: 1.5rem;
    }
    .sidebar h3 { font-size: 1.1rem; margin-bottom: .5rem; color: var(--text-light); }
    .sidebar a.account-card {
      display: block; background: var(--muted-bg); padding: .75rem; border-radius: var(--radius);
      text-decoration: none; color: var(--text-dark); border:1px solid var(--border);
      margin-bottom: .5rem;
      transition: background .2s;
    }
    .sidebar a.account-card:hover { background: #e9eef5; }
    .sidebar .account-card strong { display:block; margin-bottom:.25rem; }
    .sidebar .account-total {
      margin-top: auto; padding: .75rem; background: #e9f7ef; border-radius: var(--radius);
      border:1px solid #a3d7b9; font-weight: 600;
    }
    .sidebar a.add-account {
      display:inline-block; margin-top:.5rem; color: var(--primary); text-decoration:none;
      font-weight:500;
    }

    /* CONTENT */
    .content {
      padding: 1.5rem; overflow-y: auto;
      display: flex; flex-direction: column; gap:1.5rem;
    }
    .content h2 { font-size: 1.25rem; margin-bottom: .75rem; }

    /* FILTER PANEL */
    .filter-panel {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem;
      display: grid;
      grid-template-columns: repeat(auto-fit,minmax(160px,1fr)) auto;
      gap: .75rem 1.5rem;
      align-items: center;
    }
    .filter-panel label {
      font-size: .875rem; color: var(--text-light);
      display: block; margin-bottom: .25rem;
    }
    .filter-panel input,
    .filter-panel select {
      width: 100%; padding: .5rem; border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: .9rem;
    }
    .filter-panel .stats {
      grid-column: 1 / -1;
      display:flex; justify-content:flex-end; gap:1rem;
      font-size:.95rem;
    }
    .filter-panel .stats .income { color: var(--success); font-weight:600; }
    .filter-panel .stats .expense { color: var(--danger); font-weight:600; }
    .filter-panel button,
    .filter-panel a.reset {
      padding: .5rem 1rem; border:none; border-radius: var(--radius);
      cursor:pointer; font-size:.9rem; font-weight:500;
    }
    .filter-panel button {
      background: var(--primary); color:white;
      transition: background .2s;
    }
    .filter-panel button:hover { background:#0056b3; }
    .filter-panel a.reset {
      background: var(--muted-bg); color: var(--text-light); text-decoration:none;
      border:1px solid var(--border);
    }

    /* TRANSACTIONS GROUP */
    .group {
      display: flex; flex-direction: column; gap:1rem;
    }
    .group .date-block {
      background: #e2e2e2; padding:.5rem 1rem; border-left:4px solid var(--primary);
      display:flex; justify-content:space-between; align-items:center;
      font-weight:600; 
    }

    /* TABLE */
    table.transactions {
      width:100%; border-collapse: collapse; background:var(--card-bg);
      border:1px solid var(--border); border-radius: var(--radius); overflow:hidden;
    }
    table.transactions th, table.transactions td {
      padding:.75rem 1rem; border-bottom:1px solid var(--border);
      text-align:left; font-size:.9rem;
    }
    table.transactions th {
      background: #f0f2f5; font-weight:600;
    }
    table.transactions tr:nth-child(even) { background: #fafbfc; }
    table.transactions tr:hover { background:#f4f6f8; }
    .transactions td.actions a {
      margin-right:.5rem; font-size: .9rem; text-decoration:none;
      color: var(--primary);
      transition: color .2s;
    }
    .transactions td.actions a:hover { color: #0056b3; }

    /* RESPONSIVE */
    @media (max-width: 900px) {
      .content { padding:.75rem; }
      .filter-panel { grid-template-columns: 1fr; }
      table.transactions th, td { padding:.5rem; font-size:.8rem; }
    }
  </style>
</head>
<body>
  <div class="header">
    <h2>Quản lý thu chi</h2>
    <div class="user">
      <span>Xin chào, <?=htmlspecialchars($user['full_name'])?></span>
      <img src="<?=$avatarPath?>" alt="Avatar" class="avatar-header">
    </div>
  </div>

  <div class="main">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <h3>Khoản tiền</h3>
      <?php foreach($accounts as $acc): ?>
        <a href="edit_account_balance.php?account_id=<?=$acc['id']?>"
           class="account-card">
          <strong><?=htmlspecialchars($acc['name'])?></strong>
          <div><?=number_format($acc['balance'],0,',','.')?> VND</div>
        </a>
      <?php endforeach; ?>
      <a href="create_account.php" class="add-account">+ Thêm khoản tiền</a>

      <div class="account-total">
        Tổng số dư: <?=number_format($totalAccountBalance,0,',','.')?> VND
      </div>

      <hr>

      <a href="feedback.php">📩 Gửi phản hồi</a>
      <?php if($user['role']==='admin'):?>
        <a href="admin_feedback.php">📬 Xem phản hồi</a>
      <?php endif;?>
    </aside>

    <!-- MAIN CONTENT -->
    <section class="content">
      <h2>Lịch sử thu chi</h2>

      <!-- FILTER FORM -->
      <form method="get" class="filter-panel">
        <div>
          <label>Từ ngày</label>
          <input type="date" name="from_date" value="<?=htmlspecialchars($from_date)?>">
        </div>
        <div>
          <label>Đến ngày</label>
          <input type="date" name="to_date" value="<?=htmlspecialchars($to_date)?>">
        </div>
        <div>
          <label>Loại</label>
          <select name="type">
            <option value="all" <?=$filter_type==='all'?'selected':''?>>Tất cả</option>
            <option value="0" <?=$filter_type==='0'?'selected':''?>>Thu</option>
            <option value="1" <?=$filter_type==='1'?'selected':''?>>Chi</option>
            <option value="2" <?=$filter_type==='2'?'selected':''?>>Cập nhật</option>
          </select>
        </div>
        <div>
          <label>Mô tả</label>
          <select name="description">
            <option value="">Tất cả</option>
            <?php foreach(pg_fetch_all($result_desc)?:[] as $d):?>
              <option value="<?=htmlspecialchars($d['description'])?>"
                <?=$d['description']===$filter_description?'selected':''?>>
                <?=htmlspecialchars($d['description'])?>
              </option>
            <?php endforeach;?>
          </select>
        </div>
        <div>
          <label>Khoản tiền</label>
          <select name="account_id">
            <option value="0" <?=$filter_account===0?'selected':''?>>Tất cả</option>
            <?php foreach($accounts as $acc):?>
              <option value="<?=$acc['id']?>" <?=$filter_account===$acc['id']?'selected':''?>>
                <?=htmlspecialchars($acc['name'])?>
              </option>
            <?php endforeach;?>
          </select>
        </div>

        <!-- Tổng thu/chi -->
        <div class="stats">
          <span class="income">🔼 <?=number_format($totalThuAll,0,',','.')?> VND</span>
          <span class="expense">🔽 <?=number_format($totalChiAll,0,',','.')?> VND</span>
        </div>

        <!-- Buttons -->
        <button type="submit">Lọc</button>
        <a href="dashboard.php" class="reset">Làm mới</a>
      </form>

      <!-- TRANSACTIONS LIST -->
      <?php if(empty($grouped)): ?>
        <p>Không có giao dịch nào.</p>
      <?php else: ?>
        <?php foreach($grouped as $date=>$entries): ?>
          <?php
            $thu=0; $chi=0;
            foreach($entries as $r){
              if($r['type']==0)$thu+=$r['amount'];
              elseif($r['type']==1)$chi+=$r['amount'];
            }
          ?>
          <div class="group">
            <div class="date-block">
              <span><?=$date?></span>
              <span>
                <span class="income">🔼 <?=number_format($thu,0,',','.')?></span>
                <span class="expense">🔽 <?=number_format($chi,0,',','.')?></span>
              </span>
            </div>

            <table class="transactions">
              <thead>
                <tr>
                  <th>Giờ</th><th>Loại</th><th>Số tiền</th>
                  <th>Mô tả</th><th>Số dư</th><th>Khoản tiền</th><th>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($entries as $r): ?>
                  <tr>
                    <td><?=date('H:i:s',strtotime($r['date']))?></td>
                    <td><?=$typeLabels[$r['type']]?></td>
                    <td class="<?=$r['type']==0?'amount-income':'amount-expense'?>">
                      <?= $r['type']==2 ? '0' : number_format($r['amount'],0,',','.')?> VND
                    </td>
                    <td>
                      <?php
                        $d=$r['description'];
                        if(strpos($d,'Tạo tài khoản mới:')===0) $d='Tạo khoản tiền mới';
                        echo !empty($d)?htmlspecialchars($d):'-';
                      ?>
                    </td>
                    <td><?=number_format($r['remaining_balance'],0,',','.')?> VND</td>
                    <td><?=htmlspecialchars($r['account_name'])?></td>
                    <td class="actions">
                      <a href="edit_transaction.php?id=<?=$r['id']?>">✏️</a>
                      <a href="delete_transaction.php?id=<?=$r['id']?>" 
                         onclick="return confirm('Bạn có chắc muốn xoá giao dịch này?')">
                         🗑️
                      </a>
                    </td>
                  </tr>
                <?php endforeach;?>
              </tbody>
            </table>
          </div>
        <?php endforeach;?>
      <?php endif;?>
    </section>
  </div>
</body>
</html>
