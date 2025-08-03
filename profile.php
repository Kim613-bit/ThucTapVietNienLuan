<?php
session_start();
include "db.php"; // kết nối PostgreSQL, ví dụ dùng pg_connect()

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 🔹 Lấy thông tin người dùng
$sql    = "SELECT username, avatar, fullname, birthyear, email FROM users WHERE id = $1";
$result = pg_query_params($conn, $sql, array($user_id));
$user   = pg_fetch_assoc($result);

// 🔹 Cập nhật thông tin hoặc xóa tài khoản nếu gửi POST
$success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_account'])) {
        // Xóa dữ liệu liên quan
        pg_query_params($conn, "DELETE FROM transactions WHERE user_id = $1", array($user_id));
        pg_query_params($conn, "DELETE FROM accounts WHERE user_id = $1", array($user_id));
        pg_query_params($conn, "DELETE FROM descriptions WHERE user_id = $1", array($user_id));
        pg_query_params($conn, "DELETE FROM users WHERE id = $1", array($user_id));

        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Xử lý cập nhật thông tin
    $fullname  = $_POST['fullname'];
    $birthyear = $_POST['birthyear'];
    $email     = $_POST['email'];

    $avatar = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext         = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename    = time() . "_" . basename($_FILES['avatar']['name']);
        $upload_path = "uploads/" . $filename;
        if (!is_dir("uploads")) mkdir("uploads");
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            $avatar = $filename;
        }
    }

    $sql_update = "UPDATE users SET fullname = $1, birthyear = $2, email = $3, avatar = $4 WHERE id = $5";
    pg_query_params($conn, $sql_update, array($fullname, $birthyear, $email, $avatar, $user_id));

    $success               = "✅ Cập nhật hồ sơ thành công!";
    $user['fullname']      = $fullname;
    $user['birthyear']     = $birthyear;
    $user['email']         = $email;
    $user['avatar']        = $avatar;
}
?>
    
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hồ sơ cá nhân</title>
    <style>
        /* Base Reset */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        /* Typography & Layout */
        body, html {
            font-family: Arial, sans-serif;
            height: 100%;
            background-color: #f9f9f9;
        }
        
        .header {
            background-color: #007BFF;
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .header h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
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
            font-weight: bold;
        }
        
        .header .user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 0.5rem;
        }
        
        /* Main layout */
        .main {
            display: flex;
            flex-wrap: wrap;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 100%;
            max-width: 280px;
            background-color: #f0f0f0;
            padding: 1rem;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        }
        
        .sidebar a {
            display: block;
            margin-bottom: 0.75rem;
            color: #333;
            text-decoration: none;
            font-size: 1rem;
        }
        
        .content {
            flex: 1;
            padding: 1rem;
        }
        
        /* Form & Profile box */
        .profile-box {
            background: white;
            padding: 1.5rem;
            border: 1px solid #ccc;
            border-radius: 1rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="file"],
        label {
            width: 100%;
            font-size: 1rem;
            margin-bottom: 0.75rem;
            display: block;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="file"] {
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 0.75rem;
            transition: border-color 0.3s ease;
        }
        
        input:focus {
            border-color: #007BFF;
            outline: none;
        }
        
        /* Buttons */
        button {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        button[type="submit"] {
            background-color: #007BFF;
            color: white;
        }
        
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #a71d2a;
        }
        
        .success {
            color: green;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        
        /* Avatar preview */
        .profile-box img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto 1rem auto;
        }
        
        /* Responsive tweaks */
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
        
            button {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Quản lý thu chi</h2>
        <div class="user">
            <a href="profile.php">
                <span><?= htmlspecialchars($user['username']) ?></span>
                <?php if (!empty($user['avatar'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
                <?php else: ?>
                    <img src="default-avatar.png" alt="Avatar">
                <?php endif; ?>
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
                    <label>Họ tên:</label><br>
                    <input
                        type="text"
                        name="fullname"
                        value="<?= htmlspecialchars($user['fullname']) ?>"
                        required
                    >
                    <br>

                    <label>Năm sinh:</label><br>
                    <input
                        type="number"
                        name="birthyear"
                        value="<?= htmlspecialchars($user['birthyear']) ?>"
                        required
                    >
                    <br>

                    <label>Email:</label><br>
                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($user['email']) ?>"
                        required
                    >
                    <br>

                    <label>Ảnh đại diện:</label><br>
                    <input type="file" name="avatar">
                    <br><br>

                    <?php if (!empty($user['avatar'])): ?>
                        <img
                            src="uploads/<?= htmlspecialchars($user['avatar']) ?>"
                            width="100"
                            height="100"
                            style="border-radius: 50%; object-fit: cover;"
                        >
                    <?php endif; ?>
                    <br><br>

                    <button
                        type="submit"
                        onclick="return confirm('✅ Bạn có chắc chắn muốn cập nhật thông tin không?');"
                    >
                        Cập nhật
                    </button>
                    <br><br>

                    <button
                        type="submit"
                        name="delete_account"
                        class="btn-delete"
                        onclick="return confirm('❌ Bạn có chắc chắn muốn xóa tài khoản không? Thao tác này không thể hoàn tác!');"
                    >
                        ❌ Xóa tài khoản
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
