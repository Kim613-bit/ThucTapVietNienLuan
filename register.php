<?php
// register.php
session_start();
include "db.php";       // Kết nối PostgreSQL: $conn

$success = "";
$old     = [];          // Lưu lại giá trị đã nhập
$errors  = [];          // Mảng lỗi chi tiết

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Sanitize & giữ lại giá trị cũ
    $old['username']  = trim($_POST["username"]  ?? "");
    $old['password']  =             $_POST["password"]  ?? "";
    $old['confirm']   =             $_POST["confirm"]   ?? "";
    $old['fullname']  = trim($_POST["fullname"]  ?? "");
    $old['birthyear'] =             $_POST["birthyear"] ?? "";
    $old['email']     = trim($_POST["email"]     ?? "");

    // 2. Server-side validation

    // 2.1 Username: 1–50 ký tự, chỉ chữ và số
    if (strlen($old['username']) < 1 || strlen($old['username']) > 50) {
        $errors['username'] = "Tên đăng nhập phải từ 1–50 ký tự!";
    }
    elseif (!preg_match('/^[A-Za-z0-9]+$/', $old['username'])) {
        $errors['username'] = "Tên đăng nhập chỉ chứa chữ và số, không khoảng trắng!";
    }

    // 2.2 Password & Confirm
    if (!isset($errors['username'])) {
        if ($old['password'] !== $old['confirm']) {
            $errors['confirm'] = "Mật khẩu xác nhận không khớp!";
        }
        elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*\W).{6,}$/', $old['password'])) {
            $errors['password'] = "Mật khẩu ít nhất 6 ký tự, có 1 hoa, 1 số, 1 ký tự đặc biệt!";
        }
    }

    // 2.3 Fullname: chỉ chữ (có dấu) và khoảng trắng
    if (!preg_match('/^[A-Za-zÀ-ỵ\s]+$/u', $old['fullname'])) {
        $errors['fullname'] = "Họ và tên chỉ chứa chữ và khoảng trắng!";
    }

    // 2.4 Email chuẩn RFC
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Email không đúng định dạng!";
    }

    // 2.5 Birthyear: 1900 → năm hiện tại
    $by = intval($old['birthyear']);
    $cy = intval(date('Y'));
    if ($by < 1900 || $by > $cy) {
        $errors['birthyear'] = "Năm sinh phải từ 1900 đến $cy!";
    }

    // 3. Kiểm tra trùng username trong DB
    if (empty($errors)) {
        $res = pg_query_params($conn,
            "SELECT id FROM users WHERE username = $1",
            [ $old['username'] ]
        );
        if ($res && pg_num_rows($res) > 0) {
            $errors['username'] = "Tên đăng nhập đã tồn tại!";
        }
    }

    // 4. Lưu user mới
    if (empty($errors)) {
        $hash = password_hash($old['password'], PASSWORD_DEFAULT);
        $res = pg_query_params($conn,
            "INSERT INTO users (username,password,fullname,birthyear,email)
             VALUES ($1,$2,$3,$4,$5)",
            [
              $old['username'],
              $hash,
              $old['fullname'],
              $by,
              $old['email']
            ]
        );
        if ($res) {
            $success = "Tạo tài khoản thành công! Bạn có thể <a href='login.php'>đăng nhập</a>.";
            $old = [];  // Xoá data cũ
        } else {
            $errors['general'] = "Lỗi khi tạo tài khoản, vui lòng thử lại.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng ký tài khoản</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background:#f1f1f1; margin:0; padding:0;
           display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .container { background:#fff; padding:30px; border-radius:12px;
                 box-shadow:0 0 12px rgba(0,0,0,0.08); width:100%; max-width:400px; }
    h2 { text-align:center; margin-bottom:20px; }
    input, button { width:100%; padding:12px; font-size:15px; border-radius:8px; }
    input { margin-bottom:5px; border:1px solid #ccc; }
    button { border:none; background:#28a745; color:#fff; font-weight:bold;
             cursor:pointer; transition:background .2s; }
    button:hover { background:#218838; }
    .error { color:red; font-size:14px; margin:5px 0 10px; }
    .success { color:green; text-align:center; margin-bottom:10px; }
    .link { text-align:center; margin-top:15px; }
    .link a { color:#007bff; text-decoration:none; }
    .link a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <div class="container">
    <h2>📝 Đăng ký tài khoản</h2>

    <?php if (!empty($errors['general'])): ?>
      <p class="error"><?= htmlspecialchars($errors['general']) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
      <p class="success"><?= $success ?></p>
    <?php endif; ?>

    <form method="post" novalidate>
      <!-- Username -->
      <input
        type="text"
        name="username"
        placeholder="Tên đăng nhập"
        value="<?= htmlspecialchars($old['username'] ?? '') ?>"
        required
        maxlength="50"
        pattern="^[A-Za-z0-9]{1,50}$"
        title="1–50 ký tự, chỉ chữ và số"
      />
      <div class="error"><?= $errors['username'] ?? '' ?></div>

      <!-- Password -->
      <input
        type="password"
        name="password"
        placeholder="Mật khẩu (6+ ký tự, 1 hoa, 1 số, 1 đặc biệt)"
        required
        pattern="(?=.*[A-Z])(?=.*\d)(?=.*\W).{6,}"
        title="Ít nhất 6 ký tự, 1 chữ hoa, 1 số, 1 ký tự đặc biệt"
      />
      <div class="error"><?= $errors['password'] ?? '' ?></div>

      <!-- Confirm Password -->
      <input
        type="password"
        name="confirm"
        placeholder="Xác nhận mật khẩu"
        required
        title="Phải khớp với mật khẩu"
      />
      <div class="error"><?= $errors['confirm'] ?? '' ?></div>

      <!-- Fullname -->
      <input
        type="text"
        name="fullname"
        placeholder="Họ và tên"
        value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
        required
        pattern="^[A-Za-zÀ-ỵ\s]+$"
        title="Chỉ chứa chữ và khoảng trắng"
      />
      <div class="error"><?= $errors['fullname'] ?? '' ?></div>

      <!-- Birthyear -->
      <input
        type="number"
        name="birthyear"
        placeholder="Năm sinh"
        value="<?= htmlspecialchars($old['birthyear'] ?? '') ?>"
        required
        min="1900"
        max="<?= date('Y') ?>"
      />
      <div class="error"><?= $errors['birthyear'] ?? '' ?></div>

      <!-- Email -->
      <input
        type="email"
        name="email"
        placeholder="Email"
        value="<?= htmlspecialchars($old['email'] ?? '') ?>"
        required
      />
      <div class="error"><?= $errors['email'] ?? '' ?></div>

      <button type="submit">Đăng ký</button>
    </form>

    <div class="link">
      Đã có tài khoản? <a href="login.php">Đăng nhập</a>
    </div>
  </div>

  <script>
    // Hỏi lại trước khi submit (tuỳ chọn)
    document.querySelector("form").addEventListener("submit", function(e) {
      if (!confirm("Bạn chắc chắn muốn tạo tài khoản?")) {
        e.preventDefault();
      }
    });
  </script>
</body>
</html>
