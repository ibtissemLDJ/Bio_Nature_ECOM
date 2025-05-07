<?php
session_start();
require_once 'db_connection.php';

if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Invalid order confirmation request.";
    header('Location: index.php');
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

try {
    // Call your get_order_details procedure
    $stmt = $conn->prepare("CALL get_order_details(?, ?)");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if (!$order) {
        throw new Exception("Order not found or doesn't belong to this user.");
    }
    
    // Get order items
    $stmt->next_result();
    $items_result = $stmt->get_result();
    $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Order confirmation error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error retrieving order details: " . $e->getMessage();
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation | Nescare</title>
    <link rel="stylesheet" href="checkout.css">
    <link rel="stylesheet" href="landing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="confirmation-container">
        <h1>Order Confirmation</h1>
        
        <div class="confirmation-message">
            <i class="fas fa-check-circle"></i>
            <h2>Thank you for your order!</h2>
            <p>Your order #<?php echo htmlspecialchars($order_id); ?> has been received.</p>
        </div>

        <div class="order-details">
            <h3>Order Summary</h3>
            
            <div class="order-info">
                <div>
                    <h4>Order Number</h4>
                    <p>#<?php echo htmlspecialchars($order_id); ?></p>
                </div>
                <div>
                    <h4>Date</h4>
                    <p><?php echo htmlspecialchars($order['order_date']); ?></p>
                </div>
                <div>
                    <h4>Total</h4>
                    <p>$<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                </div>
                <div>
                    <h4>Payment Method</h4>
                    <p><?php echo htmlspecialchars($order['payment_method']); ?></p>
                </div>
            </div>

            <div class="shipping-info">
                <h4>Shipping Address</h4>
                <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            </div>

            <div class="order-items">
                <h4>Items Ordered</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['price_at_order'], 2)); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format($item['item_subtotal'], 2)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="actions">
            <a href="product.php" class="btn">Continue Shopping</a>
            <a href="profile.php?tab=orders" class="btn outline">View All Orders</a>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>