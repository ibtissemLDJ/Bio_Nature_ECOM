<?php
// admin_categories.php
// Included by admin.php
// Expects $conn (database connection) and $action (current administrative action) to be set.

// Ensure database connection is available ($conn)
if (!isset($conn) || $conn->connect_error) {
    // If conn is not set or connection failed, display an error message.
    // Note: If included by admin.php which already checks $conn, this might be redundant but safe.
    echo "<div class='message error'>Database connection not available. Please check your connection settings.</div>";
    // Prevent further execution of DB-dependent code in this file
    return; // Stop execution of this include file
}

// $action variable is expected to be passed from the including admin.php file
// Example: switch ($action) { case 'categories': include 'admin_categories.php'; break; ... }


// --- Handle Category Actions (Add, Edit, Delete) ---
// Process POST requests for Add and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                handle_add_category($conn);
                // After handling, typically redirect or set $action to 'categories' to show the list
                // For simplicity here, we'll just let it fall through to the display switch
                // and handle display logic within the POST handler or rely on a later redirect.
                // A better approach is often to redirect after successful POST.
                break;
            case 'update_category':
                handle_update_category($conn);
                 // After handling, typically redirect or set $action to 'categories'
                break;
            // Delete is typically handled via GET request for simplicity (less secure but common for simple actions)
        }
    }
}
// Process GET requests for Delete (and potentially setting display $action)
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
     if ($_GET['action'] === 'delete_category' && isset($_GET['id'])) {
         handle_delete_category($conn, $_GET['id']);
         // handle_delete_category function performs a redirect on success or failure
     }
     // GET requests for 'categories', 'add_category', 'edit_category'
     // These actions are used by the display switch below.
}


// --- Display Content Based on Action ---
// Assumes $action is set by the including admin.php script
if (isset($action)) {
    switch ($action) {
        case 'categories':
            display_category_list($conn);
            break;
        case 'add_category':
            display_add_category_form(); // No database connection needed for just displaying the form
            break;
        case 'edit_category':
            if (isset($_GET['id'])) {
                display_edit_category_form($conn, $_GET['id']);
            } else {
                echo "<div class='message error'>Category ID not specified for editing.</div>";
                // Fallback to displaying the list
                display_category_list($conn);
            }
            break;
        // delete_category action is handled above and redirects, so no display case needed here

        // Default case if $action is set but not recognized by this script
        default:
            // This case might be reached if admin.php's switch sends an unknown action here
            // For safety, maybe show the list or an error
             echo "<div class='message warning'>Unknown category action specified.</div>";
             display_category_list($conn);
            break;
    }
} else {
    // Fallback if $action was not set at all (e.g., direct access to admin.php without action parameter)
    // Default action is typically to show the list
    display_category_list($conn);
}


// --- Functions for Category Management ---

function display_category_list($conn) {
    echo "<h2>Manage Categories</h2>";
    echo "<p><a href='?action=add_category' class='button'>Add New Category</a></p>";

    // Fetch categories from the database
    // Using the correct table name 'categories' (lowercase)
    $sql = "SELECT category_id, name FROM categories ORDER BY name"; // Corrected table name
    $result = $conn->query($sql);

    if ($result === FALSE) {
         echo "<div class='message error'>Error fetching categories: " . $conn->error . "</div>";
         return; // Stop execution of this function
    }

    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead>";
        echo "<tbody>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['category_id']) . "</td>"; // Using the fetched column name
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>";
            // Links use the fetched category_id
            echo "<a href='?action=edit_category&id=" . htmlspecialchars($row['category_id']) . "' class='button edit'>Edit</a>";
            // Add a confirmation dialog for delete
            echo "<a href='?action=delete_category&id=" . htmlspecialchars($row['category_id']) . "' class='button delete' onclick='return confirm(\"Are you sure you want to delete category ID " . htmlspecialchars($row['category_id']) . "? This will set the category_id to NULL for any associated products.\");'>Delete</a>"; // Corrected message
            echo "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>No categories found.</p>";
    }
    $result->free(); // Free result set
}

function display_add_category_form() {
    echo "<h2>Add New Category</h2>";
    // Form action posts back to the same page (admin.php?action=categories)
    echo "<form action='' method='post'>";
    // Hidden input to tell the script which action to handle on POST
    echo "<input type='hidden' name='action' value='add_category'>";

    echo "<div class='form-group'>"; // Added form-group div for potential styling
    echo "<label for='name'>Category Name:</label>";
    echo "<input type='text' id='name' name='name' required>";
    echo "</div>";

    echo "<button type='submit' class='button'>Add Category</button>"; // Added button class
    // Optional: Add a back link
    echo "<p><a href='?action=categories'>Back to Category List</a></p>";
    echo "</form>";
}

