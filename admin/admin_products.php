<?php
// admin_products.php
// Included by admin.php
// Expects $conn (database connection) and $action (current administrative action) to be set.

// Ensure database connection is available ($conn)
// This check relies on $conn being set by db_connection.php *before* this file is included.
if (!isset($conn) || !($conn instanceof mysqli)) {
    // If $conn is not set or not a valid MySQLi object, there's a setup issue.
    echo "<div class='message error'>Database connection not available. Please ensure db_connection.php is correctly included.</div>";
    // Prevent further execution of DB-dependent code in this file
    return; // Stop execution of this include file
}

// $action variable is expected to be passed from the including admin.php file
// Example: switch ($action) { case 'products': include 'admin_products.php'; break; ... }


// --- Handle Product Actions (Add, Edit, Delete) ---
// Process POST requests for Add and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                handle_add_product($conn);
                 // After handling, typically redirect or set $action to 'products' to show the list
                 // A redirect is generally better to prevent form resubmission on refresh
                 // header('Location: admin.php?action=products&message=added'); exit();
                break;
            case 'update_product':
                handle_update_product($conn);
                  // After handling, typically redirect or set $action to 'products'
                  // header('Location: admin.php?action=products&message=updated'); exit();
                break;
            // Delete is typically handled via GET request
        }
    }
}
// Process GET requests for Delete (and potentially setting display $action)
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
     if ($_GET['action'] === 'delete_product' && isset($_GET['id'])) {
         handle_delete_product($conn, $_GET['id']);
         // handle_delete_product function performs a redirect
     }
     // GET requests for 'products', 'add_product', 'edit_product'
     // These actions are used by the display switch below.
}


// --- Display Content Based on Action ---
// Assumes $action is set by the including admin.php script
if (isset($action)) {
    switch ($action) {
        case 'products':
            display_product_list($conn);
            break;
        case 'add_product':
            display_add_product_form($conn);
            break;
        case 'edit_product':
            if (isset($_GET['id'])) {
                // Validate ID before attempting to display form
                 $item_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
                 if ($item_id !== false) {
                     display_edit_product_form($conn, $item_id);
                 } else {
                     echo "<div class='message error'>Invalid product ID specified for editing.</div>";
                     display_product_list($conn); // Show list if ID is missing or invalid
                 }
            } else {
                echo "<div class='message error'>Product ID not specified for editing.</div>";
                display_product_list($conn); // Show list if ID is missing
            }
            break;
        // delete_product action is handled above and redirects, so no display case needed here

        // Default case if $action is set but not recognized by this script
        default:
            // This case might be reached if admin.php's switch sends an unknown action here
             echo "<div class='message warning'>Unknown product action specified.</div>";
             display_product_list($conn);
            break;
    }
} else {
    // Fallback if $action was not set at all (e.g., direct access to admin.php without action parameter)
    // Default action is typically to show the list
    display_product_list($conn);
}


// --- Functions for Product Management ---

function display_product_list($conn) {
    echo "<h2>Manage Products</h2>";
    echo "<p><a href='?action=add_product' class='button'>Add New Product</a></p>";

    // Fetch products from the database, joining with categories
    // Use the correct table names 'items' and 'categories' (lowercase)
    $sql = "SELECT i.*, c.name AS category_name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id ORDER BY i.item_id DESC"; // Corrected table names, added ordering
    $result = $conn->query($sql);

    if ($result === FALSE) {
         error_log("Error fetching products: " . $conn->error); // Log detailed error
         echo "<div class='message error'>Error fetching products from database.</div>"; // User-friendly error
         return; // Stop execution of this function
    }

    if ($result->num_rows > 0) {
        echo "<table class='admin-table product-list-table'>"; // Added classes
        echo "<thead><tr><th>ID</th><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>";
        echo "<tbody>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td data-label='ID'>" . htmlspecialchars($row['item_id']) . "</td>"; // Added data-label
            echo "<td data-label='Image'>"; // Added data-label
            if (!empty($row['image_url'])) {
                // Adjust path based on where your images are stored, ensure safety
                echo "<img src='" . htmlspecialchars($row['image_url']) . "' alt='" . htmlspecialchars($row['name']) . "' style='width: 50px; height: auto; object-fit: cover;'>"; // Added object-fit
            } else {
                echo "No Image";
            }
            echo "</td>";
            echo "<td data-label='Name'>" . htmlspecialchars($row['name']) . "</td>"; // Added data-label
            // Display category name, defaulting to 'Uncategorized' if NULL
            echo "<td data-label='Category'>" . htmlspecialchars($row['category_name'] ?? 'Uncategorized') . "</td>"; // Correct column and null coalescing
            // Format price
            echo "<td data-label='Price'>$" . htmlspecialchars(number_format($row['price'], 2)) . "</td>"; // Added data-label and formatting
            echo "<td data-label='Stock'>" . htmlspecialchars($row['stock']) . "</td>"; // Added data-label
            echo "<td data-label='Actions'>"; // Added data-label
            // Links use the fetched item_id
            echo "<a href='?action=edit_product&id=" . htmlspecialchars($row['item_id']) . "' class='button edit'>Edit</a>"; // Corrected htmlspecialchars
            // Add a confirmation dialog for delete
            echo "<a href='?action=delete_product&id=" . htmlspecialchars($row['item_id']) . "' class='button delete' onclick='return confirm(\"Are you sure you want to delete product ID " . htmlspecialchars($row['item_id']) . " - " . htmlspecialchars($row['name']) . "?\");'>Delete</a>"; // Corrected htmlspecialchars and added name to message
            echo "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No products found.</p>"; // Message when no products are in the DB
    }
    $result->free(); // Free result set
}

