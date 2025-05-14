<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Category Management';
require_once 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        // Add new category
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $_POST['name'], $_POST['description']);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Category added successfully!';
        } else {
            $_SESSION['error'] = 'Error adding category: ' . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['update_category'])) {
        // Update category
        $stmt = $conn->prepare("UPDATE categories SET name=?, description=? WHERE category_id=?");
        $stmt->bind_param("ssi", $_POST['name'], $_POST['description'], $_POST['category_id']);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Category updated successfully!';
        } else {
            $_SESSION['error'] = 'Error updating category: ' . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: categories.php");
    exit();
} elseif (isset($_GET['delete'])) {
    // Delete category
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id=?");
    $stmt->bind_param("i", $_GET['delete']);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Category deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting category: ' . $stmt->error;
    }
    $stmt->close();
    header("Location: categories.php");
    exit();
}

// Get all categories
$categories = $conn->query("SELECT * FROM categories");
?>
<link rel="stylesheet" href="categories.css">
<div class="content-box">
    <div class="content-box-header">
        <h2 class="content-box-title">Categories</h2>
        <button class="btn btn-primary" id="openAddCategoryModal">
            <i class="bi bi-plus"></i> Add Category
        </button>
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
                        <th>Name</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                    <tr>
                        <td><?= $category['category_id'] ?></td>
                        <td><?= htmlspecialchars($category['name']) ?></td>
                        <td><?= htmlspecialchars($category['description']) ?></td>
                        <td><?= date('M d, Y', strtotime($category['created_at'])) ?></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-warning btn-sm edit-category-btn" data-id="<?= $category['category_id'] ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <a href="categories.php?delete=<?= $category['category_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?')">
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

<!-- Add Category Modal -->
<div class="modal-overlay" id="addCategoryModal">
    <div class="modal-container">
        <form method="POST">
            <div class="modal-header">
                <h3 class="modal-title">Add New Category</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Name*</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal (will be shown via JavaScript) -->
<div class="modal-overlay" id="editCategoryModal">
    <div class="modal-container">
        <form method="POST">
            <div class="modal-header">
                <h3 class="modal-title">Edit Category</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="category_id" id="editCategoryId">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" id="editCategoryName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" id="editCategoryDescription" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
                <button type="submit" name="update_category" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// JavaScript to handle modals
document.addEventListener('DOMContentLoaded', function() {
    // Add Category Modal
    const addModal = document.getElementById('addCategoryModal');
    const openAddBtn = document.getElementById('openAddCategoryModal');
    const closeAddBtn = addModal.querySelector('.modal-close');
    const closeAddBtnFooter = addModal.querySelector('.modal-close-btn');
    
    openAddBtn.addEventListener('click', () => {
        addModal.classList.add('active');
    });
    
    [closeAddBtn, closeAddBtnFooter].forEach(btn => {
        btn.addEventListener('click', () => {
            addModal.classList.remove('active');
        });
    });
    
    // Edit Category Modal
    const editModal = document.getElementById('editCategoryModal');
    const closeEditBtn = editModal.querySelector('.modal-close');
    const closeEditBtnFooter = editModal.querySelector('.modal-close-btn');
    
    closeEditBtn.addEventListener('click', () => {
        editModal.classList.remove('active');
    });
    
    closeEditBtnFooter.addEventListener('click', () => {
        editModal.classList.remove('active');
    });
    
    // Handle edit buttons
    document.querySelectorAll('.edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const row = this.closest('tr');
            const name = row.querySelector('td:nth-child(2)').textContent;
            const description = row.querySelector('td:nth-child(3)').textContent;
            
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCategoryDescription').value = description;
            
            editModal.classList.add('active');
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === addModal) {
            addModal.classList.remove('active');
        }
        if (e.target === editModal) {
            editModal.classList.remove('active');
        }
    });
});
</script>