<?php
session_start();
// Include database connection - Make sure this path is correct relative to process_checkout.php
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
//     die("An error occurred while connecting to the database. Please try again later.");
// }


// Check if user is logged in. The checkout process requires a logged-in user based on the stored procedure.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['warning_message'] = "Please log in to proceed.";
    header('Location: login.php'); // Redirect to your login page
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Get data from the checkout form fields (submitted via POST from checkout_info.php) ---

// Get individual address components from the form
// Added retrieval for billing address and payment method
$street_address_shipping = isset($_POST['street_address_shipping']) ? trim($_POST['street_address_shipping']) : '';
$city_shipping = isset($_POST['city_shipping']) ? trim($_POST['city_shipping']) : '';
$postal_code_shipping = isset($_POST['postal_code_shipping']) ? trim($_POST['postal_code_shipping']) : '';
$country_shipping = isset($_POST['country_shipping']) ? trim($_POST['country_shipping']) : '';

// Assuming your form sends billing address components, combine them here
// If you have a "Billing address is same as shipping" checkbox, you'd handle that logic in checkout_info.php
// or duplicate the combined shipping address here. For simplicity, we assume separate fields or duplication.
$street_address_billing = isset($_POST['street_address_billing']) ? trim($_POST['street_address_billing']) : '';
$city_billing = isset($_POST['city_billing']) ? trim($_POST['city_billing']) : '';
$postal_code_billing = isset($_POST['postal_code_billing']) ? trim($_POST['postal_code_billing']) : '';
$country_billing = isset($_POST['country_billing']) ? trim($_POST['country_billing']) : '';


// Retrieve payment method from the form
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';


// Get Customer Full Name (for validation, not stored in a separate order column based on current schema)
// $customer_full_name = isset($_POST['customer_full_name']) ? trim($_POST['customer_full_name']) : ''; // Keep if needed for validation

// Get Phone Number (used for validation, but not passed directly to the stored procedure)
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';


// --- Combine address components into single strings for the database columns ---
// Combine shipping address fields
$shipping_address = "";
if (!empty($street_address_shipping)) $shipping_address .= $street_address_shipping;
if (!empty($city_shipping)) $shipping_address .= (empty($shipping_address) ? "" : ", ") . $city_shipping;
if (!empty($postal_code_shipping)) $shipping_address .= (empty($shipping_address) ? "" : ", ") . $postal_code_shipping;
if (!empty($country_shipping)) $shipping_address .= (empty($shipping_address) ? "" : ", ") . $country_shipping;

// Combine billing address fields
$billing_address = "";
if (!empty($street_address_billing)) $billing_address .= $street_address_billing;
if (!empty($city_billing)) $billing_address .= (empty($billing_address) ? "" : ", ") . $city_billing;
if (!empty($postal_code_billing)) $billing_address .= (empty($billing_address) ? "" : ", ") . $postal_code_billing;
if (!empty($country_billing)) $billing_address .= (empty($billing_address) ? "" : ", ") . $country_billing;


// --- Validate required fields ---
// Validate fields that are inputs to the stored procedure OR critical for the order
if ( empty($shipping_address) || empty($billing_address) || empty($payment_method) || empty($phone_number) /* Add $customer_full_name if you want to validate it */ ) {
     $_SESSION['warning_message'] = "Please fill in all required delivery and payment information fields.";
     // Redirect back to the checkout info page, preserving the messages
     header('Location: checkout_info.php');
     exit(); // Stop script execution
}

$stmt = $conn->prepare("CALL finalize_order_from_cart(?, ?, ?, ?)");

if ($stmt) {
    // Bind the parameters to the prepared statement
    // 'i' for the integer user_id, 's' for the string shipping_address, 's' for billing_address, 's' for payment_method
    $stmt->bind_param("isss", $user_id, $shipping_address, $billing_address, $payment_method); // **MODIFIED**: Added billing_address and payment_method binding

    // Execute the prepared statement
    if ($stmt->execute()) {
        // Procedure executed successfully.
        // The procedure returns the new order_id as the first result set (SELECT v_new_order_id AS new_order_id;).
        // Note: In mysqli, after executing a procedure that returns results, you need to get the result set.
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            // Fetch the result containing the new order ID
            $row = $result->fetch_assoc();
            $new_order_id = $row['new_order_id'];

            // Set a success message for the user
            $_SESSION['success_message'] = "Your order has been placed successfully! Order ID: " . htmlspecialchars($new_order_id);

            // Redirect the user to an order confirmation page
            header('Location: order_confirmation.php?order_id=' . htmlspecialchars($new_order_id)); // Pass the new order ID
            exit(); // Stop script execution after redirect

        } else {
             // This might happen if the procedure completed without error but didn't return the order_id as expected
             // (e.g., if the SELECT v_new_order_id was commented out or failed silently in the SP).
             // Or, less likely, if get_result() failed.
             error_log("FinalizeOrderFromCart procedure executed but returned no order ID for user " . $user_id . " (process_checkout.php).");
             $_SESSION['error_message'] = "Order placed, but could not retrieve order confirmation details."; // Or a generic error
             header('Location: basket.php'); // Redirect back to the basket or a generic success page
             exit();
        }

    } else {
        // Handle errors during the execution of the stored procedure
        $error_message = $conn->error; // Get the error message from the connection
        // Log the detailed database error for debugging purposes
        error_log("Error executing finalize_order_from_cart procedure for user " . $user_id . " (process_checkout.php): " . $error_message);

        // Provide user-friendly feedback based on the error message (e.g., from procedure SIGNALs)
        // Match the messages from your stored procedure's SIGNAL statements
        if (strpos($error_message, 'Cart is empty') !== false) {
             $_SESSION['warning_message'] = "Your cart is empty. No order was placed.";
             header('Location: basket.php'); // Redirect to basket if cart is empty
             exit();
        } elseif (strpos($error_message, 'Insufficient stock') !== false) {
             // This message comes from your database trigger BeforeOrderItemInsert
             $_SESSION['warning_message'] = "Some items in your cart are out of stock. Please review your cart quantities.";
             header('Location: checkout_info.php'); // Redirect back to checkout info page
             exit();
        } else {
             // Generic error message for other database issues (permissions, syntax, etc.)
             $_SESSION['error_message'] = "An error occurred while finalizing your order. Please try again.";
             header('Location: checkout_info.php'); // Redirect back to checkout info page
             exit();
        }
    }

    // --- Important: Fetch all results from a procedure call ---
    // When using mysqli, you must fetch all potential result sets and free them
    // to avoid "Commands out of sync" errors on subsequent queries.
    // Your procedure returns the order ID as the first result set, but might return
    // other things or just require consumption of subsequent results.
    while($conn->more_results() && $conn->next_result()){
        $dummy_result = $conn->use_result();
        if($dummy_result instanceof mysqli_result) {
            $dummy_result->free(); // Free the result set if it exists
        }
    }
    $stmt->close(); // Close the prepared statement

} else {
    // Handle errors if the prepared statement itself failed (e.g., syntax error in the CALL statement)
    error_log("Database query failed to prepare finalize_order_from_cart procedure call (process_checkout.php): " . $conn->error);
    $_SESSION['error_message'] = "An internal error occurred during checkout preparation.";
    header('Location: checkout_info.php'); // Redirect back to checkout info page
    exit();
}

$conn->close(); // Close the database connection at the end of the script
?>