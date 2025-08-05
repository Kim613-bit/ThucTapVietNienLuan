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
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            height: 100%;
            background-color: #f9f9f9;
        }

        /* --- Header --- */
        .header {
            background: #007BFF;
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
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
        }

        /* --- Layout --- */
        .main {
            display: flex;
            flex-wrap: wrap;
            min-height: 100vh;
        }
        .sidebar {
            width: 100%;
            max-width: 300px;
            background-color: #f0f0f0;
            padding: 1rem;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar a {
            display: block;
            margin-bottom: 0.75rem;
            color: #333;
            text-decoration: none;
        }

        .content {
            flex: 1;
            padding: 1rem;
        }

        /* --- Profile box --- */
        .profile-box {
            background: white;
            padding: 1rem;
            border: 1px solid #ccc;
            border-radius: 0.5rem;
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
            width: 100%;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 0.3rem;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        button[type="submit"] {
            background-color: #007BFF;
            color: white;
        }
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        .btn-delete {
            background-color: red;
            color: white;
        }
        .btn-delete:hover {
            background-color: darkred;
        }

        .success {
            color: green;
            margin-bottom: 1rem;
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
            <a href="profile.php">👤 Hồ sơ cá nhân</a>
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

