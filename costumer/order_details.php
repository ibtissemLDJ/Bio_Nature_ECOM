<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['warning_message'] = "Please log in to view order details.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    $_SESSION['error_message'] = "No order specified.";
    header("Location: profile.php");
    exit();
}

$order_id = intval($_GET['order_id']);

// First, verify the order belongs to the user and get basic order info
$stmt = $conn->prepare("
    SELECT o.* 
    FROM orders o
    WHERE o.order_id = ? AND o.user_id = ?
");

if ($stmt) {
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_details = $result->fetch_assoc();
    
    if (!$order_details) {
        $_SESSION['error_message'] = "Order not found or you don't have permission to view it.";
        header("Location: profile.php");
        exit();
    }
    $stmt->close();
}

// Now fetch the order items with accurate calculations
$stmt = $conn->prepare("
    SELECT 
        oi.order_item_id,
        oi.item_id,
        oi.quantity,
        oi.price_at_order,
        i.name,
        i.image_url,
        (oi.quantity * oi.price_at_order) as item_subtotal
    FROM order_items oi
    JOIN items i ON oi.item_id = i.item_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id ASC
");

if ($stmt) {
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $order_items = [];
    $total_amount = 0;
    
    while ($row = $result->fetch_assoc()) {
        $order_items[] = [
            'order_item_id' => $row['order_item_id'],
            'item_id' => $row['item_id'],
            'quantity' => $row['quantity'],
            'price_at_order' => $row['price_at_order'],
            'name' => $row['name'],
            'image_url' => $row['image_url'],
            'item_subtotal' => $row['item_subtotal']
        ];
        $total_amount += $row['item_subtotal'];
    }
    
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | Nescare</title>
    <link rel="stylesheet" href="profiles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .order-details-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .order-summary {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .order-summary h2 {
            font-size: 24px;
            color: #2d3436;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-item label {
            display: block;
            font-size: 14px;
            color: #636e72;
            margin-bottom: 5px;
        }

        .info-item span {
            font-size: 16px;
            color: #2d3436;
            font-weight: 500;
        }

        .order-items {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
        }

        .item-details {
            flex-grow: 1;
        }

        .item-name {
            font-size: 18px;
            color: #2d3436;
            margin-bottom: 5px;
        }

        .item-price {
            color: #4CAF50;
            font-weight: 500;
        }

        .item-quantity {
            color: #636e72;
            font-size: 14px;
        }

        .order-total {
            text-align: right;
            padding: 20px;
            font-size: 20px;
            font-weight: 600;
            color: #2d3436;
        }

        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-processing { background-color: #cce5ff; color: #004085; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php require_once 'header.php' ?>

    <main class="order-details-container">
        <div class="order-summary">
            <h2>Order #<?php echo htmlspecialchars($order_details['order_id']); ?></h2>
            
            <div class="order-info">
                <div class="info-item">
                    <label>Order Date</label>
                    <span><?php echo htmlspecialchars(date('F j, Y', strtotime($order_details['order_date']))); ?></span>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($order_details['status'])); ?>">
                        <?php echo htmlspecialchars($order_details['status']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Total Amount</label>
                    <span>$<?php echo htmlspecialchars(number_format($total_amount, 2)); ?></span>
                </div>
                <div class="info-item">
                    <label>Shipping Address</label>
                    <span><?php echo htmlspecialchars($order_details['shipping_address']); ?></span>
                </div>
            </div>
        </div>

        <div class="order-items">
            <h3>Order Items</h3>
            <?php if (empty($order_items)): ?>
                <p>No items found in this order.</p>
            <?php else: ?>
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'images/default.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="item-image">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-price">$<?php echo htmlspecialchars(number_format($item['price_at_order'], 2)); ?></div>
                            <div class="item-quantity">Quantity: <?php echo htmlspecialchars($item['quantity']); ?></div>
                        </div>
                        <div class="item-subtotal">
                            $<?php echo htmlspecialchars(number_format($item['item_subtotal'], 2)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="order-total">
                    Total: $<?php echo htmlspecialchars(number_format($total_amount, 2)); ?>
                </div>
            <?php endif; ?>
        </div>

        <a href="profile.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
    </main>

    <?php require_once 'footer.php' ?>
</body>
</html> 