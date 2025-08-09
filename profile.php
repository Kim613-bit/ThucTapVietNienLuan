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
  <link rel="stylesheet" href="dashboard.css"> <!-- dùng chung CSS -->
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
