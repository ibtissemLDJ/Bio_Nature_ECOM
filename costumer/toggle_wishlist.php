<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['warning_message'] = "Please log in to manage your wishlist.";
    header('Location: login.php');
    exit();
}

// Get item_id from POST request
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

// Validate item_id
if ($item_id <= 0) {
    $_SESSION['error_message'] = "Invalid product specified.";
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the item is already in the user's wishlist
$check_sql = "SELECT COUNT(*) FROM favorites WHERE user_id = ? AND item_id = ?";
$stmt_check = $conn->prepare($check_sql);

if ($stmt_check) {
    $stmt_check->bind_param("ii", $user_id, $item_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        // Item is in wishlist, remove it
        $delete_sql = "DELETE FROM favorites WHERE user_id = ? AND item_id = ?";
        $stmt_delete = $conn->prepare($delete_sql);
        if ($stmt_delete) {
            $stmt_delete->bind_param("ii", $user_id, $item_id);
            $stmt_delete->execute();
            if ($stmt_delete->affected_rows > 0) {
                $_SESSION['success_message'] = "Item removed from your wishlist.";
            }
            $stmt_delete->close();
        }
    } else {
        // Item is not in wishlist, add it
        $insert_sql = "INSERT INTO favorites (user_id, item_id) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        if ($stmt_insert) {
            $stmt_insert->bind_param("ii", $user_id, $item_id);
            $stmt_insert->execute();
            if ($stmt_insert->affected_rows > 0) {
                $_SESSION['success_message'] = "Item added to your wishlist.";
            }
            $stmt_insert->close();
        }
    }
} else {
    $_SESSION['error_message'] = "An error occurred while managing your wishlist.";
}

// Redirect back to the previous page
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?> 