function display_add_product_form($conn) {
    echo "<h2>Add New Product</h2>";

    // Fetch categories for the dropdown
    $categories = fetch_categories($conn); // Function uses corrected table name

    // Form action posts back to the same page (admin.php?action=products)
    echo "<form action='' method='post' enctype='multipart/form-data'>"; // Added enctype for potential file uploads later
    echo "<input type='hidden' name='action' value='add_product'>";

    echo "<div class='form-group'>"; // Added form-group divs for styling
    echo "<label for='name'>Product Name:</label>";
    echo "<input type='text' id='name' name='name' required>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='description'>Description:</label>";
    echo "<textarea id='description' name='description'></textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='ingredients'>Ingredients:</label>";
    echo "<textarea id='ingredients' name='ingredients'></textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='how_to_use'>How to Use:</label>";
    echo "<textarea id='how_to_use' name='how_to_use'></textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='shipping_returns_info'>Shipping/Returns Info:</label>";
    echo "<textarea id='shipping_returns_info' name='shipping_returns_info'></textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='price'>Price:</label>";
    echo "<input type='number' id='price' name='price' step='0.01' required>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='stock'>Stock:</label>";
    echo "<input type='number' id='stock' name='stock' required>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='category_id'>Category:</label>";
    echo "<select id='category_id' name='category_id'>";
    // Option for no category (category_id will be NULL in DB)
    echo "<option value=''>-- Select Category --</option>";
    if (!empty($categories)) {
        foreach ($categories as $category) {
            echo "<option value='" . htmlspecialchars($category['category_id']) . "'>" . htmlspecialchars($category['name']) . "</option>";
        }
    }
    echo "</select>";
    echo "</div>";


    // Image URL field - Note about file uploads
    echo "<div class='form-group'>";
    echo "<label for='image_url'>Main Image URL:</label>";
    echo "<input type='text' id='image_url' name='image_url'>";
    echo "<small>Enter a URL or leave empty. For file uploads, you need different form handling.</small>";
    echo "</div>";
     // Note: For file uploads, you'd need <input type='file'> and handle file uploads securely


    echo "<button type='submit' class='button'>Add Product</button>"; // Added button class
    // Optional: Add a back link
    echo "<p><a href='?action=products'>Back to Product List</a></p>";
    echo "</form>";
}

