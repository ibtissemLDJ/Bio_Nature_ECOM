<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please log in to complete your order.";
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['place_order'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header('Location: checkout_info.php');
    exit();
}

// Prepare addresses
$shipping_address = implode(", ", [
    $_POST['street_address_shipping'],
    $_POST['city_shipping'],
    $_POST['postal_code_shipping'],
    $_POST['country_shipping']
]);

$billing_address = $_POST['billing_same_as_shipping'] === 'on' 
    ? $shipping_address 
    : implode(", ", [
        $_POST['street_address_billing'],
        $_POST['city_billing'],
        $_POST['postal_code_billing'],
        $_POST['country_billing']
    ]);

// Start transaction
$conn->begin_transaction();

try {
    // 1. Calculate cart total
    $stmt_cart = $conn->prepare("
        SELECT SUM(ci.quantity * i.price) as subtotal
        FROM cart_items ci
        JOIN items i ON ci.item_id = i.item_id
        WHERE ci.cart_id = (SELECT cart_id FROM carts WHERE user_id = ?)
    ");
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $result = $stmt_cart->get_result();
    $cart_data = $result->fetch_assoc();
    $subtotal = $cart_data['subtotal'];
    $stmt_cart->close();
    
    if ($subtotal === null) {
        throw new Exception("Your cart is empty.");
    }

    // Calculate shipping and total
    $shipping_cost = $subtotal > 100 ? 0 : 10;
    $total_amount = $subtotal + $shipping_cost;

    // 2. Create the order record
    $stmt_order = $conn->prepare("
        INSERT INTO orders 
        (user_id, total_amount, status, shipping_address, billing_address, payment_method)
        VALUES (?, ?, 'Pending', ?, ?, 'Cash on Delivery')
    ");
    
    $stmt_order->bind_param("idss", 
        $user_id,
        $total_amount,
        $shipping_address,
        $billing_address
    );
    
    if (!$stmt_order->execute()) {
        throw new Exception("Failed to create order: " . $stmt_order->error);
    }
    
    $order_id = $conn->insert_id;
    $stmt_order->close();
    
    // 3. Transfer cart items to order items
    $stmt_items = $conn->prepare("
        INSERT INTO order_items (order_id, item_id, quantity, price_at_order)
        SELECT ?, ci.item_id, ci.quantity, i.price
        FROM cart_items ci
        JOIN items i ON ci.item_id = i.item_id
        WHERE ci.cart_id = (SELECT cart_id FROM carts WHERE user_id = ?)
    ");
    $stmt_items->bind_param("ii", $order_id, $user_id);
    if (!$stmt_items->execute()) {
        throw new Exception("Failed to add order items: " . $stmt_items->error);
    }
    $stmt_items->close();
    
    // 4. Update stock quantities
    $stmt_stock = $conn->prepare("
        UPDATE items i
        JOIN cart_items ci ON i.item_id = ci.item_id
        SET i.stock = i.stock - ci.quantity
        WHERE ci.cart_id = (SELECT cart_id FROM carts WHERE user_id = ?)
    ");
    $stmt_stock->bind_param("i", $user_id);
    if (!$stmt_stock->execute()) {
        throw new Exception("Failed to update stock: " . $stmt_stock->error);
    }
    $stmt_stock->close();
    
    // 5. Clear the cart
    $stmt_clear = $conn->prepare("
        DELETE FROM cart_items 
        WHERE cart_id = (SELECT cart_id FROM carts WHERE user_id = ?)
    ");
    $stmt_clear->bind_param("i", $user_id);
    if (!$stmt_clear->execute()) {
        throw new Exception("Failed to clear cart: " . $stmt_clear->error);
    }
    $stmt_clear->close();
    
    // Commit transaction
    $conn->commit();
    
    // Redirect to confirmation page
    $_SESSION['success_message'] = "Your order #$order_id has been placed successfully!";
    header('Location: order_confirmation.php?order_id=' . $order_id);
    exit();

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Order processing error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error processing your order: " . $e->getMessage();
    header('Location: checkout_info.php');
    exit();
} finally {
    if ($conn) $conn->close();
}
?>
