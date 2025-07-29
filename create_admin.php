<?php
include "db.php";

$username = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT); // Băm đúng cách
$user_id = 1; // Đảm bảo user admin có ID = 1

// Kiểm tra xem đã tồn tại chưa
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($check, "s", $username);
mysqli_stmt_execute($check);
$result = mysqli_stmt_get_result($check);

if (mysqli_fetch_assoc($result)) {
    echo "Tài khoản admin đã tồn tại!";
    exit;
}

// Thêm tài khoản mới
$stmt = mysqli_prepare($conn, "INSERT INTO users (id, username, password) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iss", $user_id, $username, $password);

if (mysqli_stmt_execute($stmt)) {
    echo "✅ Tạo tài khoản admin thành công!";
} else {
    echo "❌ Lỗi khi tạo tài khoản: " . mysqli_error($conn);
}
?>