function handle_add_product($conn) {
    // Use prepared statements to prevent SQL injection
    // Using the correct table name 'items' (lowercase)
    $sql = "INSERT INTO items (name, description, ingredients, how_to_use, shipping_returns_info, price, stock, category_id, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"; // Corrected table name

    $stmt = $conn->prepare($sql);

    if ($stmt === FALSE) {
         error_log("Error preparing product insert statement: " . $conn->error); // Log detailed error
         echo "<div class='message error'>An internal error occurred while adding the product.</div>"; // User-friendly error
         return; // Stop function execution
    }

    // Sanitize and get input data
    $name = htmlspecialchars(trim($_POST['name']));
    $description = htmlspecialchars(trim($_POST['description']));
    $ingredients = htmlspecialchars(trim($_POST['ingredients']));
    $how_to_use = htmlspecialchars(trim($_POST['how_to_use']));
    $shipping_returns_info = htmlspecialchars(trim($_POST['shipping_returns_info']));
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'], FILTER_VALIDATE_INT);
    $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT); // Will be false if empty string or not int
    $image_url = htmlspecialchars(trim($_POST['image_url']));

    // Check for valid numeric inputs (required fields should be checked client-side too, but validate server-side)
    if ($price === false || $stock === false || empty($name)) { // Basic check for name too
         echo "<div class='message error'>Invalid input data. Please check Name, Price, and Stock.</div>";
         $stmt->close();
         return;
    }

    // Handle category_id potentially being empty or not an integer, set to NULL if invalid or 0
    $category_id = ($category_id !== false && $category_id > 0) ? $category_id : NULL;


    // Bind parameters (s: string, d: double/float, i: integer) - Note: number of 's' matches the string columns
    // Ensure the types match the database column types (e.g., price is DECIMAL/FLOAT, stock is INT)
    $stmt->bind_param("sssssdiis", $name, $description, $ingredients, $how_to_use, $shipping_returns_info, $price, $stock, $category_id, $image_url);

    if ($stmt->execute()) {
        // Redirect after successful add to prevent form resubmission
        $_SESSION['success_message'] = "New product added successfully!"; // Set success message in session
        header('Location: admin.php?action=products'); // Redirect to show the list
        exit; // Important to exit after header redirect
    } else {
        // Check for potential errors like foreign key constraint (if category_id doesn't exist) or other DB errors
        // MySQL error code 1452 is for foreign key constraint failures
         if ($conn->errno == 1452) {
             echo "<div class='message error'>Error adding product: Invalid category selected.</div>";
         } else {
             error_log("Error executing product insert statement: " . $stmt->error); // Log detailed error
             echo "<div class='message error'>Error adding product. Please try again.</div>"; // User-friendly error
         }
    }

    $stmt->close();
    // If we reach here, it means there was an error and no redirect occurred in the success case.
    // relies on admin.php to handle displaying messages and then potentially showing the list.
}


function display_edit_product_form($conn, $item_id) {
    echo "<h2>Edit Product</h2>";

    // Use prepared statements to fetch the product details to pre-fill the form
    // Using the correct table name 'items' (lowercase)
    $sql = "SELECT * FROM items WHERE item_id = ?"; // Corrected table name
    $stmt = $conn->prepare($sql);
     if ($stmt === FALSE) {
         error_log("Error preparing product edit fetch statement: " . $conn->error);
         echo "<div class='message error'>An internal error occurred while fetching product details.</div>";
         return; // Stop function execution
    }

    // item_id is already validated before this function is called, just bind
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo "<div class='message error'>Product not found.</div>";
        $result->free(); // Free result set
        $stmt->close();
        // Fallback to showing the list
        display_product_list($conn);
        return;
    }

    $product = $result->fetch_assoc();
    $result->free(); // Free result set
    $stmt->close();

    // Fetch categories for the dropdown
    $categories = fetch_categories($conn); // Function uses corrected table name

    // Form action posts back to the same page (admin.php?action=products)
    echo "<form action='' method='post' enctype='multipart/form-data'>"; // Added enctype
    // Hidden input to tell the script which action to handle on POST (update)
    echo "<input type='hidden' name='action' value='update_product'>";
    // Hidden input for the product ID being updated
    echo "<input type='hidden' name='item_id' value='" . htmlspecialchars($product['item_id']) . "'>"; // Keep the product ID

    echo "<div class='form-group'>"; // Added form-group divs
    echo "<label for='name'>Product Name:</label>";
    echo "<input type='text' id='name' name='name' value='" . htmlspecialchars($product['name']) . "' required>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='description'>Description:</label>";
    echo "<textarea id='description' name='description'>" . htmlspecialchars($product['description']) . "</textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='ingredients'>Ingredients:</label>";
    echo "<textarea id='ingredients' name='ingredients'>" . htmlspecialchars($product['ingredients']) . "</textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='how_to_use'>How to Use:</label>";
    echo "<textarea id='how_to_use' name='how_to_use'>" . htmlspecialchars($product['how_to_use']) . "</textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='shipping_returns_info'>Shipping/Returns Info:</label>";
    echo "<textarea id='shipping_returns_info' name='shipping_returns_info'>" . htmlspecialchars($product['shipping_returns_info']) . "</textarea>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='price'>Price:</label>";
    echo "<input type='number' id='price' name='price' step='0.01' value='" . htmlspecialchars($product['price']) . "' required>";
    echo "</div>";

    echo "<div class='form-group'>";
    echo "<label for='stock'>Stock:</label>";
    echo "<input type='number' id='stock' name='stock' value='" . htmlspecialchars($product['stock']) . "' required>";
    echo "</div>";


    echo "<div class='form-group'>";
    echo "<label for='category_id'>Category:</label>";
    echo "<select id='category_id' name='category_id'>";
    // Option for no category (category_id will be NULL in DB)
    echo "<option value=''>-- Select Category --</option>";
    if (!empty($categories)) {
        foreach ($categories as $category) {
            // Select the current category
            $selected = ($product['category_id'] !== NULL && $product['category_id'] == $category['category_id']) ? 'selected' : '';
            echo "<option value='" . htmlspecialchars($category['category_id']) . "' " . $selected . ">" . htmlspecialchars($category['name']) . "</option>";
        }
    }
    echo "</select>";
    echo "</div>";


    // Image URL field - Note about file uploads
    echo "<div class='form-group'>";
    echo "<label for='image_url'>Main Image URL:</label>";
    echo "<input type='text' id='image_url' name='image_url' value='" . htmlspecialchars($product['image_url']) . "'>";
     echo "<small>Enter a URL or leave empty.</small>";
    echo "</div>";


    echo "<button type='submit' class='button'>Update Product</button>"; // Added button class
    // Optional: Add a back link
    echo "<p><a href='?action=products'>Cancel</a></p>";
    echo "</form>";
}