function handle_add_category($conn) {
    // Use prepared statements to prevent SQL injection
    // Using the correct table name 'categories' (lowercase) and column name 'name'
    $sql = "INSERT INTO categories (name) VALUES (?)"; // Corrected table name

    $stmt = $conn->prepare($sql);

    if ($stmt === FALSE) {
         error_log("Error preparing category insert statement: " . $conn->error); // Log detailed error
         echo "<div class='message error'>An internal error occurred while adding the category.</div>"; // User-friendly error
         return; // Stop function execution
    }

    // Sanitize and get input data
    // Use FILTER_SANITIZE_STRING or htmlspecialchars + trim
    $name = htmlspecialchars(trim($_POST['name']));
    // Basic validation: Check if name is empty after trimming
    if (empty($name)) {
        echo "<div class='message warning'>Category name cannot be empty.</div>";
        $stmt->close();
        return;
    }


    // Bind parameters (s: string)
    $stmt->bind_param("s", $name);

    if ($stmt->execute()) {
        echo "<div class='message success'>New category added successfully!</div>";
    } else {
        // Check for duplicate entry error (MySQL error code 1062)
        if ($conn->errno == 1062) {
             echo "<div class='message error'>Error adding category: A category with this name already exists.</div>";
        } else {
             error_log("Error executing category insert statement: " . $stmt->error); // Log detailed error
             echo "<div class='message error'>Error adding category. Please try again.</div>"; // User-friendly error
        }
    }

    $stmt->close();
    // After handling add, show the list (or redirect)
    // A redirect is generally better to prevent form resubmission on refresh
     // header('Location: admin.php?action=categories&message=added'); exit();
     // If not redirecting, ensure display_category_list is called later in the admin.php flow
}

function display_edit_category_form($conn, $category_id) {
    echo "<h2>Edit Category</h2>";

    // Use prepared statements to fetch the category details to pre-fill the form
    // Using the correct table name 'categories' (lowercase) and column name 'category_id'
    $sql = "SELECT category_id, name FROM categories WHERE category_id = ?"; // Corrected table name
    $stmt = $conn->prepare($sql);
     if ($stmt === FALSE) {
         error_log("Error preparing category edit fetch statement: " . $conn->error);
         echo "<div class='message error'>An internal error occurred while fetching category details.</div>";
         return; // Stop function execution
    }

    // Validate and bind category ID
    $category_id = filter_var($category_id, FILTER_VALIDATE_INT);
    if ($category_id === false) {
         echo "<div class='message error'>Invalid category ID specified for editing.</div>";
         $stmt->close();
         // Fallback to showing the list
         display_category_list($conn);
         return;
    }
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo "<div class='message error'>Category not found.</div>";
        $result->free(); // Free result set
        $stmt->close();
        // Fallback to showing the list
        display_category_list($conn);
        return;
    }

    $category = $result->fetch_assoc();
    $result->free(); // Free result set
    $stmt->close();

    // Form action posts back to the same page (admin.php?action=categories)
    echo "<form action='' method='post'>";
    // Hidden input to tell the script which action to handle on POST (update)
    echo "<input type='hidden' name='action' value='update_category'>";
    // Hidden input for the category ID being updated
    echo "<input type='hidden' name='category_id' value='" . htmlspecialchars($category['category_id']) . "'>";

    echo "<div class='form-group'>"; // Added form-group div for potential styling
    echo "<label for='name'>Category Name:</label>";
    echo "<input type='text' id='name' name='name' value='" . htmlspecialchars($category['name']) . "' required>";
    echo "</div>";

    echo "<button type='submit' class='button'>Update Category</button>"; // Added button class
    // Optional: Add a back link
    echo "<p><a href='?action=categories'>Cancel</a></p>";
    echo "</form>";
}

