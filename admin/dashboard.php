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
?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h5 class="card-title">Total Orders</h5>
                <h2 class="card-text"><?= $totalOrders ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <h5 class="card-title">Total Products</h5>
                <h2 class="card-text"><?= $totalProducts ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-info h-100">
            <div class="card-body">
                <h5 class="card-title">Total Customers</h5>
                <h2 class="card-text"><?= $totalCustomers ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-warning h-100">
            <div class="card-body">
                <h5 class="card-title">Pending Orders</h5>
                <h2 class="card-text"><?= $pendingOrders ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5>Recent Orders</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
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
                            <span class="badge bg-<?= 
                                $order['status'] === 'Delivered' ? 'success' : 
                                ($order['status'] === 'Pending' ? 'warning' : 'primary') 
                            ?>">
                                <?= $order['status'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="orders.php?view=<?= $order['order_id'] ?>" class="btn btn-sm btn-outline-primary">
                                View
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