<?php
session_start(); // Start the session

// Include database connection
// This file must connect to your 'nescare' database and provide a $conn mysqli object.
// Your db_connection.php uses die() on connection failure, which stops script execution.
// The check below is a safeguard in case db_connection.php changes or doesn't use die().
require_once 'db_connection.php';

// --- Database Connection Check ---
// Ensure the connection from db_connection.php was successful
if (!$conn) {
    // This block will only be reached if db_connection.php didn't die() on failure
    error_log("add_to_cart.php: Database Connection failed after including db_connection.php");
    $_SESSION['error_message'] = "Failed to add item to cart due to a database connection error. Please try again later.";

    // Redirect back to the page the user came from, or index.php as a fallback
    $redirect_url = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header('Location: ' . $redirect_url);
    exit(); // Stop script execution after redirect
}

// Set charset for the connection
if ($conn instanceof mysqli && !$conn->connect_error) {
    $conn->set_charset("utf8mb4");
}


// --- Get item ID and quantity from the request (POST or GET) ---
$item_id = 0;
$quantity = 1; // Default quantity if not provided

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_SANITIZE_NUMBER_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $item_id = filter_input(INPUT_GET, 'item_id', FILTER_SANITIZE_NUMBER_INT);
    $quantity = filter_input(INPUT_GET, 'quantity', FILTER_SANITIZE_NUMBER_INT);
}

$item_id = intval($item_id);
$quantity = intval($quantity);


// --- Validate inputs ---
// Item ID must be positive, quantity must be positive to add
if ($item_id <= 0 || $quantity <= 0) {
    $_SESSION['warning_message'] = "Invalid product or quantity specified.";

    // Redirect back to the page the user came from
    $redirect_url = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
     // Prevent redirection loops
    if (strpos($redirect_url, 'add_to_cart.php') !== false) {
        $redirect_url = 'index.php';
    }
    header('Location: ' . $redirect_url);
    exit();
}