function handle_update_category($conn) {
     // Use prepared statements
     // Using the correct table name 'categories' (lowercase) and column name 'category_id'
    $sql = "UPDATE categories SET name = ? WHERE category_id = ?"; // Corrected table name

    $stmt = $conn->prepare($sql);

     if ($stmt === FALSE) {
         error_log("Error preparing category update statement: " . $conn->error);
         echo "<div class='message error'>An internal error occurred while updating the category.</div>";
         // After error, show the list
         display_category_list($conn);
         return; // Stop function execution
    }

    // Sanitize and get input data
    // Validate category ID from the form
    $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
    $name = htmlspecialchars(trim($_POST['name']));

    // Check for valid category ID and non-empty name
    if ($category_id === false) {
         echo "<div class='message error'>Invalid category ID submitted for updating.</div>";
         $stmt->close();
         // After error, show the list
         display_category_list($conn);
         return;
    }
    if (empty($name)) {
         echo "<div class='message warning'>Category name cannot be empty.</div>";
         $stmt->close();
         // After error, show the list
         display_category_list($conn);
         return;
    }

    // Bind parameters (s: string, i: integer)
    $stmt->bind_param("si", $name, $category_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
             // Update successful, redirect to prevent form resubmission
             header('Location: admin.php?action=categories&message=updated&id=' . $category_id); // Pass ID for confirmation message
             exit; // Important to exit after header redirect
        } else {
             // No rows affected, could mean the category ID didn't exist or no changes were made
             // Check if the category exists first? Or assume no changes were intended.
             echo "<div class='message info'>No changes were made to the category.</div>";
        }
    } else {
        // Check for duplicate entry error (MySQL error code 1062)
        if ($conn->errno == 1062) {
             echo "<div class='message error'>Error updating category: A category with this name already exists.</div>";
        } else {
             error_log("Error executing category update statement: " . $stmt->error);
             echo "<div class='message error'>Error updating category. Please try again.</div>";
        }
    }

    $stmt->close();
    // After handling update (if not redirected), show the category list
    display_category_list($conn);
}


function handle_delete_category($conn, $category_id) {
     // Use prepared statements
     // Using the correct table name 'categories' (lowercase) and column name 'category_id'
    $sql = "DELETE FROM categories WHERE category_id = ?"; // Corrected table name
    $stmt = $conn->prepare($sql);

     if ($stmt === FALSE) {
         error_log("Error preparing category delete statement: " . $conn->error);
         $_SESSION['error_message'] = "An internal error occurred while preparing to delete the category.";
         header('Location: admin.php?action=categories'); // Redirect on error
         exit; // Important to exit
    }

     // Validate category ID from GET parameter
     $category_id = filter_var($category_id, FILTER_VALIDATE_INT);

     if ($category_id === false) {
         $_SESSION['error_message'] = "Invalid category ID specified for deletion.";
          header('Location: admin.php?action=categories'); // Redirect on error
          exit;
     }

    $stmt->bind_param("i", $category_id);

    if ($stmt->execute()) {
         if ($stmt->affected_rows > 0) {
              // Deletion successful, redirect to prevent form resubmission issues on refresh
              $_SESSION['success_message'] = "Category deleted successfully!"; // Set success message in session
              header('Location: admin.php?action=categories'); // Redirect to show the list
              exit; // Important to exit after header redirect
         } else {
              // No rows affected, category not found
              $_SESSION['warning_message'] = "Category with ID " . htmlspecialchars($category_id) . " not found.";
         }
    } else {
        // Check for foreign key constraint errors (MySQL error code 1451 or 1452 depending on server)
        if ($conn->errno == 1451 || $conn->errno == 1452) {
             $_SESSION['error_message'] = "Error deleting category: This category is currently assigned to products and cannot be deleted directly. Please update or delete the associated products first.";
        } else {
             error_log("Error executing category delete statement: " . $stmt->error);
             $_SESSION['error_message'] = "Error deleting category. Please try again.";
        }
    }

    $stmt->close();
    // If we reach here, it means there was an error and no redirect occurred in the success case.
    // Redirect to show the list and the error message set in the session.
    header('Location: admin.php?action=categories');
    exit; // Important to exit
}

// --- Display Messages (Typically handled in admin.php template) ---
// If admin.php includes this file and handles message display based on $_SESSION
// then this block is not needed here. If admin.php does NOT handle messages,
// uncomment and use these. Messages from redirects (like delete) are in $_SESSION.
// Messages from non-redirecting handlers (like add/update errors) are echoed directly.
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
// Note: Messages echoed directly by handlers will appear above session messages if uncommented here.
*/

// No $conn->close() needed here as $conn is expected to be managed by admin.php

?>