function handle_update_product($conn) {
     // Use prepared statements
    // Using the correct table name 'items' (lowercase)
    $sql = "UPDATE items SET -- Corrected table name
                name = ?,
                description = ?,
                ingredients = ?,
                how_to_use = ?,
                shipping_returns_info = ?,
                price = ?,
                stock = ?,
                category_id = ?,
                image_url = ?
            WHERE item_id = ?";

    $stmt = $conn->prepare($sql);

     if ($stmt === FALSE) {
         error_log("Error preparing product update statement: " . $conn->error);
         echo "<div class='message error'>An internal error occurred while updating the product.</div>";
         // After error, show the list
         display_product_list($conn);
         return; // Stop function execution
    }

    // Sanitize and get input data
    $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
    $name = htmlspecialchars(trim($_POST['name']));
    $description = htmlspecialchars(trim($_POST['description']));
    $ingredients = htmlspecialchars(trim($_POST['ingredients']));
    $how_to_use = htmlspecialchars(trim($_POST['how_to_use']));
    $shipping_returns_info = htmlspecialchars(trim($_POST['shipping_returns_info']));
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'], FILTER_VALIDATE_INT);
    $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
    $image_url = htmlspecialchars(trim($_POST['image_url']));

    // Check for valid inputs (required fields should be checked client-side too, but validate server-side)
    if ($item_id === false || $price === false || $stock === false || empty($name)) { // Basic check for name too
         echo "<div class='message error'>Invalid input data. Please check Product ID, Name, Price, and Stock.</div>";
         $stmt->close();
         // After error, show the list
         display_product_list($conn);
         return;
    }

    // Handle category_id potentially being empty or not an integer, set to NULL if invalid or 0
    $category_id = ($category_id !== false && $category_id > 0) ? $category_id : NULL;


    // Bind parameters (s: string, d: double/float, i: integer) - Order matters!
    // Match the order in the SQL UPDATE statement
    $stmt->bind_param("sssssdiisi", $name, $description, $ingredients, $how_to_use, $shipping_returns_info, $price, $stock, $category_id, $image_url, $item_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Update successful, redirect to prevent form resubmission
            $_SESSION['success_message'] = "Product updated successfully!"; // Set success message in session
            header('Location: admin.php?action=products'); // Redirect to show the list
            exit; // Important to exit after header redirect
        } else {
             // No rows affected, could mean the item ID didn't exist or no changes were made
             // It's safer to check if the item ID existed first if this is critical.
             // For now, assume no changes were made or the item was deleted by another process.
            $_SESSION['info_message'] = "No changes were made to the product or product not found."; // Info message in session
            header('Location: admin.php?action=products'); // Redirect to show the list
            exit; // Important to exit after header redirect
        }
    } else {
        // Check for potential errors like foreign key constraint (if category_id doesn't exist) or other DB errors
        // MySQL error code 1452 is for foreign key constraint failures
         if ($conn->errno == 1452) {
             $_SESSION['error_message'] = "Error updating product: Invalid category selected.";
         } else {
             error_log("Error executing product update statement: " . $stmt->error);
            $_SESSION['error_message'] = "Error updating product. Please try again.";
         }
          // After error, redirect to show the list and the error message set in the session
          header('Location: admin.php?action=products');
          exit; // Important to exit
    }

    $stmt->close();
    // This part will not be reached if redirects are handled correctly above.
}


