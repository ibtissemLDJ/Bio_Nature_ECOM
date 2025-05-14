<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid customer ID';
    header("Location: customers.php");
    exit();
}

$user_id = (int)$_GET['id'];

// Check if user has any orders
$stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order_count = $result->fetch_assoc()['order_count'];
$stmt->close();

if ($order_count > 0) {
    $_SESSION['error'] = 'Cannot ban customer with existing orders';
    header("Location: customers.php");
    exit();
}

// Update user status to banned
$stmt = $conn->prepare("UPDATE users SET is_banned = 1, is_active = 0 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $_SESSION['message'] = 'Customer has been banned successfully';
} else {
    $_SESSION['error'] = 'Error banning customer: ' . $stmt->error;
}
$stmt->close();

header("Location: customers.php");
exit(); 