<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Order Management';
require_once 'includes/header.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $stmt = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
    $stmt->bind_param("si", $_POST['status'], $_POST['order_id']);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Order status updated successfully!';
    } else {
        $_SESSION['error'] = 'Error updating order status: ' . $stmt->error;
    }
    $stmt->close();
    header("Location: orders.php");
    exit();
}

// Get all orders
$orders = $conn->query("
    SELECT o.*, u.username, u.email 
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC
");

// Get single order details if viewing
if (isset($_GET['view'])) {
    $order_id = $_GET['view'];
    $order = $conn->query("
        SELECT o.*, u.username, u.email 
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = $order_id
    ")->fetch_assoc();
    
    $order_items = $conn->query("
        SELECT oi.*, i.name, i.image_url
        FROM order_items oi
        JOIN items i ON oi.item_id = i.item_id
        WHERE oi.order_id = $order_id
    ");
}
?>

<div class="card">
    <div class="card-header">
        <h5><?= isset($order) ? "Order #{$order['order_id']}" : 'Orders' ?></h5>
    </div>
    <div class="card-body">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($order)): ?>
            <!-- Single Order View -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Order Details</h6>
                    <p><strong>Order ID:</strong> #<?= $order['order_id'] ?></p>
                    <p><strong>Date:</strong> <?= date('M d, Y H:i', strtotime($order['order_date'])) ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge bg-<?= 
                            $order['status'] === 'Delivered' ? 'success' : 
                            ($order['status'] === 'Pending' ? 'warning' : 'primary') 
                        ?>">
                            <?= $order['status'] ?>
                        </span>
                    </p>
                    <p><strong>Total:</strong> $<?= number_format($order['total_amount'], 2) ?></p>
                    <p><strong>Payment Method:</strong> <?= $order['payment_method'] ?></p>
                </div>
                <div class="col-md-6">
                    <h6>Customer Details</h6>
                    <p><strong>Name:</strong> <?= htmlspecialchars($order['username']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
                    <p><strong>Shipping Address:</strong> <?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                    <p><strong>Billing Address:</strong> <?= nl2br(htmlspecialchars($order['billing_address'])) ?></p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Order Items</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $order_items->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if ($item['image_url']): ?>
                                            <img src="../<?= $item['image_url'] ?>" alt="<?= $item['name'] ?>" style="height: 50px;" class="me-2">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($item['name']) ?>
                                    </td>
                                    <td>$<?= number_format($item['price_at_order'], 2) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>$<?= number_format($item['price_at_order'] * $item['quantity'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="mb-4">
                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Update Status</label>
                        <select name="status" class="form-select">
                            <option value="Pending" <?= $order['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Processing" <?= $order['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="Shipped" <?= $order['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="Delivered" <?= $order['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="Cancelled" <?= $order['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>
            
            <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
            
        <?php else: ?>
            <!-- Orders List -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $order['order_id'] ?></td>
                            <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                            <td><?= htmlspecialchars($order['username']) ?></td>
                            <td>$<?= number_format($order['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $order['status'] === 'Delivered' ? 'success' : 
                                    ($order['status'] === 'Pending' ? 'warning' : 'primary') 
                                ?>">
                                    <?= $order['status'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="orders.php?view=<?= $order['order_id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>