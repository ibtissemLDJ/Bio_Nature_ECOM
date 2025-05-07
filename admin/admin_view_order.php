<?php
// admin_view_order.php
// This file is typically included by a main admin dashboard file (like admin.php)
// that handles session checks and database connection via db_connection.php.

// Ensure database connection is available ($conn)
// This check relies on $conn being set by the main admin file that includes db_connection.php.
// Line 8 check: Correctly verifies $conn is set and is a mysqli object.
if (!isset($conn) || !($conn instanceof mysqli)) {
   // In a real application, redirect to login or an error page
   die("Database connection not available. Please ensure db_connection.php is correctly included."); // Added more specific message
}

// Check if the user is logged in and has admin privileges (assuming this is handled in admin.php)
// For this file's scope, we assume if it's included, the user is authorized.

// Get the order ID from the URL query string (e.g., admin.php?action=view_order&id=123)
$order_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;

// --- Handle Status Update Action ---
// This block processes the form submission for updating the order status.
// The form submits back to this same page (or the including admin.php with the same action/id).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
   // Get the order ID from the hidden input field in the form
   $posted_order_id = isset($_POST['order_id']) ? filter_var($_POST['order_id'], FILTER_VALIDATE_INT) : false;

   // Validate that the order ID from the URL matches the one from the form
   // Use strict comparison (===) for type and value match after validation
   if ($order_id !== false && $order_id > 0 && $order_id === $posted_order_id) {
      // Proceed to handle the status update using the validated order_id
      handle_update_order_status($conn, $order_id);
   } else {
      // Handle case where order ID is invalid or mismatched between GET and POST
      $_SESSION['admin_message_error'] = "Cannot update status: Invalid or missing Order ID.";
      // Log the error for debugging
      error_log("Admin View Order Error: Attempted status update with invalid or mismatched Order ID. GET ID: " . ($order_id === false ? 'false' : $order_id) . ", POST ID: " . ($posted_order_id === false ? 'false' : $posted_order_id));
      // Redirect back to the view page to show the error message. Use the ID from GET if it was valid.
      // Ensure redirection URL handles cases where $order_id was false
     $redirect_id = ($order_id !== false) ? $order_id : '';
      header('Location: ?action=view_order&id=' . htmlspecialchars($redirect_id)); // htmlspecialchars for safety
      exit; // Stop script execution after redirect
   }
}


// --- Display Order Details or Error ---
// This block checks if a valid order_id was provided and then displays the details.
if ($order_id === false || $order_id <= 0) {
   echo "<div class='message error'>Invalid or missing Order ID.</div>";
   // TODO: Optionally add a meta refresh here to redirect back to the order list after a few seconds
} else {
   // Display the order details using the stored procedure and other fetches
   display_order_details_from_procedure($conn, $order_id);
}


