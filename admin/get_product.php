<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

$item_id = (int)$_GET['id'];

// Get product details
$stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();

// Return product data
echo json_encode([
    'status' => 'success',
    'data' => $product
]); 