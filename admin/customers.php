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
?>

<div class="card">
    <div class="card-header">
        <h5>Customers</h5>
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
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($customer = $customers->fetch_assoc()): ?>
                    <tr>
                        <td><?= $customer['user_id'] ?></td>
                        <td><?= htmlspecialchars($customer['username']) ?></td>
                        <td><?= htmlspecialchars($customer['email']) ?></td>
                        <td><?= date('M d, Y', strtotime($customer['created_at'])) ?></td>
                        <td><?= $customer['updated_at'] ? date('M d, Y', strtotime($customer['updated_at'])) : 'Never' ?></td>
                        <td>
                            <a href="#" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <a href="customers.php?delete=<?= $customer['user_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this customer?')">
                                <i class="bi bi-trash"></i> Delete
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