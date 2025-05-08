<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$pageTitle = 'Product Management';
require_once 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        // Handle image - either file upload or URL/path
        $image_url = '';
        
        // Priority 1: File upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../images/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = uniqid() . '_' . basename($_FILES['product_image']['name']);
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                $image_url = 'images/products/' . $file_name;
            }
        }
        // Priority 2: URL or path
        elseif (!empty($_POST['product_image_url'])) {
            $input_url = trim($_POST['product_image_url']);
            
            // If it's a full URL
            if (filter_var($input_url, FILTER_VALIDATE_URL)) {
                $image_url = $input_url;
            } 
            // If it's a relative path
            else {
                // Sanitize path
                $image_url = preg_replace(['/\.\.\//', '/^\/+/'], '', $input_url); // Remove ../ and leading slashes
                $image_url = 'images/' . ltrim($image_url, 'images/'); // Ensure it starts with images/
            }
        }

        // Add new item
        $stmt = $conn->prepare("INSERT INTO items (name, description, price, stock, category_id, image_url, ingredients, how_to_use) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdiisss", 
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['stock'],
            $_POST['category_id'],
            $image_url,
            $_POST['ingredients'],
            $_POST['how_to_use']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Product added successfully!';
        } else {
            $_SESSION['error'] = 'Error adding product: ' . $stmt->error;
        }
        $stmt->close();
        header("Location: items.php");
        exit();
        
    } elseif (isset($_POST['update_item'])) {
        // Handle file upload for update
        $image_url = $_POST['existing_image'];
        
        // Priority 1: File upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../images/products/';
            $file_name = uniqid() . '_' . basename($_FILES['product_image']['name']);
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                $image_url = 'images/products/' . $file_name;
                // Delete old image if exists and it's a local file
                if (!empty($_POST['existing_image']) && 
                    strpos($_POST['existing_image'], 'http') !== 0 && 
                    file_exists('../' . $_POST['existing_image'])) {
                    unlink('../' . $_POST['existing_image']);
                }
            }
        }
        // Priority 2: URL or path if no file uploaded
        elseif (!empty($_POST['product_image_url'])) {
            $input_url = trim($_POST['product_image_url']);
            
            // If it's a full URL
            if (filter_var($input_url, FILTER_VALIDATE_URL)) {
                $image_url = $input_url;
                // Delete old image if it was a local file
                if (!empty($_POST['existing_image']) && 
                    strpos($_POST['existing_image'], 'http') !== 0 && 
                    file_exists('../' . $_POST['existing_image'])) {
                    unlink('../' . $_POST['existing_image']);
                }
            } 
            // If it's a relative path
            else {
                // Sanitize path
                $new_path = preg_replace(['/\.\.\//', '/^\/+/'], '', $input_url);
                $new_path = 'images/' . ltrim($new_path, 'images/');
                
                // Only update if path changed
                if ($new_path !== $_POST['existing_image']) {
                    $image_url = $new_path;
                    // Delete old image if it was a local file
                    if (!empty($_POST['existing_image']) && 
                        strpos($_POST['existing_image'], 'http') !== 0 && 
                        file_exists('../' . $_POST['existing_image'])) {
                        unlink('../' . $_POST['existing_image']);
                    }
                }
            }
        }

        // Update item
        $stmt = $conn->prepare("UPDATE items SET name=?, description=?, price=?, stock=?, category_id=?, image_url=?, ingredients=?, how_to_use=? WHERE item_id=?");
        $stmt->bind_param("ssdiisssi", 
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['stock'],
            $_POST['category_id'],
            $image_url,
            $_POST['ingredients'],
            $_POST['how_to_use'],
            $_POST['item_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Product updated successfully!';
        } else {
            $_SESSION['error'] = 'Error updating product: ' . $stmt->error;
        }
        $stmt->close();
        header("Location: items.php");
        exit();
    }
} elseif (isset($_GET['delete'])) {
    // Delete item
    $item_id = $_GET['delete'];
    
    // Get image path first
    $item = $conn->query("SELECT image_url FROM items WHERE item_id = $item_id")->fetch_assoc();
    
    $stmt = $conn->prepare("DELETE FROM items WHERE item_id=?");
    $stmt->bind_param("i", $item_id);
    
    if ($stmt->execute()) {
        // Delete the image file if exists and it's a local file
        if (!empty($item['image_url']) && 
            strpos($item['image_url'], 'http') !== 0 && 
            file_exists('../' . $item['image_url'])) {
            unlink('../' . $item['image_url']);
        }
        $_SESSION['message'] = 'Product deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting product: ' . $stmt->error;
    }
    $stmt->close();
    header("Location: items.php");
    exit();
}