// --- Function to Display Order Details and Status Form using Stored Procedure ---
function display_order_details_from_procedure($conn, $order_id) {
   // Validate order_id again, though it's done before calling this function
   if ($order_id === false || $order_id <= 0) {
       echo "<div class='message error'>Internal error: Invalid Order ID passed to display function.</div>";
       return;
   }

   echo "<h2>Order Details (ID: " . htmlspecialchars($order_id) . ")</h2>";

   // --- Fetch the user_id associated with the order first ---
   // The GetUserOrderDetails stored procedure requires the user_id as an input parameter
   // Use the correct table name 'orders' (lowercase)
   $sql_get_userid = "SELECT user_id FROM orders WHERE order_id = ?"; // Corrected table name
   $stmt_get_userid = $conn->prepare($sql_get_userid);
   if ($stmt_get_userid === FALSE) {
        echo "<div class='message error'>Error preparing user ID statement: " . htmlspecialchars($conn->error) . "</div>";
        error_log("Admin View Order Error: Error preparing user ID statement: " . $conn->error);
        return; // Stop execution of the function
   }
   $stmt_get_userid->bind_param("i", $order_id);
   $stmt_get_userid->execute();
   $result_userid = $stmt_get_userid->get_result();
   $order_user = $result_userid->fetch_assoc();
   $result_userid->free(); // Free result set
   $stmt_get_userid->close();

   // If no order found with that ID, display error
   if (!$order_user) {
        echo "<div class='message error'>Order with ID " . htmlspecialchars($order_id) . " not found.</div>";
        // Log the event for debugging
        error_log("Admin View Order Error: Order with ID " . $order_id . " not found.");
        return; // Stop execution of the function
   }
   $user_id = $order_user['user_id']; // Get the user_id

   $sql_call_procedure = "CALL GetUserOrderDetails(?, ?)"; // Prepare the CALL statement
   $stmt_procedure = $conn->prepare($sql_call_procedure);

   if ($stmt_procedure === FALSE) {
        echo "<div class='message error'>Error preparing stored procedure call: " . htmlspecialchars($conn->error) . "</div>";
        error_log("Admin View Order Error: Error preparing stored procedure call: " . $conn->error);
        return; // Stop execution of the function
   }

   // Bind the user_id and order_id parameters to the procedure call
   $stmt_procedure->bind_param("ii", $user_id, $order_id);

   // Execute the stored procedure
   if (!$stmt_procedure->execute()) {
        // Handle procedure execution errors
        echo "<div class='message error'>Error executing stored procedure: " . htmlspecialchars($stmt_procedure->error) . "</div>";
        error_log("Admin View Order Error: Error executing stored procedure for Order ID " . $order_id . ": " . $stmt_procedure->error);
        $stmt_procedure->close(); // Close the statement
        return; // Stop execution
   }

   -- --- Fetch the first result set (Order Details including phone_number, addresses, payment method) ---
   // The procedure returns the main order details first.
   $result_order = $stmt_procedure->get_result();

   // Check if the first result set returned rows (should be exactly one row for the order)
   if ($result_order->num_rows == 0) {
      echo "<div class='message error'>Order details not found via procedure for ID " . htmlspecialchars($order_id) . ".</div>";
      error_log("Admin View Order Error: Procedure returned no order details for ID " . $order_id);
      $result_order->free(); // Free the result set
      // Consume any remaining result sets from the procedure call to allow closing the statement
      while($stmt_procedure->more_results()){
         if($stmt_procedure->next_result()){
             $dummy_result = $stmt_procedure->get_result();
                if($dummy_result) $dummy_result->free();
         } else { break; } // Exit loop if next_result fails
      }
      $stmt_procedure->close(); // Close the statement
      return; // Stop execution
   }

   $order = $result_order->fetch_assoc(); // Fetch the single row for order details (now includes 'phone_number', addresses, payment method)
   $result_order->free(); // Free the first result set memory


   -- --- Move to the next result set (Order Items) ---
   // After processing the first result set, move to the next one returned by the procedure.
   // You MUST call next_result() and get_result() for ALL expected result sets from a procedure.
   $result_items = false; // Initialize the items result set to false
   if ($stmt_procedure->more_results()) {
      $stmt_procedure->next_result(); // Move to the next result set
      $result_items = $stmt_procedure->get_result(); // Get the result set for order items
   } else {
     // Log if the expected second result set for items is not available
     error_log("Admin View Order Error: Stored procedure did not return expected second result set (items) for Order ID " . $order_id);
 }

   -- Close the statement *after* processing or attempting to process all expected result sets.
   $stmt_procedure->close();


   -- --- Fetch Customer Details Separately ---
   -- We still fetch first_name, last_name, and email from the Users table based on user_id.
   // Use the correct table name 'users' (lowercase)
   $sql_get_customer = "SELECT username, email, first_name, last_name FROM users WHERE user_id = ?"; // Corrected table name
   $stmt_customer = $conn->prepare($sql_get_customer);
   $customer = null; // Initialize customer details array
   if ($stmt_customer === FALSE) {
      echo "<div class='message error'>Error preparing customer statement: " . htmlspecialchars($conn->error) . "</div>";
      error_log("Admin View Order Error: Error preparing customer statement: " . $conn->error);
   } else {
      $stmt_customer->bind_param("i", $user_id);
      $stmt_customer->execute();
      $result_customer = $stmt_customer->get_result();
      if ($result_customer->num_rows > 0) { $customer = $result_customer->fetch_assoc(); } // Fetch customer details if found
      else { error_log("Admin View Order Error: Customer details not found for user ID " . $user_id . " associated with Order ID " . $order_id); } // Log if customer not found
      $result_customer->free(); // Free result set
      $stmt_customer->close(); // Close the statement
   }

   -- --- Fetch Delivery Details Separately ---
   -- This table is separate and not part of the GetUserOrderDetails procedure result sets.
   // Use the correct table name 'deliveries' (lowercase)
   $sql_get_delivery = "SELECT status, delivery_date FROM deliveries WHERE order_id = ?"; // Corrected table name
   $stmt_delivery = $conn->prepare($sql_get_delivery);
   $delivery = null; // Initialize delivery details
   if ($stmt_delivery === FALSE) {
      echo "<div class='message error'>Error preparing delivery statement: " . htmlspecialchars($conn->error) . "</div>";
      error_log("Admin View Order Error: Error preparing delivery statement: " . $conn->error);
   } else {
      $stmt_delivery->bind_param("i", $order_id);
      $stmt_delivery->execute();
      $result_delivery = $stmt_delivery->get_result();
      if ($result_delivery->num_rows > 0) { $delivery = $result_delivery->fetch_assoc(); } // Fetch delivery details if found
      $result_delivery->free(); // Free result set
      $stmt_delivery->close(); // Close the statement
   }


   -- --- Display Order Summary ---
   // This section displays the main order information.
   echo "<div class='order-summary admin-card'>";
   echo "<h3>Order Summary</h3>";
   echo "<p><strong>Order Date:</strong> " . htmlspecialchars(date('Y-m-d H:i', strtotime($order['order_date']))) . "</p>";

   // Display customer's full name and email if customer details were fetched
   if ($customer) {
      echo "<p><strong>Customer:</strong> " . htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name'])) . " (" . htmlspecialchars($customer['email']) . ")</p>";
      echo "<p><strong>Username:</strong> " . htmlspecialchars($customer['username']) . "</p>"; // Display username too
   } else {
      // Fallback if customer details couldn't be fetched
      echo "<p><strong>Customer User ID:</strong> " . htmlspecialchars($user_id) . " (Details not found)</p>";
   }

   // Display the phone number from the order details fetched by the procedure
   // The 'phone_number' key is available in the $order array because the procedure selects it.
   echo "<p><strong>Phone Number:</strong> " . htmlspecialchars($order['phone_number'] ?? 'N/A') . "</p>"; // Use ?? 'N/A' for safety in case the column is null


   echo "<p><strong>Current Status:</strong> <span class='order-status status-" . htmlspecialchars(strtolower(str_replace(' ', '-', $order['status']))) . "'>" . htmlspecialchars(ucfirst($order['status'])) . "</span></p>"; // Added status class formatting
   echo "<p><strong>Total:</strong> $" . htmlspecialchars(number_format($order['total'], 2)) . "</p>";
   // Display the shipping address, converting newlines to <br> tags
   echo "<p><strong>Shipping Address:</strong> " . nl2br(htmlspecialchars($order['shipping_address'] ?? 'N/A')) . "</p>"; // Use ?? 'N/A' for safety
    // Display the billing address
   echo "<p><strong>Billing Address:</strong> " . nl2br(htmlspecialchars($order['billing_address'] ?? 'N/A')) . "</p>"; // Display billing address
    // Display the payment method
   echo "<p><strong>Payment Method:</strong> " . htmlspecialchars($order['payment_method'] ?? 'N/A') . "</p>"; // Display payment method


   // Display delivery details if available
   if ($delivery) {
      echo "<p><strong>Delivery Status:</strong> <span class='delivery-status status-" . htmlspecialchars(strtolower(str_replace(' ', '-', $delivery['status'] ?? 'N/A'))) . "'>" . htmlspecialchars(ucfirst($delivery['status'] ?? 'N/A')) . "</span></p>"; // Added status class formatting
      echo "<p><strong>Delivery Date:</strong> " . htmlspecialchars($delivery['delivery_date'] ? date('Y-m-d H:i', strtotime($delivery['delivery_date'])) : 'N/A') . "</p>";
   } else { echo "<p><strong>Delivery Info:</strong> Not available yet.</p>"; } // Message if no delivery record


   echo "</div>"; // Close order-summary


   -- --- Status Update Form ---
   // This section provides a form for the admin to change the order status.
   echo "<div class='status-update-form admin-card'>";
   echo "<h3>Update Order Status</h3>";
   // The form submits back to this same page (or the main admin.php controller)
   echo "<form action='' method='post'>";
   echo "<input type='hidden' name='action' value='update_order_status'>"; // Hidden field to identify the action
   echo "<input type='hidden' name='order_id' value='" . htmlspecialchars($order['order_id']) . "'>"; // Hidden field for the order ID

   echo "<div class='form-group'>";
   echo "<label for='new_status'>Change Status:</label>";
   echo "<select id='new_status' name='new_status' class='form-control'>";
   // Define the possible order statuses (ensure these match your 'status' ENUM in the 'orders' table)
   $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
   foreach ($statuses as $status_option) {
      // Select the current status in the dropdown
      $selected = ($order['status'] === $status_option) ? 'selected' : '';
      echo "<option value='" . htmlspecialchars($status_option) . "' " . $selected . ">" . htmlspecialchars(ucfirst($status_option)) . "</option>";
   }
   echo "</select>";
   echo "</div>";

   echo "<div class='form-group'>";
   echo "<label for='status_reason'>Reason (Optional):</label>";
   echo "<textarea id='status_reason' name='status_reason' class='form-control'></textarea>";
   echo "</div>";

   echo "<button type='submit' class='button primary-button'>Update Status</button>";
   echo "</div>"; // Close status-update-form


   -- --- Display Order Items ---
   // This section displays the list of items within the order.
   echo "<div class='order-items-list admin-card'>";
   echo "<h3>Order Items</h3>";

   // Check if the second result set (for items) was successfully fetched and has rows
   if ($result_items && $result_items->num_rows > 0) {
      echo "<table class='admin-table order-items-table'>";
      echo "<thead><tr><th>Product Name</th><th>Quantity</th><th>Unit Price</th><th>Line Total</th></tr></thead>";
      echo "<tbody>";
      // Loop through each item in the order
      while($row_item = $result_items->fetch_assoc()) {
         echo "<tr>";
         echo "<td>" . htmlspecialchars($row_item['item_name']) . "</td>"; // Assuming 'item_name' is returned by procedure
         echo "<td>" . htmlspecialchars(number_format($row_item['quantity'], 0)) . "</td>";
         echo "<td>$" . htmlspecialchars(number_format($row_item['unit_price'], 2)) . "</td>"; // Assuming 'unit_price' is returned by procedure
         echo "<td>$" . htmlspecialchars(number_format($row_item['quantity'] * $row_item['unit_price'], 2)) . "</td>";
         echo "</tr>";
      }
      echo "</tbody>";
      echo "</table>";
        $result_items->free(); // Free the result set memory
   } else {
      // Message if no items were found for this order
      echo "<p>No items found for this order.</p>";
   }
   echo "</div>"; // Close order-items-list

   -- --- Back to Order List Link ---
   // Link to return to the main order list page in the admin panel.
   echo "<div class='order-actions' style='margin-top: 20px;'>";
   echo "<a href='?action=orders' class='button secondary-button'>Back to Order List</a>";
   echo "</div>";
}

