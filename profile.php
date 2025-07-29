<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin người dùng
$sql = "SELECT username, avatar, fullname, birthyear, email FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Cập nhật thông tin hoặc xóa tài khoản nếu gửi POST
$success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nếu là nút xóa tài khoản
    if (isset($_POST['delete_account'])) {
        // Xóa các dữ liệu liên quan (nếu có)
        mysqli_query($conn, "DELETE FROM transactions WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM accounts WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM descriptions WHERE user_id = $user_id");

        // Xóa người dùng
        $sql_delete = "DELETE FROM users WHERE id = ?";
        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
        mysqli_stmt_execute($stmt_delete);

        // Xoá session và chuyển về đăng ký
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Nếu là cập nhật thông tin
    $fullname = $_POST['fullname'];
    $birthyear = $_POST['birthyear'];
    $email = $_POST['email'];

    $avatar = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = time() . "_" . basename($_FILES['avatar']['name']);
        $upload_path = "uploads/" . $filename;
        if (!is_dir("uploads")) mkdir("uploads");
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            $avatar = $filename;
        }
    }

    $sql_update = "UPDATE users SET fullname = ?, birthyear = ?, email = ?, avatar = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "sissi", $fullname, $birthyear, $email, $avatar, $user_id);
    mysqli_stmt_execute($stmt_update);

    $success = "✅ Cập nhật hồ sơ thành công!";
    $user['fullname'] = $fullname;
    $user['birthyear'] = $birthyear;
    $user['email'] = $email;
    $user['avatar'] = $avatar;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hồ sơ cá nhân</title>
    <style>
        body { font-family: Arial; margin: 0; }
        .header {
            background: #007BFF;
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        .main { display: flex; }
        .sidebar {
            width: 300px;
            background: #f0f0f0;
            padding: 20px;
            height: 100vh;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar a {
            display: block;
            margin-bottom: 10px;
            text-decoration: none;
            color: #333;
        }
        .content {
            flex: 1;
            padding: 20px;
        }
        input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }
        button {
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
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
            margin-bottom: 10px;
        }
        .profile-box {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
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
        <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

        <div class="profile-box">
            <form method="post" enctype="multipart/form-data">
                <label>Họ tên:</label><br>
                <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required><br>

                <label>Năm sinh:</label><br>
                <input type="number" name="birthyear" value="<?= htmlspecialchars($user['birthyear']) ?>" required><br>

                <label>Email:</label><br>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><br>

                <label>Ảnh đại diện:</label><br>
                <input type="file" name="avatar"><br><br>

                <?php if (!empty($user['avatar'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" width="100" height="100" style="border-radius: 50%; object-fit: cover;">
                <?php endif; ?>
                <br><br>

                <button type="submit" onclick="return confirm('✅ Bạn có chắc chắn muốn cập nhật thông tin không?');">
                    Cập nhật
                </button>
                <br><br>
                <button type="submit" name="delete_account" class="btn-delete" onclick="return confirm('❌ Bạn có chắc chắn muốn xóa tài khoản không? Thao tác này không thể hoàn tác!');">
                    ❌ Xóa tài khoản
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
