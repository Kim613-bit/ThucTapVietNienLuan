<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['account_id'])) {
    $account_id = intval($_GET['account_id']);
    $user_id = $_SESSION['user_id'];

    // Xoá tất cả giao dịch liên quan đến tài khoản này
    $delete_transactions_sql = "DELETE FROM transactions WHERE account_id = ?";
    $stmt_transactions = mysqli_prepare($conn, $delete_transactions_sql);
    mysqli_stmt_bind_param($stmt_transactions, "i", $account_id);
    mysqli_stmt_execute($stmt_transactions);
    mysqli_stmt_close($stmt_transactions);

    // Xoá tài khoản khỏi bảng accounts
    $delete_account_sql = "DELETE FROM accounts WHERE id = ? AND user_id = ?";
    $stmt_account = mysqli_prepare($conn, $delete_account_sql);
    mysqli_stmt_bind_param($stmt_account, "ii", $account_id, $user_id);
    mysqli_stmt_execute($stmt_account);
    mysqli_stmt_close($stmt_account);
}

header("Location: dashboard.php");
exit();
?>
