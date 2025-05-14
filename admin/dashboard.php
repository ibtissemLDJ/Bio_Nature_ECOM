<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// Get statistics
$totalOrders = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$totalProducts = $conn->query("SELECT COUNT(*) FROM items")->fetch_row()[0];
$totalCustomers = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$pendingOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetch_row()[0];
$recentOrders = $conn->query("
    SELECT o.order_id, u.username, o.order_date, o.total_amount, o.status 
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC LIMIT 5
");

// Calculate percentage changes (example values - replace with your actual calculations)
$ordersChange = 12.5; // Example: 12.5% increase
$productsChange = -3.2; // Example: 3.2% decrease
$customersChange = 8.7; // Example: 8.7% increase
$pendingChange = 5.0; // Example: 5.0% increase
?>
<link rel="stylesheet" href="dashboard.css">
<div class="dashboard-container">
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-card-title">Total Orders</div>
            <div class="stat-card-value"><?= number_format($totalOrders) ?></div>
            <div class="stat-card-change <?= $ordersChange >= 0 ? 'positive' : 'negative' ?>">
                <i class="bi bi-arrow-<?= $ordersChange >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($ordersChange) ?>% <?= $ordersChange >= 0 ? 'increase' : 'decrease' ?>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-card-title">Total Products</div>
            <div class="stat-card-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-card-change <?= $productsChange >= 0 ? 'positive' : 'negative' ?>">
                <i class="bi bi-arrow-<?= $productsChange >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($productsChange) ?>% <?= $productsChange >= 0 ? 'increase' : 'decrease' ?>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-card-title">Total Customers</div>
            <div class="stat-card-value"><?= number_format($totalCustomers) ?></div>
            <div class="stat-card-change <?= $customersChange >= 0 ? 'positive' : 'negative' ?>">
                <i class="bi bi-arrow-<?= $customersChange >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($customersChange) ?>% <?= $customersChange >= 0 ? 'increase' : 'decrease' ?>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-card-title">Pending Orders</div>
            <div class="stat-card-value"><?= number_format($pendingOrders) ?></div>
            <div class="stat-card-change <?= $pendingChange >= 0 ? 'positive' : 'negative' ?>">
                <i class="bi bi-arrow-<?= $pendingChange >= 0 ? 'up' : 'down' ?>"></i>
                <?= abs($pendingChange) ?>% <?= $pendingChange >= 0 ? 'increase' : 'decrease' ?>
            </div>
        </div>
    </div>
    
    <div class="content-box">
        <div class="content-box-header">
            <h2 class="content-box-title">Recent Orders</h2>
            <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
        </div>
        <div class="content-box-body">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $recentOrders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $order['order_id'] ?></td>
                        <td><?= htmlspecialchars($order['username']) ?></td>
                        <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                        <td>$<?= number_format($order['total_amount'], 2) ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($order['status']) ?>">
                                <?= $order['status'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="orders.php?view=<?= $order['order_id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>