// --- Add Item to Cart (Database for logged-in, Session for guests) ---

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // --- Logged-in User: Update Database 'carts' and 'cart_items' tables ---

    // 1. Find or Create the user's cart in the 'carts' table
    $cart_id = null;

    // Check if user already has a cart
    $find_cart_sql = "SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1";
    $stmt_find_cart = $conn->prepare($find_cart_sql);
    if ($stmt_find_cart) {
        $stmt_find_cart->bind_param("i", $user_id);
        $stmt_find_cart->execute();
        $result_find_cart = $stmt_find_cart->get_result();
        if ($row_cart = $result_find_cart->fetch_assoc()) {
            $cart_id = $row_cart['cart_id'];
        }
        $result_find_cart->free();
        $stmt_find_cart->close();
    } else {
        error_log("add_to_cart.php: Failed to prepare find cart statement: " . $conn->error);
        $_SESSION['error_message'] = "Database error finding your cart.";
        // Redirect and exit on critical error
        $redirect_url = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
        if (strpos($redirect_url, 'add_to_cart.php') !== false) $redirect_url = 'index.php';
        header('Location: ' . $redirect_url);
        exit();
    }

    // If user doesn't have a cart, create one
    if ($cart_id === null) {
        $create_cart_sql = "INSERT INTO carts (user_id) VALUES (?)";
        $stmt_create_cart = $conn->prepare($create_cart_sql);
         if ($stmt_create_cart) {
             $stmt_create_cart->bind_param("i", $user_id);
             if ($stmt_create_cart->execute()) {
                 $cart_id = $conn->insert_id; // Get the ID of the newly created cart
             } else {
                  error_log("add_to_cart.php: Failed to execute create cart: " . $stmt_create_cart->error);
                 $_SESSION['error_message'] = "Database error creating your cart.";
                 // Redirect and exit on critical error
                 $redirect_url = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
                 if (strpos($redirect_url, 'add_to_cart.php') !== false) $redirect_url = 'index.php';
                 header('Location: ' . $redirect_url);
                 exit();
             }
             $stmt_create_cart->close();
         } else {
             error_log("add_to_cart.php: Failed to prepare create cart statement: " . $conn->error);
             $_SESSION['error_message'] = "Database error preparing cart creation.";
              // Redirect and exit on critical error
             $redirect_url = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
             if (strpos($redirect_url, 'add_to_cart.php') !== false) $redirect_url = 'index.php';
             header('Location: ' . $redirect_url);
             exit();
         }
    }

    // At this point, $cart_id should have the correct cart ID for the user.
    // Now, use $cart_id to interact with the 'cart_items' table.

    if ($cart_id !== null) {
        // 2. Check if the item is already in this cart in the 'cart_items' table
        //    Use cart_id instead of user_id here!
        $check_item_sql = "SELECT quantity FROM cart_items WHERE cart_id = ? AND item_id = ?";
        $stmt_check_item = $conn->prepare($check_item_sql);

        if ($stmt_check_item) {
            $stmt_check_item->bind_param("ii", $cart_id, $item_id);
            $stmt_check_item->execute();
            $result_check_item = $stmt_check_item->get_result();

            if ($row_item = $result_check_item->fetch_assoc()) {
                // Item exists in cart_items, update the quantity
                // Use cart_id instead of user_id here!
                $update_item_sql = "UPDATE cart_items SET quantity = quantity + ? WHERE cart_id = ? AND item_id = ?";
                $stmt_update_item = $conn->prepare($update_item_sql);
                if ($stmt_update_item) {
                    $stmt_update_item->bind_param("iii", $quantity, $cart_id, $item_id);
                    if ($stmt_update_item->execute()) {
                        if ($stmt_update_item->affected_rows > 0) {
                            $_SESSION['success_message'] = "Item quantity updated in your cart.";
                        } else {
                            $_SESSION['warning_message'] = "Could not update item quantity in cart (no changes made).";
                            error_log("add_to_cart.php: Update query affected 0 rows for cart " . $cart_id . ", item " . $item_id);
                        }
                    } else {
                         $_SESSION['error_message'] = "Database error updating cart item.";
                         error_log("add_to_cart.php: Failed to execute cart item update: " . $stmt_update_item->error);
                    }
                    $stmt_update_item->close();
                } else {
                    $_SESSION['error_message'] = "Database error preparing item update statement.";
                    error_log("add_to_cart.php: Failed to prepare cart item update statement: " . $conn->error);
                }
            } else {
                // Item does not exist in cart_items, insert new item
                // Use cart_id instead of user_id here!
                $insert_item_sql = "INSERT INTO cart_items (cart_id, item_id, quantity) VALUES (?, ?, ?)";
                $stmt_insert_item = $conn->prepare($insert_item_sql);
                if ($stmt_insert_item) {
                    $stmt_insert_item->bind_param("iii", $cart_id, $item_id, $quantity);
                    if ($stmt_insert_item->execute()) {
                        if ($stmt_insert_item->affected_rows > 0) {
                             $_SESSION['success_message'] = "Item added to your cart.";
                        } else {
                             $_SESSION['warning_message'] = "Could not add item to cart (insertion failed).";
                             error_log("add_to_cart.php: Insert query affected 0 rows for cart " . $cart_id . ", item " . $item_id);
                        }
                    } else {
                         // This might catch FK constraint violations (e.g., item_id not found in items)
                         $_SESSION['warning_message'] = "Could not add item to cart (product may not exist or data error).";
                         error_log("add_to_cart.php: Failed to execute cart item insert: " . $stmt_insert_item->error . " for cart " . $cart_id . ", item " . $item_id);
                    }
                    $stmt_insert_item->close();
                } else {
                    $_SESSION['error_message'] = "Database error preparing item insert statement.";
                    error_log("add_to_cart.php: Failed to prepare cart item insert statement: " . $conn->error);
                }
            }
            $result_check_item->free(); // Free result set
            $stmt_check_item->close(); // Close statement
        } else {
            $_SESSION['error_message'] = "Database error checking cart item status.";
            error_log("add_to_cart.php: Failed to prepare cart item check statement: " . $conn->error);
        }
    } // else ($cart_id === null) is handled by the critical error redirects above

} else {
    // --- User is NOT logged in: Update Session 'cart' ---
    // This logic remains mostly the same as it doesn't use the DB structure for storage

    // Initialize session cart array if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // For guest users, fetch item details from the database to store in the session cart
    $item_details = null;

    if ($conn) { // Only attempt DB query if connection is valid
        $details_sql = "SELECT name, price, image_url FROM items WHERE item_id = ?";
        $stmt_details = $conn->prepare($details_sql);

        if ($stmt_details) {
            $stmt_details->bind_param("i", $item_id);
            $stmt_details->execute();
            $details_result = $stmt_details->get_result();

            if ($details_result && $details_result->num_rows > 0) {
                $item_details = $details_result->fetch_assoc();
            } else {
                 error_log("add_to_cart.php: Item ID " . $item_id . " not found in 'items' table for guest cart details fetch.");
            }
            if ($details_result) $details_result->free(); // Free result set, check if $details_result is valid
            $stmt_details->close();

        } else {
            error_log("add_to_cart.php: Failed to prepare item details fetch statement: " . $conn->error);
            // Don't set an error message here, the logic below handles missing details.
        }
    } else {
         error_log("add_to_cart.php: DB connection not available for guest item details fetch.");
         // Don't set an error message here, the logic below handles missing details.
    }

    // Now, update/add the item in the session cart based on whether details were found AND item_id is valid
    if ($item_id > 0) {
         if (isset($_SESSION['cart'][$item_id]) && is_array($_SESSION['cart'][$item_id]) && isset($_SESSION['cart'][$item_id]['quantity'])) {
             // Item exists in session, update quantity
             $_SESSION['cart'][$item_id]['quantity'] += $quantity;
             $_SESSION['success_message'] = "Item quantity updated in your session cart.";

             // Optionally update session details if new details were fetched (e.g., price changed)
             if ($item_details) {
                  $_SESSION['cart'][$item_id]['name'] = $item_details['name'];
                  $_SESSION['cart'][$item_id]['price'] = $item_details['price'];
                  $_SESSION['cart'][$item_id]['image_url'] = $item_details['image_url'];
             }

         } elseif ($item_details) { // Item does not exist in session, BUT details were found in DB
             // Add new item to session cart with fetched details
             $_SESSION['cart'][$item_id] = [
                 'quantity' => $quantity,
                 'name' => $item_details['name'],
                 'price' => $item_details['price'],
                 'image_url' => $item_details['image_url']
             ];
             $_SESSION['success_message'] = "Item added to your session cart.";

         } else { // Item does not exist in session, AND details were NOT found in DB (or DB error)
             // Cannot add item to session cart reliably without details.
              $_SESSION['warning_message'] = "Could not add item to cart (product details unavailable or database error).";
         }
    } else {
         // This case should be caught by the initial validation
         $_SESSION['warning_message'] = "Invalid item ID for session cart.";
    }
}

// Close the database connection if it was successfully opened and is a valid object
if ($conn instanceof mysqli && !$conn->connect_error) {
    $conn->close();
    $conn = null;
}

// --- Redirect back to the referring page ---
$redirect_url = isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';

// Prevent redirection loops if HTTP_REFERER is this script itself
if (strpos($redirect_url, 'add_to_cart.php') !== false) {
    $redirect_url = 'index.php';
     // Or maybe redirect to the basket page?
     // $redirect_url = 'basket.php';
}

header('Location: ' . $redirect_url);
exit(); // Stop script execution after redirect
?>