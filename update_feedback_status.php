<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Kiểm tra tồn tại dữ liệu POST
    if (isset($_POST['feedback_id'], $_POST['action'])) {
        $feedback_id = (int)$_POST['feedback_id'];
        $action = $_POST['action'];

        // Chỉ chấp nhận 2 giá trị hợp lệ
        if (in_array($action, ['processed', 'ignored'])) {
            $stmt = mysqli_prepare($conn, "UPDATE feedbacks SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $action, $feedback_id);

            if (mysqli_stmt_execute($stmt)) {
                // Thành công
                mysqli_stmt_close($stmt);
                header("Location: admin_feedback.php");
                exit();
            } else {
                echo "❌ Không thể cập nhật phản hồi.";
            }
        } else {
            echo "❌ Giá trị 'action' không hợp lệ.";
        }
    } else {
        echo "❌ Thiếu dữ liệu đầu vào.";
    }
} else {
    echo "❌ Phương thức không hợp lệ.";
}
?>