function handle_delete_product($conn, $item_id) {
     // Use prepared statements
    // Using the correct table name 'items' (lowercase)
    $sql = "DELETE FROM items WHERE item_id = ?"; // Corrected table name
    $stmt = $conn->prepare($sql);

     if ($stmt === FALSE) {
         error_log("Error preparing product delete statement: " . $conn->error);
         $_SESSION['error_message'] = "An internal error occurred while preparing to delete the product."; // Error message in session
         header('Location: admin.php?action=products'); // Redirect on error
         exit; // Important to exit
    }

     // Validate item ID from GET parameter
     $item_id = filter_var($item_id, FILTER_VALIDATE_INT);

     if ($item_id === false) {
         $_SESSION['error_message'] = "Invalid product ID specified for deletion."; // Error message in session
          header('Location: admin.php?action=products'); // Redirect on error
          exit;
     }

    $stmt->bind_param("i", $item_id);

    if ($stmt->execute()) {
         if ($stmt->affected_rows > 0) {
              // Deletion successful, redirect to prevent form resubmission issues on refresh
              $_SESSION['success_message'] = "Product deleted successfully!"; // Set success message in session
              header('Location: admin.php?action=products'); // Redirect to show the list
              exit; // Important to exit after header redirect
         } else {
              // No rows affected, product not found (maybe already deleted)
              $_SESSION['warning_message'] = "Product with ID " . htmlspecialchars($item_id) . " not found or already deleted."; // Warning message in session
         }
    } else {
        // Check for potential errors (e.g., foreign key constraints if product is referenced elsewhere)
        // Deleting a product might affect cart_items, order_items, favorites.
        // Depending on FK constraints (ON DELETE CASCADE or RESTRICT), this might fail.
        // MySQL error code 1451 or 1452 depending on server
         if ($conn->errno == 1451 || $conn->errno == 1452) {
             $_SESSION['error_message'] = "Error deleting product: This product is linked to orders, wishlists, or carts and cannot be deleted directly. Consider deactivating it instead."; // More specific error
         } else {
             error_log("Error executing product delete statement: " . $stmt->error);
            $_SESSION['error_message'] = "Error deleting product. Please try again.";
         }
    }

    $stmt->close();
    // If we reach here, it means there was an error and no redirect occurred in the success case.
    // Redirect to show the list and the error message set in the session.
    header('Location: admin.php?action=products');
    exit; // Important to exit
}

// Helper function to fetch categories
function fetch_categories($conn) {
    $categories = [];
    // Use the correct table name 'categories' (lowercase)
    $sql = "SELECT category_id, name FROM categories ORDER BY name"; // Corrected table name
    $result = $conn->query($sql);

    if ($result === FALSE) {
        error_log("Error fetching categories (for product forms): " . $conn->error); // Log error instead of displaying directly
        // Optionally set a session error message here if categories are critical for the form
        // $_SESSION['error_message'] = "Could not load categories.";
        return []; // Return empty array on error
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    $result->free();
    return $categories;
}

// --- Display Messages (Typically handled in admin.php template) ---
// If admin.php includes this file and handles message display based on $_SESSION
// then this block is not needed here. Messages from redirects are in $_SESSION.
// Messages echoed directly by handlers might appear above session messages.
/*
if (isset($_SESSION['success_message'])) {
     echo "<div class='message success'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
     unset($_SESSION['success_message']);
}
if (isset($_SESSION['warning_message'])) {
     echo "<div class='message warning'>" . htmlspecialchars($_SESSION['warning_message']) . "</div>";
     unset($_SESSION['warning_message']);
}
if (isset($_SESSION['error_message'])) {
     echo "<div class='message error'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
     unset($_SESSION['error_message']);
}
if (isset($_SESSION['info_message'])) { // Added info messages
     echo "<div class='message info'>" . htmlspecialchars($_SESSION['info_message']) . "</div>";
     unset($_SESSION['info_message']);
}
*/

// No $conn->close() needed here as $conn is expected to be managed by admin.php

?>