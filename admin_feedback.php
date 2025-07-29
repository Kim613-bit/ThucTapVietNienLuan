<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("Bạn không có quyền truy cập trang này.");
}

$admin_id = $_SESSION['user_id'];

// Lấy thông tin admin
$sql = "SELECT username, avatar FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

// Lấy phản hồi chưa xử lý để hiển thị ở sidebar
$pending_feedbacks_sql = "
    SELECT f.id, u.username, f.message
    FROM feedbacks f
    JOIN users u ON f.user_id = u.id
    WHERE f.status = 'pending'
    ORDER BY f.created_at DESC
";
$pending_feedbacks = mysqli_query($conn, $pending_feedbacks_sql);

// Lọc phản hồi theo trạng thái
$status_filter = $_GET['status'] ?? '';

$feedback_sql = "
    SELECT f.*, u.username
    FROM feedbacks f
    JOIN users u ON f.user_id = u.id
";

if ($status_filter === 'processed') {
    $feedback_sql .= " WHERE f.status = 'processed'";
} elseif ($status_filter === 'ignored') {
    $feedback_sql .= " WHERE f.status = 'ignored'";
} elseif ($status_filter === 'pending') {
    $feedback_sql .= " WHERE f.status = 'pending'";
}

$feedback_sql .= " ORDER BY f.created_at DESC";
$feedbacks = mysqli_query($conn, $feedback_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Phản hồi người dùng</title>
    <style>
        body { font-family: Arial; margin: 0; }
        .header {
            background: #6f42c1;
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header .user a {
            text-decoration: none;
            display: flex;
            align-items: center;
            color: white;
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
            width: 250px;
            background: #f5f5f5;
            padding: 20px;
            height: 100vh;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
        }
        th {
            background: #eee;
        }
        .feedback-content {
            white-space: pre-line;
        }
        .status-actions form {
            display: flex;
            gap: 5px;
        }
        .status-actions button {
            padding: 4px 8px;
            font-size: 13px;
        }
        .sidebar h4 {
            margin-top: 30px;
        }
        .sidebar ul {
            padding-left: 15px;
        }

        /* Zoom popup */
        #overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        #overlay img {
            max-width: 90%;
            max-height: 90%;
            border: 4px solid white;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            cursor: zoom-out;
        }
    </style>
</head>
<body>
<div class="header">
    <h2>📬 Phản hồi người dùng</h2>
    <div class="user">
        <span><?= htmlspecialchars($admin['username']) ?></span>
        <img src="<?= !empty($admin['avatar']) ? 'uploads/' . htmlspecialchars($admin['avatar']) : 'default-avatar.png' ?>" alt="Avatar">
    </div>
</div>
<div class="main">
    <div class="sidebar">
        <h3>Menu quản trị</h3>
        <ul style="list-style: none; padding: 0; margin-top: 20px;">
            <li><a href="logout.php">🚪 Đăng xuất</a></li>
        </ul>

        <?php if (mysqli_num_rows($pending_feedbacks) > 0): ?>
            <h4>📌 Phản hồi chưa xử lý</h4>
            <ul>
                <?php while ($row = mysqli_fetch_assoc($pending_feedbacks)): ?>
                    <li>
                        <strong><?= htmlspecialchars($row['username']) ?></strong>: 
                        <?= htmlspecialchars(mb_strimwidth($row['message'], 0, 40, "...")) ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="content">
        <h3>Danh sách phản hồi</h3>
        <form method="get" style="margin-top: 10px; margin-bottom: 20px;">
            <label for="status_filter">Lọc theo trạng thái:</label>
            <select name="status" id="status_filter" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chưa xử lý</option>
                <option value="processed" <?= $status_filter === 'processed' ? 'selected' : '' ?>>Đã xử lý</option>
                <option value="ignored" <?= $status_filter === 'ignored' ? 'selected' : '' ?>>Không xử lý</option>
            </select>
        </form>

        <?php if (mysqli_num_rows($feedbacks) === 0): ?>
            <p>Chưa có phản hồi nào từ người dùng.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Người gửi</th>
                    <th>Nội dung</th>
                    <th>Thời gian</th>
                    <th>Trạng thái</th>
                </tr>
                <?php while ($row = mysqli_fetch_assoc($feedbacks)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td>
                            <?= nl2br(htmlspecialchars($row['message'])) ?>
                            <?php if (!empty($row['image'])): ?>
                                <div style="margin-top: 8px;">
                                    <img src="<?= htmlspecialchars($row['image']) ?>" alt="Feedback image" class="zoomable" style="max-width: 200px; max-height: 150px; border: 1px solid #ccc; cursor: zoom-in;">
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= $row['created_at'] ?></td>
                        <td class="status-actions">
                            <?php if ($row['status'] === 'processed'): ?>
                                <span style="color: green;">✔ Đã xử lý</span>
                            <?php elseif ($row['status'] === 'ignored'): ?>
                                <span style="color: gray;">🚫 Không xử lý</span>
                            <?php elseif ($row['status'] === 'pending' || $row['status'] === null): ?>
                                <form method="post" action="update_feedback_status.php">
                                    <input type="hidden" name="feedback_id" value="<?= $row['id'] ?>">
                                    <button name="action" value="processed" style="background: #ffc107;">Đã xử lý</button>
                                    <button name="action" value="ignored" style="background: #ddd;">Không xử lý</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Overlay phóng to ảnh -->
<div id="overlay" onclick="hideOverlay()">
    <img id="overlay-img" src="" alt="Zoomed Image">
</div>

<script>
    const images = document.querySelectorAll('.zoomable');
    const overlay = document.getElementById('overlay');
    const overlayImg = document.getElementById('overlay-img');

    images.forEach(image => {
        image.addEventListener('click', function (e) {
            e.stopPropagation(); // Ngăn chặn lan sự kiện click
            overlay.style.display = 'flex';
            overlayImg.src = this.src;
        });
    });

    function hideOverlay() {
        overlay.style.display = 'none';
        overlayImg.src = '';
    }

    // Đóng khi nhấn ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === "Escape") {
            hideOverlay();
        }
    });
</script>
</body>
</html>
