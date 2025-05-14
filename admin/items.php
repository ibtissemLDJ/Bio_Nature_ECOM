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
        
        // Handle category_id - set to NULL if empty
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        
        // Priority 1: File upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../images/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['product_image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.']);
                exit();
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                // Delete old image if exists and it's a local file
                if (!empty($_POST['existing_image']) && 
                    strpos($_POST['existing_image'], 'http') !== 0 && 
                    file_exists('../' . $_POST['existing_image'])) {
                    @unlink('../' . $_POST['existing_image']);
                }
                $image_url = 'images/products/' . $file_name;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to upload image. Please try again.']);
                exit();
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
                    @unlink('../' . $_POST['existing_image']);
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
                        @unlink('../' . $_POST['existing_image']);
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
            $category_id,
            $image_url,
            $_POST['ingredients'],
            $_POST['how_to_use'],
            $_POST['item_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['message'] = 'Product updated successfully!';
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully!']);
        } else {
            $_SESSION['error'] = 'Error updating product: ' . $stmt->error;
            echo json_encode(['status' => 'error', 'message' => 'Error updating product: ' . $stmt->error]);
        }
        $stmt->close();
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
<link rel="stylesheet" href="css/items.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Bootstrap modals
    var addItemModal = new bootstrap.Modal(document.getElementById('addItemModal'));
    var editItemModal = new bootstrap.Modal(document.getElementById('editItemModal'));

    // Add Product button click handler
    $('[data-target="#addItemModal"]').click(function() {
        addItemModal.show();
    });

    // Function to open edit modal with product data
    $('.edit-btn').click(function() {
        var itemId = $(this).data('id');
        editItemModal.show();
        
        // Fetch product data via AJAX
        $.ajax({
            url: 'get_product.php',
            type: 'GET',
            data: {id: itemId},
            dataType: 'json',
            success: function(response) {
                if(response.status == 'success') {
                    // Populate form fields
                    $('#editItemModal input[name="item_id"]').val(response.data.item_id);
                    $('#editItemModal input[name="name"]').val(response.data.name);
                    $('#editItemModal input[name="price"]').val(response.data.price);
                    $('#editItemModal input[name="stock"]').val(response.data.stock);
                    $('#editItemModal select[name="category_id"]').val(response.data.category_id);
                    $('#editItemModal textarea[name="description"]').val(response.data.description);
                    $('#editItemModal textarea[name="ingredients"]').val(response.data.ingredients);
                    $('#editItemModal textarea[name="how_to_use"]').val(response.data.how_to_use);
                    $('#editItemModal input[name="existing_image"]').val(response.data.image_url);
                    
                    // Display existing image if available
                    if(response.data.image_url) {
                        var imgSrc = (response.data.image_url.indexOf('http') === 0 || response.data.image_url.indexOf('images/') === 0) 
                            ? response.data.image_url 
                            : '../images/' + response.data.image_url.replace('images/', '');
                        $('#editItemModal .image-preview').html(
                            '<img src="' + imgSrc + '" class="img-thumbnail mb-2" style="max-height: 100px; max-width: 100px; object-fit: contain;">'
                        );
                    } else {
                        $('#editItemModal .image-preview').html('');
                    }
                } else {
                    alert('Error loading product data: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error communicating with server: ' + error);
            }
        });
    });

    // Handle edit form submission
    $('#editItemModal form').on('submit', function(e) {
        e.preventDefault();
        
        // Add the update_item parameter to indicate this is an update
        var formData = new FormData(this);
        formData.append('update_item', '1');
        
        $.ajax({
            url: 'items.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Check if the response contains an error message
                if(response.includes('Error')) {
                    alert('Error updating product: ' + response);
                } else {
                    // Reload the page to show updated data
                    window.location.reload();
                }
            },
            error: function(xhr, status, error) {
                alert('Error updating product: ' + error);
            }
        });
    });

    // Close modal buttons
    $('.close, [data-dismiss="modal"]').click(function() {
        addItemModal.hide();
        editItemModal.hide();
    });
});
</script>
<link rel="stylesheet" href="items.css">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>Products</h5>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addItemModal">
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
                            <button class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $item['item_id']; ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <a href="items.php?delete=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
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

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Product Name*</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Price*</label>
                                <input type="number" step="0.01" class="form-control" name="price" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stock Quantity*</label>
                                <input type="number" class="form-control" name="stock" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select class="form-control" name="category_id">
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
                            <div class="form-group">
                                <label class="form-label">Product Image</label>
                                <input type="file" class="form-control" name="product_image" accept="image/*">
                                <small class="text-muted">OR enter image path/URL:</small>
                                <input type="text" class="form-control mt-2" 
                                       name="product_image_url" 
                                       placeholder="images/productX.png or https://example.com/image.jpg">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ingredients</label>
                                <textarea class="form-control" name="ingredients" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">How to Use</label>
                                <textarea class="form-control" name="how_to_use" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description*</label>
                        <textarea class="form-control" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="add_item" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal (Single modal for all edits) -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="item_id" value="">
                <input type="hidden" name="existing_image" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Product Name*</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Price*</label>
                                <input type="number" step="0.01" class="form-control" name="price" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stock Quantity*</label>
                                <input type="number" class="form-control" name="stock" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select class="form-control" name="category_id">
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
                            <div class="form-group">
                                <label class="form-label">Product Image</label>
                                <div class="image-preview mb-2"></div>
                                <input type="file" class="form-control" name="product_image" accept="image/*">
                                <small class="text-muted">OR enter image path/URL:</small>
                                <input type="text" class="form-control mt-2" 
                                       name="product_image_url" 
                                       placeholder="images/productX.png or https://example.com/image.jpg">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ingredients</label>
                                <textarea class="form-control" name="ingredients" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">How to Use</label>
                                <textarea class="form-control" name="how_to_use" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description*</label>
                        <textarea class="form-control" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="update_item" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>