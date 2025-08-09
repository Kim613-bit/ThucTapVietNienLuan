<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}
$user_id = $_SESSION['user_id'];
$success = "";

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['delete_account'])) {
    pg_query_params($conn, "DELETE FROM transactions WHERE user_id = $1", [$user_id]);
    pg_query_params($conn, "DELETE FROM accounts WHERE user_id = $1", [$user_id]);
    pg_query_params($conn, "DELETE FROM descriptions WHERE user_id = $1", [$user_id]);
    pg_query_params($conn, "DELETE FROM users WHERE id = $1", [$user_id]);
    session_destroy();
    header("Location: login.php");
    exit();
  }

  $fullname = trim($_POST['fullname']);
  $birthyear = $_POST['birthyear'];
  $email = $_POST['email'];
  $avatar = '';

  if (strlen($fullname) > 30) {
    $success = "❌ Tên không được vượt quá 30 ký tự!";
  } else {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
      $type = mime_content_type($_FILES['avatar']['tmp_name']);
      $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
      if ($type !== 'image/png' || $ext !== 'png') {
        $success = "❌ Avatar phải là file .png!";
      } else {
        $filename = time() . "_" . basename($_FILES['avatar']['name']);
        $upload_path = "uploads/" . $filename;
        if (!is_dir("uploads")) mkdir("uploads");
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
          $avatar = $filename;
        }
      }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $success = "❌ Email không hợp lệ!";
    }

    if (!$avatar) {
      $res_old = pg_query_params($conn, "SELECT avatar FROM users WHERE id = $1", [$user_id]);
      $old = pg_fetch_assoc($res_old);
      $avatar = $old['avatar'];
    }

    pg_query_params($conn,
      "UPDATE users SET fullname = $1, birthyear = $2, email = $3, avatar = $4 WHERE id = $5",
      [$fullname, $birthyear, $email, $avatar, $user_id]
    );
    $success = "✅ Cập nhật hồ sơ thành công!";
  }
}

// Tải thông tin người dùng
$res = pg_query_params($conn, "SELECT username, avatar, fullname, birthyear, email FROM users WHERE id = $1", [$user_id]);
$user = pg_fetch_assoc($res);
$avatarPath = 'uploads/' . (!empty($user['avatar']) ? $user['avatar'] : 'avt_mem.png');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Hồ sơ cá nhân</title>
  <style>
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
    
    /* Reset & Base */
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: var(--color-bg);
      color: var(--color-text);
      line-height: 1.6;
    }
    a {
      text-decoration: none;
      color: inherit;
    }
    
    /* Header */
    .header {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: var(--color-primary);
      color: white;
      padding: 12px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header .user {
      display: flex;
      align-items: center;
    }
    .header .user a {
      display: flex;
      align-items: center;
      text-decoration: none;
      color: white;
    }
    .header .user span {
      font-weight: bold;
    }
    .header .user img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin-left: 10px;
      object-fit: cover;
      border: 2px solid white;
    }
    .brand {
      font-size: 1.75rem;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: #fff;
    }
    
    /* Layout */
    .dashboard-wrapper {
      display: grid;
      grid-template-columns: var(--sidebar-width) 1fr;
      gap: var(--spacing);
      padding: var(--spacing);
    }
    .sidebar {
      background-color: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .sidebar h3 {
      font-size: 0.9rem;
      color: var(--color-muted);
      margin-bottom: 12px;
    }
    .sidebar a {
      display: block;
      margin-bottom: 12px;
      color: var(--color-text);
      text-decoration: none;
      font-weight: 500;
    }
    .sidebar a:hover {
      color: var(--color-primary);
    }
    .content {
      background-color: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    
    /* Profile Box */
    .profile-box {
      background-color: var(--color-bg);
      border: 1px solid #e2e8f0;
      border-radius: var(--border-radius);
      padding: var(--spacing);
    }
    .profile-box label {
      font-weight: 600;
      margin-top: 12px;
      display: block;
      font-size: 0.95rem;
    }
    .profile-box input[type="text"],
    .profile-box input[type="number"],
    .profile-box input[type="email"],
    .profile-box input[type="file"] {
      width: 100%;
      padding: 10px;
      margin-top: 6px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 0.95rem;
    }
    .profile-box img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin-top: 12px;
      border: 2px solid var(--color-primary);
    }
    
    /* Buttons */
    button {
      padding: 10px 16px;
      border: none;
      border-radius: var(--border-radius);
      font-size: 1rem;
      cursor: pointer;
      margin-top: 16px;
      width: 100%;
    }
    button[type="submit"] {
      background-color: var(--color-primary);
      color: white;
    }
    button[type="submit"]:hover {
      background-color: #1565c0;
    }
    .btn-delete {
      background-color: var(--color-danger);
      color: white;
    }
    .btn-delete:hover {
      background-color: #b71c1c;
    }
    
    /* Success Message */
    .success {
      color: green;
      margin-bottom: 1rem;
      font-weight: 500;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .dashboard-wrapper {
        grid-template-columns: 1fr;
      }
      .sidebar {
        width: 100%;
        margin-bottom: 1rem;
      }
      .header h2 {
        font-size: 1.3rem;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <h2 class="brand">Hồ sơ người dùng</h2>
    <div class="user">
      <a href="profile.php" class="profile-link">
        <span>Xin chào, <?= htmlspecialchars($user['fullname']) ?></span>
        <img src="<?= $avatarPath ?>" alt="Avatar">
      </a>
    </div>
  </div>

  <div class="dashboard-wrapper">
    <!-- Sidebar -->
    <nav class="sidebar">
      <h3><a href="advanced_statistics.php">📊 Thống kê nâng cao</a></h3>
      <h3>Chức năng</h3>
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="feedback.php">📩 Gửi phản hồi</a>
      <?php if ($user['username'] === 'admin'): ?>
        <a href="admin_feedback.php">📬 Xem phản hồi</a>
      <?php endif; ?>
      <a href="logout.php">🔓 Đăng xuất</a>
    </nav>

    <!-- Content -->
    <div class="content">
      <h2>👤 Hồ sơ cá nhân</h2>
      <?php if ($success): ?>
        <p class="success"><?= $success ?></p>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="profile-box">
        <label>Họ tên:</label>
        <input type="text" name="fullname" maxlength="30" value="<?= htmlspecialchars($user['fullname']) ?>" required>

        <label>Năm sinh:</label>
        <input type="number" name="birthyear" value="<?= htmlspecialchars($user['birthyear']) ?>" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Ảnh đại diện (.png):</label>
        <input type="file" name="avatar" accept=".png">
        <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar">

        <button type="submit" onclick="return confirm('✅ Bạn có chắc chắn muốn cập nhật thông tin không?');">Cập nhật</button>
        <button type="submit" name="delete_account" class="btn-delete" onclick="return confirm('❌ Bạn có chắc chắn muốn xóa tài khoản không?');">❌ Xóa tài khoản</button>
      </form>
    </div>
  </div>
</body>
</html>
