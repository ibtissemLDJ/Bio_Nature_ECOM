<?php
session_start(); // Start the session

// Include database connection - Make sure this path is correct relative to add_to_wishlist.php
// Make sure db_connection.php connects to your 'nescare' database
require_once 'db_connection.php';

// Use a separate variable for DB password if db_connection.php doesn't handle it
// $host = "localhost";
// $user = "root";
// $password_db = "";
// $dbname = "nescare";
// $conn = new mysqli($host, $user, $password_db, $dbname);
// if ($conn->connect_error) {
//     error_log("Database Connection failed: " . $conn->connect_error);
//     $_SESSION['error_message'] = "An error occurred while connecting to the database.";
//     // Decide how to handle DB connection failure in this script
//     // For now, it will attempt DB operations which will fail if $conn is null.
//     // You might add checks `if ($conn)` around DB operations.
// }


// --- Get Item ID from URL ---
// This script is typically called via a GET request, passing the item_id in the URL
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

// Validate the item_id
if ($item_id <= 0) {
    $_SESSION['warning_message'] = "Invalid product specified.";
    // Redirect back to the page the user came from
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit(); // Stop script execution
}

// --- Add/Remove Logic based on Login Status ---

// Ensure connection is valid before DB operations
if ($conn) {
    if (isset($_SESSION['user_id'])) {
        // --- User is logged in: Add/Remove from Database 'favorites' table ---
        $user_id = $_SESSION['user_id'];

        // Check if the item is already in the user's favorites
        // Use the correct table name 'favorites'
        $check_sql = "SELECT COUNT(*) FROM favorites WHERE user_id = ? AND item_id = ?";
        $stmt_check = $conn->prepare($check_sql);

        if ($stmt_check) {
            $stmt_check->bind_param("ii", $user_id, $item_id);
            $stmt_check->execute();
            $stmt_check->bind_result($count);
            $stmt_check->fetch();
            $stmt_check->close(); // Close statement after fetching result

            if ($count > 0) {
                // Item is already in favorites, so remove it
                // Use the correct table name 'favorites'
                $delete_sql = "DELETE FROM favorites WHERE user_id = ? AND item_id = ?";
                $stmt_delete = $conn->prepare($delete_sql);
                if ($stmt_delete) {
                    $stmt_delete->bind_param("ii", $user_id, $item_id);
                    $stmt_delete->execute();
                    if ($stmt_delete->affected_rows > 0) {
                        $_SESSION['success_message'] = "Item removed from your wishlist.";
                    } else {
                        // Might happen if the item was removed by another process simultaneously
                        $_SESSION['warning_message'] = "Item was not found in your wishlist.";
                        error_log("Failed to delete favorite (affected_rows = 0) for user " . $user_id . ", item " . $item_id);
                    }
                    $stmt_delete->close();
                } else {
                    $_SESSION['error_message'] = "Database error removing from wishlist.";
                    error_log("Failed to prepare favorite delete statement: " . $conn->error);
                }
            } else {
                // Item is not in favorites, so add it
                // Use the correct table name 'favorites'
                $insert_sql = "INSERT INTO favorites (user_id, item_id) VALUES (?, ?)";
                $stmt_insert = $conn->prepare($insert_sql);
                if ($stmt_insert) {
                    $stmt_insert->bind_param("ii", $user_id, $item_id);
                    $stmt_insert->execute();
                    if ($stmt_insert->affected_rows > 0) {
                        $_SESSION['success_message'] = "Item added to your wishlist.";
                    } else {
                        // This might happen if the item_id doesn't exist in the 'items' table
                        $_SESSION['warning_message'] = "Could not add item to wishlist (product may not exist).";
                        error_log("Failed to insert favorite (affected_rows = 0) for user " . $user_id . ", item " . $item_id . " (Does item exist in 'items'?): " . $conn->error);
                    }
                    $stmt_insert->close();
                } else {
                    $_SESSION['error_message'] = "Database error adding to wishlist.";
                    error_log("Failed to prepare favorite insert statement: " . $conn->error);
                }
            }
        } else {
            $_SESSION['error_message'] = "Database error checking wishlist status.";
            error_log("Failed to prepare favorite check statement: " . $conn->error);
        }

    } else {
        // --- User is NOT logged in: Add/Remove from Session 'wishlist' ---
        // Initialize session wishlist array if it doesn't exist
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }

        // For guest users, fetch item details from the database to store in the session
        // Ensure connection is valid before querying the 'items' table
        if ($conn) {
            // Use the correct table name 'items'
            $details_sql = "SELECT name, price, image_url FROM items WHERE item_id = ?";
            $stmt_details = $conn->prepare($details_sql);
            $item_details = null; // Variable to hold item details

            if ($stmt_details) {
                $stmt_details->bind_param("i", $item_id);
                $stmt_details->execute();
                $details_result = $stmt_details->get_result();

                // Check if item details were found
                if ($details_result && $details_result->num_rows > 0) {
                    $item_details = $details_result->fetch_assoc();
                }
                $details_result->free(); // Free result set
                $stmt_details->close(); // Close statement

                // Now, toggle the item in the session wishlist based on whether details were found
                if ($item_details) {
                    if (isset($_SESSION['wishlist'][$item_id])) {
                        // Item is in session wishlist, so remove it
                        unset($_SESSION['wishlist'][$item_id]);
                        $_SESSION['success_message'] = "Item removed from your wishlist.";
                    } else {
                        // Item is not in session wishlist, so add it with details
                        $_SESSION['wishlist'][$item_id] = [
                            'name' => $item_details['name'],
                            'price' => $item_details['price'],
                            'image_url' => $item_details['image_url']
                        ];
                        $_SESSION['success_message'] = "Item added to your wishlist.";
                    }
                } else {
                    // Item details not found in the database
                    $_SESSION['warning_message'] = "Could not add item to wishlist (product not found).";
                    error_log("Attempted to add non-existent item ID " . $item_id . " to guest wishlist (details fetch failed).");
                }
            } else {
                // Database query for item details failed
                $_SESSION['error_message'] = "Database error fetching item details for wishlist.";
                error_log("DB query failed (add_to_wishlist.php guest fetch item): " . $conn->error);
                // Fallback: if DB failed, maybe just toggle based on ID without details?
                if (isset($_SESSION['wishlist'][$item_id])) {
                    unset($_SESSION['wishlist'][$item_id]);
                    $_SESSION['success_message'] = "Item removed from your wishlist.";
                } else {
                     // Can't add with details, maybe add a placeholder or just fail?
                     // Adding a minimal placeholder might lead to bad display on wishlist page
                     // Let's set a warning and not add the item if details couldn't be fetched.
                      $_SESSION['warning_message'] = "Could not add item to wishlist due to a data error.";
                }
            }
        } else {
            // Database connection failed
            $_SESSION['error_message'] = "Unable to process wishlist request due to a database error.";
             // If DB failed, can only operate on session if it already existed
             if (isset($_SESSION['wishlist'][$item_id])) {
                 unset($_SESSION['wishlist'][$item_id]);
                 $_SESSION['success_message'] = "Item removed from your wishlist (session).";
             } else {
                 // Cannot add if DB is down and details are needed for session
                 $_SESSION['warning_message'] = "Could not add item to wishlist due to a database error.";
             }
        }
    }
} else {
     // Database connection failed
     $_SESSION['error_message'] = "Unable to process wishlist request due to a database error.";
     // If DB is down, also handle the initial invalid item_id check message properly.
     // This case is already handled at the top by the `if ($item_id <= 0)` block.
}


// Close the database connection at the very end if it was opened successfully
if ($conn) {
    $conn->close();
}

// --- Redirect back to the referring page ---
// Use $_SERVER['HTTP_REFERER'] to send the user back to where they came from
// Provide a fallback redirect just in case HTTP_REFERER is not set
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header('Location: ' . $redirect_url);
exit(); // Stop script execution after redirect
?>