// --- Function to Handle Status Update ---
// This function is called when the status update form is submitted.
function handle_update_order_status($conn, $order_id) {
   // Validate order_id again, though it should be valid if called from the POST handler
   if ($order_id === false || $order_id <= 0) {
       $_SESSION['admin_message_error'] = "Internal error: Invalid Order ID passed to update handler.";
       header('Location: ?action=orders'); // Redirect to list if internal error
       exit;
   }

   // Get the new status and reason from the form POST data
   $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
   $reason = isset($_POST['status_reason']) ? trim($_POST['status_reason']) : '';
   // Set reason to NULL if it's an empty string after trimming, or if it was never set
   $reason = (empty($reason) && $reason !== '0') ? NULL : htmlspecialchars($reason);


   // Validate that the submitted status is one of the allowed enum values
   // Ensure this list matches the ENUM definition in your 'orders' table
   $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
   if (!in_array($new_status, $allowed_statuses)) {
      $_SESSION['admin_message_error'] = "Invalid status selected: " . htmlspecialchars($new_status); // Show invalid status to admin
      error_log("Admin View Order Error: Invalid status '" . $new_status . "' selected for Order ID " . $order_id);
      // Redirect back to the order view page with the original order ID
      header('Location: ?action=view_order&id=' . $order_id); exit;
   }

   // Fetch current order info (user_id and status) to log history accurately
   // Use the correct table name 'orders' (lowercase)
   $sql_current_info = "SELECT user_id, status FROM orders WHERE order_id = ?"; // Corrected table name
   $stmt_current = $conn->prepare($sql_current_info);
   if ($stmt_current === FALSE) {
      $_SESSION['admin_message_error'] = "Error checking current order status.";
      error_log("Admin View Order Error: Error preparing current status check statement: " . $conn->error);
      header('Location: ?action=view_order&id=' . $order_id); exit;
   }
   $stmt_current->bind_param("i", $order_id);
   $stmt_current->execute();
   $result_current = $stmt_current->get_result();
   $current_order_info = $result_current->fetch_assoc();
   $result_current->free(); // Free result set
   $stmt_current->close();

   // Check if the order exists
   if (!$current_order_info) {
      $_SESSION['admin_message_error'] = "Order with ID " . htmlspecialchars($order_id) . " not found for status update.";
      error_log("Admin View Order Error: Order with ID " . $order_id . " not found for status update.");
      header('Location: ?action=view_order&id=' . $order_id); exit;
   }

   $old_status = $current_order_info['status'];
   $order_user_id = $current_order_info['user_id'];

   // Only update if the status has actually changed
   if ($old_status !== $new_status) {
      // Prepare and execute the UPDATE statement
      // Use the correct table name 'orders' (lowercase)
      $sql_update = "UPDATE orders SET status = ? WHERE order_id = ?"; // Corrected table name
      $stmt_update = $conn->prepare($sql_update);
      if ($stmt_update === FALSE) {
         $_SESSION['admin_message_error'] = "Error preparing status update statement: " . htmlspecialchars($conn->error);
         error_log("Admin View Order Error: Error preparing status update statement: " . $conn->error);
         header('Location: ?action=view_order&id=' . $order_id); exit;
      }

      $stmt_update->bind_param("si", $new_status, $order_id);

      if ($stmt_update->execute()) {
         // Check if any rows were affected by the update
         if ($stmt_update->affected_rows > 0) {
            // If the status changed, log it in Order_History
            // Use the correct table name 'order_history' (lowercase)
            // Assuming admin user ID is available in $_SESSION['admin_user_id']
           $admin_user_id = isset($_SESSION['admin_user_id']) ? $_SESSION['admin_user_id'] : NULL; // Get admin user ID or set to NULL

            $sql_log_history = "INSERT INTO order_history (order_id, user_id, admin_user_id, old_status, new_status, reason) VALUES (?, ?, ?, ?, ?, ?)"; // Added admin_user_id column
            $stmt_log = $conn->prepare($sql_log_history);
            if ($stmt_log) {
               // i: order_id (int), i: user_id (int), i: admin_user_id (int, or NULL), s: old_status (string), s: new_status (string), s: reason (string - can be NULL)
               // Note: For admin_user_id which can be NULL, the bind type is 'i' but passing NULL works.
               $stmt_log->bind_param("iiisss", $order_id, $order_user_id, $admin_user_id, $old_status, $new_status, $reason);
               $stmt_log->execute();
               $stmt_log->close();
            } else { error_log("Admin View Order Error: Error preparing order history log statement: " . $conn->error); }


            $_SESSION['admin_message_success'] = "Order status updated to '" . htmlspecialchars(ucfirst($new_status)) . "' successfully!";

         } else {
            // If affected_rows is 0, it means the status was already the new status
            $_SESSION['admin_message_info'] = "Order status was already '" . htmlspecialchars(ucfirst($new_status)) . "'. No changes made.";
         }
         $stmt_update->close(); // Close the update statement

      } else {
         // Handle execution errors for the update statement
         $_SESSION['admin_message_error'] = "Error updating order status: " . htmlspecialchars($stmt_update->error);
         error_log("Admin View Order Error: Error executing status update statement for Order ID " . $order_id . ": " . $stmt_update->error);
         $stmt_update->close();
      }
   } else {
      // If the submitted status is the same as the current status
      $_SESSION['admin_message_info'] = "Order status is already '" . htmlspecialchars(ucfirst($new_status)) . "'. No changes needed.";
   }

   // Redirect back to the order view page after processing the update
   header('Location: ?action=view_order&id=' . $order_id);
   exit; // Stop script execution after redirect
}

// Note: Database connection $conn should be closed at the very end of the main script (like admin.php)
// that includes this file, after all includes and processing are done.
?>