// Get all items
$items = $conn->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id");

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM categories");
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>Products</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus"></i> Add Product
        </button>
    </div>
    <div class="card-body">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['message']; ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $item['item_id']; ?></td>
                        <td>
                            <?php if ($item['image_url']): ?>
                                <?php
                                $img_src = (strpos($item['image_url'], 'http') === 0 || strpos($item['image_url'], 'images/') === 0) 
                                    ? $item['image_url'] 
                                    : '../images/' . ltrim($item['image_url'], 'images/');
                                ?>
                                <img src="<?php echo $img_src; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     style="height: 50px; max-width: 80px; object-fit: contain;">
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td><?php echo $item['stock']; ?></td>
                        <td><?php echo $item['category_name'] ?? 'Uncategorized'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editItemModal<?php echo $item['item_id']; ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <a href="items.php?delete=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </td>
                    </tr>

                    <!-- Edit Item Modal -->
                    <div class="modal fade" id="editItemModal<?php echo $item['item_id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Product</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <input type="hidden" name="existing_image" value="<?php echo $item['image_url']; ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Product Name*</label>
                                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Price*</label>
                                                    <input type="number" step="0.01" class="form-control" name="price" value="<?php echo $item['price']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Stock Quantity*</label>
                                                    <input type="number" class="form-control" name="stock" value="<?php echo $item['stock']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Category</label>
                                                    <select class="form-select" name="category_id">
                                                        <option value="">-- Select Category --</option>
                                                        <?php 
                                                        $categories->data_seek(0);
                                                        while ($cat = $categories->fetch_assoc()): ?>
                                                            <option value="<?php echo $cat['category_id']; ?>" <?php echo ($cat['category_id'] == $item['category_id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($cat['name']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Product Image</label>
                                                    <?php if ($item['image_url']): ?>
                                                        <?php
                                                        $img_src = (strpos($item['image_url'], 'http') === 0 || strpos($item['image_url'], 'images/') === 0) 
                                                            ? $item['image_url'] 
                                                            : '../images/' . ltrim($item['image_url'], 'images/');
                                                        ?>
                                                        <img src="<?php echo $img_src; ?>" 
                                                             class="img-thumbnail mb-2" 
                                                             style="max-height: 100px; max-width: 100px; object-fit: contain;">
                                                    <?php endif; ?>
                                                    <input type="file" class="form-control" name="product_image" accept="image/*">
                                                    <small class="text-muted">OR enter image path/URL:</small>
                                                    <input type="text" class="form-control mt-2" 
                                                           name="product_image_url" 
                                                           placeholder="images/productX.png or https://example.com/image.jpg"
                                                           value="<?php echo htmlspecialchars($item['image_url']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Ingredients</label>
                                                    <textarea class="form-control" name="ingredients" rows="2"><?php echo htmlspecialchars($item['ingredients']); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">How to Use</label>
                                                    <textarea class="form-control" name="how_to_use" rows="2"><?php echo htmlspecialchars($item['how_to_use']); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description*</label>
                                            <textarea class="form-control" name="description" rows="3" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" name="update_item" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Product Name*</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Price*</label>
                                <input type="number" step="0.01" class="form-control" name="price" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity*</label>
                                <input type="number" class="form-control" name="stock" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id">
                                    <option value="">-- Select Category --</option>
                                    <?php 
                                    $categories->data_seek(0);
                                    while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Product Image</label>
                                <input type="file" class="form-control" name="product_image" accept="image/*">
                                <small class="text-muted">OR enter image path/URL:</small>
                                <input type="text" class="form-control mt-2" 
                                       name="product_image_url" 
                                       placeholder="images/productX.png or https://example.com/image.jpg">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ingredients</label>
                                <textarea class="form-control" name="ingredients" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">How to Use</label>
                                <textarea class="form-control" name="how_to_use" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description*</label>
                        <textarea class="form-control" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_item" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>