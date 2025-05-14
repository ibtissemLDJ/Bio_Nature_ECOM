<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid customer ID';
    header("Location: customers.php");
    exit();
}

$user_id = (int)$_GET['id'];

// Update user status to unbanned
$stmt = $conn->prepare("UPDATE users SET is_banned = 0, is_active = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $_SESSION['message'] = 'Customer has been unbanned successfully';
} else {
    $_SESSION['error'] = 'Error unbanning customer: ' . $stmt->error;
}
$stmt->close();

header("Location: customers.php");
exit(); 