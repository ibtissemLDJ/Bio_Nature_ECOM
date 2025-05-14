<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Customer Management';
require_once 'includes/header.php';

// Handle customer deletion
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->bind_param("i", $_GET['delete']);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Customer deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting customer: ' . $stmt->error;
    }
    $stmt->close();
    header("Location: customers.php");
    exit();
}

// Get all customers
$customers = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

// Get customer details for modal if ID is provided
$customerDetails = null;
if (isset($_GET['view'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_GET['view']);
    $stmt->execute();
    $customerDetails = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get customer orders
    $orders = $conn->query("SELECT * FROM orders WHERE user_id = ".$customerDetails['user_id']." ORDER BY order_date DESC");
}
?>
<link rel="stylesheet" href="customers.css">
<div class="content-box">
    <div class="content-box-header">
        <h2 class="content-box-title">Customers</h2>
    </div>
    <div class="content-box-body">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($customer = $customers->fetch_assoc()): 
                        $status = $customer['is_active'] ? 'active' : 'inactive';
                        $statusClass = $customer['is_banned'] ? 'banned' : $status;
                    ?>
                    <tr>
                        <td><?= $customer['user_id'] ?></td>
                        <td><?= htmlspecialchars($customer['username']) ?></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($statusClass) ?></span></td>
                        <td><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                        <td><?= $customer['updated_at'] ? date('M d, Y', strtotime($customer['updated_at'])) : 'Never' ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="customers.php?view=<?= $customer['user_id'] ?>" class="btn btn-primary btn-sm view-customer-btn">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="customers.php?delete=<?= $customer['user_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this customer?')">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($customerDetails): ?>
<!-- Customer View Modal -->
<div class="customer-modal active" id="customerModal">
    <div class="customer-modal-container">
        <div class="customer-modal-header">
            <h3 class="customer-modal-title">Customer Details</h3>
            <button type="button" class="customer-modal-close">&times;</button>
        </div>
        <div class="customer-modal-body">
            <div class="customer-details-grid">
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Customer ID</span>
                    <div class="customer-detail-value"><?= $customerDetails['user_id'] ?></div>
                </div>
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Username</span>
                    <div class="customer-detail-value"><?= htmlspecialchars($customerDetails['username']) ?></div>
                </div>
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Email</span>
                    <div class="customer-detail-value"><?= htmlspecialchars($customerDetails['email']) ?></div>
                </div>
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Status</span>
                    <div class="customer-detail-value">
                        <span class="status-badge <?= $statusClass ?>"><?= ucfirst($statusClass) ?></span>
                    </div>
                </div>
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Registration Date</span>
                    <div class="customer-detail-value"><?= date('M d, Y H:i', strtotime($customerDetails['created_at'])) ?></div>
                </div>
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Last Login</span>
                    <div class="customer-detail-value"><?= $customerDetails['updated_at'] ? date('M d, Y H:i', strtotime($customerDetails['updated_at'])) : 'Never' ?></div>
                </div>
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Phone</span>
                    <div class="customer-detail-value"><?= $customerDetails['phone'] ?? 'N/A' ?></div>
                </div>
                <div class="customer-detail-group">
                    <span class="customer-detail-label">Address</span>
                    <div class="customer-detail-value"><?= $customerDetails['address'] ?? 'N/A' ?></div>
                </div>
            </div>
            
            <?php if ($orders && $orders->num_rows > 0): ?>
            <div class="customer-orders">
                <h4>Order History</h4>
                <?php while ($order = $orders->fetch_assoc()): 
                    $orderStatus = strtolower($order['status']);
                ?>
                <div class="order-item">
                    <div class="order-header">
                        <div>
                            <strong>Order #<?= $order['order_id'] ?></strong>
                            <span> - <?= date('M d, Y', strtotime($order['order_date'])) ?></span>
                        </div>
                        <span class="order-status status-<?= $orderStatus ?>"><?= ucfirst($orderStatus) ?></span>
                    </div>
                    <div class="order-total">
                        <strong>Total:</strong> $<?= number_format($order['total_amount'], 2) ?>
                    </div>
                    
                    <?php 
                    // Get order products
                    $orderProducts = $conn->query("
                        SELECT op.*, p.name, p.image 
                        FROM order_products op
                        JOIN products p ON op.product_id = p.product_id
                        WHERE op.order_id = ".$order['order_id']
                    );
                    ?>
                    
                    <?php if ($orderProducts && $orderProducts->num_rows > 0): ?>
                    <div class="order-products">
                        <?php while ($product = $orderProducts->fetch_assoc()): ?>
                        <div class="product-item">
                            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                            <div class="product-info">
                                <div><?= htmlspecialchars($product['name']) ?></div>
                                <div>Quantity: <?= $product['quantity'] ?></div>
                                <div>Price: $<?= number_format($product['price'], 2) ?></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="no-orders">
                <p>This customer hasn't placed any orders yet.</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="customer-modal-footer">
            <button type="button" class="btn btn-secondary customer-modal-close-btn">Close</button>
            <?php if ($customerDetails['is_banned']): ?>
                <a href="unban_customer.php?id=<?= $customerDetails['user_id'] ?>" class="btn btn-success">Unban Customer</a>
            <?php else: ?>
                <a href="ban_customer.php?id=<?= $customerDetails['user_id'] ?>" class="btn btn-warning">Ban Customer</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Customer Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking close button
    const closeButtons = document.querySelectorAll('.customer-modal-close, .customer-modal-close-btn');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            window.location.href = 'customers.php';
        });
    });
    
    // Close modal when clicking outside
    const modal = document.querySelector('.customer-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                window.location.href = 'customers.php';
            }
        });
    }
    
    // Handle back button to close modal
    window.addEventListener('popstate', function() {
        if (window.location.href.indexOf('view=') === -1 && document.querySelector('.customer-modal.active')) {
            window.location.href = 'customers.php';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>