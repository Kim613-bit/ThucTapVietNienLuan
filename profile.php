<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_account'])) {
        // Xóa tài khoản + dữ liệu liên quan
        pg_query_params($conn, "DELETE FROM transactions WHERE user_id = $1", [$user_id]);
        pg_query_params($conn, "DELETE FROM accounts WHERE user_id = $1", [$user_id]);
        pg_query_params($conn, "DELETE FROM descriptions WHERE user_id = $1", [$user_id]);
        pg_query_params($conn, "DELETE FROM users WHERE id = $1", [$user_id]);
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Cập nhật thông tin
    $fullname  = trim($_POST['fullname']);
    $birthyear = $_POST['birthyear'];
    $email     = $_POST['email'];
    $avatar    = '';

    // Ràng buộc: Tên không quá 30 ký tự
    if (strlen($fullname) > 30) {
        $success = "❌ Tên không được vượt quá 30 ký tự!";
    } else {
        // Xử lý avatar nếu có file upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $type = mime_content_type($_FILES['avatar']['tmp_name']);
            $ext  = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

            if ($type !== 'image/png' || $ext !== 'png') {
                $success = "❌ Avatar phải là file .png!";
            } else {
                $filename    = time() . "_" . basename($_FILES['avatar']['name']);
                $upload_path = "uploads/" . $filename;
                if (!is_dir("uploads")) mkdir("uploads");
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    $avatar = $filename;
                }
            }
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $success = "❌ Email không hợp lệ!";
            exit();
        }
        // Nếu không upload mới, giữ avatar cũ
        if (!$avatar) {
            $sql_old = "SELECT avatar FROM users WHERE id = $1";
            $res_old = pg_query_params($conn, $sql_old, [$user_id]);
            $old     = pg_fetch_assoc($res_old);
            $avatar  = $old['avatar'];
        }

        // Thực hiện cập nhật
        $sql_update = "UPDATE users SET fullname = $1, birthyear = $2, email = $3, avatar = $4 WHERE id = $5";
        pg_query_params($conn, $sql_update, [$fullname, $birthyear, $email, $avatar, $user_id]);
        $success = "✅ Cập nhật hồ sơ thành công!";
    }
}

// Tải lại thông tin user để hiển thị
$sql_user = "SELECT username, avatar, fullname, birthyear, email FROM users WHERE id = $1";
$result = pg_query_params($conn, $sql_user, [$user_id]);
if (!$result) {
    error_log("❌ Truy vấn thất bại: " . pg_last_error($conn));
    header("Location: login.php");
    exit();
}
$user = pg_fetch_assoc($result);
if (!is_array($user)) {
    header("Location: login.php");
    exit();
}
$avatarPath = 'uploads/' . (!empty($user['avatar']) ? $user['avatar'] : 'avt_mem.png');
?>
    
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hồ sơ cá nhân</title>
    <style>
        /* --- Responsive --- */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                margin-bottom: 1rem;
                box-shadow: none;
            }
            .header h2 {
                font-size: 1.5rem;
            }
            .header .user img {
                width: 32px;
                height: 32px;
            }
        }
        @media (max-width: 500px) {
            input, label, button {
                font-size: 0.9rem;
            }
            .profile-box {
                padding: 0.75rem;
            }
        }

        /* --- Base --- */
        * {
            box-sizing: border-box;
        }
        :root {
          --color-primary: #1e88e5;
          --color-danger: #e53935;
          --color-bg: #f9fafb;
          --color-card: #ffffff;
          --color-border: #e2e8f0;
          --color-text: #2e3d49;
          --color-muted: #64748b;
          --radius: 8px;
          --spacing: 16px;
        }
        
        body {
          margin: 0;
          font-family: 'Segoe UI', sans-serif;
          background-color: var(--color-bg);
          color: var(--color-text);
        }
        
        .profile-box {
          background: var(--color-box);
          border: 1px solid var(--color-border);
          border-radius: var(--radius);
          padding: 1.5rem;
          box-shadow: 0 2px 6px rgba(0,0,0,0.05);
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
          border: 1px solid var(--color-border);
          border-radius: 6px;
          font-size: 0.95rem;
        }
        
        .profile-box button {
          padding: 0.75rem 1rem;
          border: none;
          border-radius: 0.3rem;
          font-size: 1rem;
          cursor: pointer;
          margin-top: 0.5rem;
        }
        
        button[type="submit"] {
          background-color: var(--color-primary);
          color: white;
        }
        
        button[type="submit"]:hover {
          background-color: #0056b3;
        }
        
        .btn-delete {
          background-color: var(--color-danger);
          color: white;
        }
        
        .btn-delete:hover {
          background-color: #a71d2a;
        }
        
        .profile-box img {
          width: 100px;
          height: 100px;
          border-radius: 50%;
          object-fit: cover;
          margin-top: 12px;
          border: 2px solid var(--color-primary);
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            height: 100%;
            background-color: #f9f9f9;
        }

        /* --- Header --- */
        .header {
          background: var(--color-primary);
          color: white;
          padding: 12px 24px;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        .header h2 {
            margin: 0;
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

        /* --- Layout --- */
        .main {
          display: grid;
          grid-template-columns: 280px 1fr;
          gap: var(--spacing);
          padding: var(--spacing);
        }
        .sidebar {
          background: var(--color-card);
          padding: var(--spacing);
          border-radius: var(--radius);
          height: fit-content;
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
        .content {
          background: var(--color-card);
          padding: var(--spacing);
          border-radius: var(--radius);
        }
        .sidebar a:hover {
          color: var(--color-primary);
        }
        /* --- Profile box --- */
        .profile-box {
          background: var(--color-bg);
          padding: var(--spacing);
          border-radius: var(--radius);
          border: 1px solid var(--color-border);
          box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .profile-box img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* --- Form elements --- */
        input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }
        button {
            padding: 10px 16px;
              border: none;
              border-radius: 6px;
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

        .success {
            color: green;
            margin-bottom: 1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Quản lý thu chi</h2>
        <div class="user">
            <a href="profile.php">
                <span><?= htmlspecialchars($user['username']) ?></span>
                <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar">
            </a>
        </div>
    </div>

    <div class="main">
        <div class="sidebar">
            <h3>Menu</h3>
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="advanced_statistics.php">📊 Thống kê nâng cao</a>
            <a href="logout.php">🔓 Đăng xuất</a>
        </div>

        <div class="content">
            <h2>👤 Hồ sơ cá nhân</h2>
            <?php if ($success): ?>
                <p class="success"><?= $success ?></p>
            <?php endif; ?>

            <div class="profile-box">
                <form method="post" enctype="multipart/form-data">
                    <label>Họ tên:</label>
                    <input type="text" name="fullname" maxlength="30" value="<?= htmlspecialchars($user['fullname']) ?>" required>

                    <label>Năm sinh:</label>
                    <input type="number" name="birthyear" value="<?= htmlspecialchars($user['birthyear']) ?>" required>

                    <label>Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

                    <label>Ảnh đại diện:</label>
                    <input type="file" name="avatar" accept=".png">

                    <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" style="margin-bottom: 10px;">

                    <button type="submit" onclick="return confirm('✅ Bạn có chắc chắn muốn cập nhật thông tin không?');">
                        Cập nhật
                    </button>

                    <button type="submit" name="delete_account" class="btn-delete" onclick="return confirm('❌ Bạn có chắc chắn muốn xóa tài khoản không? Thao tác này không thể hoàn tác!');">
                        ❌ Xóa tài khoản
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

