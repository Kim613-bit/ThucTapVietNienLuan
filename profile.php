<?php
session_start();
include "db.php";           // kết nối PostgreSQL
include "validation.php";   // chứa các hàm validate_fullname(), validate_birthyear(), validate_email()

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$errors   = [ 'fullname'=>'', 'birthyear'=>'', 'email'=>'' ];
$success  = '';
// Lấy thông tin người dùng để hiển thị mặc định
$sql    = "SELECT username, avatar, fullname, birthyear, email FROM users WHERE id = $1";
$result = pg_query_params($conn, $sql, [ $user_id ]);
$user   = pg_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2.1 Xóa tài khoản
    if (isset($_POST['delete_account'])) {
        pg_query_params($conn, "DELETE FROM transactions WHERE user_id = $1", [ $user_id ]);
        pg_query_params($conn, "DELETE FROM accounts     WHERE user_id = $1", [ $user_id ]);
        pg_query_params($conn, "DELETE FROM descriptions WHERE user_id = $1", [ $user_id ]);
        pg_query_params($conn, "DELETE FROM users        WHERE id       = $1", [ $user_id ]);
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // 2.2 Thu thập input
    $fullname  = trim($_POST['fullname']);
    $birthyear = trim($_POST['birthyear']);
    $email     = trim($_POST['email']);

    // 2.3 Server-side validation
    $errors['fullname']  = validate_fullname($fullname);
    $errors['birthyear'] = validate_birthyear($birthyear);
    $errors['email']     = validate_email($email);

    // 2.4 Xử lý avatar upload (nếu có)
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

    // 2.5 Nếu không có lỗi, thực hiện UPDATE
    if (empty($errors['fullname']) && empty($errors['birthyear']) && empty($errors['email'])) {
        $sql_update = "UPDATE users 
                       SET fullname = $1, birthyear = $2, email = $3, avatar = $4 
                       WHERE id = $5";
        pg_query_params($conn, $sql_update, [ $fullname, $birthyear, $email, $avatar, $user_id ]);

        $success = "✅ Cập nhật hồ sơ thành công!";
        // cập nhật lại $user để hiển thị ngay
        $user['fullname']  = $fullname;
        $user['birthyear'] = $birthyear;
        $user['email']     = $email;
        $user['avatar']    = $avatar;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hồ sơ cá nhân</title>
    <style>
        /* ... giữ nguyên style cũ ... */
        .error { color: red; margin-top: -8px; margin-bottom: 8px; font-size: 0.9em; }
    </style>
</head>
<body>
    <!-- header, sidebar giống cũ -->
    <div class="content">
        <h2>👤 Hồ sơ cá nhân</h2>
        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>

        <div class="profile-box">
            <form method="post" enctype="multipart/form-data">

                <!-- Fullname -->
                <label>Họ tên:</label><br>
                <input
                  type="text"
                  name="fullname"
                  value="<?= htmlspecialchars($user['fullname']) ?>"
                  required
                  pattern="^[A-Za-zÀ-ỹ ]{2,100}$"
                  title="Chỉ chứa chữ và khoảng trắng, từ 2 đến 100 ký tự."
                ><br>
                <?php if ($errors['fullname']): ?>
                    <div class="error"><?= $errors['fullname'] ?></div>
                <?php endif; ?>

                <!-- Birthyear -->
                <label>Năm sinh:</label><br>
                <input
                  type="number"
                  name="birthyear"
                  value="<?= htmlspecialchars($user['birthyear']) ?>"
                  required
                  min="1900"
                  max="<?= date('Y') ?>"
                ><br>
                <?php if ($errors['birthyear']): ?>
                    <div class="error"><?= $errors['birthyear'] ?></div>
                <?php endif; ?>

                <!-- Email -->
                <label>Email:</label><br>
                <input
                  type="email"
                  name="email"
                  value="<?= htmlspecialchars($user['email']) ?>"
                  required
                ><br>
                <?php if ($errors['email']): ?>
                    <div class="error"><?= $errors['email'] ?></div>
                <?php endif; ?>

                <!-- Avatar -->
                <label>Ảnh đại diện:</label><br>
                <input type="file" name="avatar" accept="image/*"><br><br>
                <?php if (!empty($user['avatar'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>"
                         width="100" height="100"
                         style="border-radius: 50%; object-fit: cover;">
                <?php endif; ?>
                <br><br>

                <button type="submit"
                  onclick="return confirm('✅ Bạn có chắc chắn muốn cập nhật thông tin không?');">
                    Cập nhật
                </button>
                <br><br>
                <button type="submit" name="delete_account" class="btn-delete"
                  onclick="return confirm('❌ Bạn có chắc chắn muốn xóa tài khoản không? Thao tác này không thể hoàn tác!');">
                    ❌ Xóa tài khoản
                </button>
            </form>
        </div>
    </div>
</body>
</html>
