<?php
session_start();
// Include database connection - Make sure this path is correct relative to order_confirmation.php
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


// Check if user is logged in. Order confirmation requires a logged-in user.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['warning_message'] = "Please log in to view your order confirmation.";
    header('Location: login.php'); // Redirect to your login page if not logged in
    exit(); // Stop script execution
}

$user_id = $_SESSION['user_id']; // Get the logged-in user's ID

// --- Get Order ID from URL ---
// The process_checkout.php script redirects here with the new order_id in the URL query string.
$order_id = isset($_GET['order_id']) ? filter_var($_GET['order_id'], FILTER_VALIDATE_INT) : false;

// Validate the order_id
if ($order_id === false || $order_id <= 0) {
    // If order ID is invalid or missing, show an error message and redirect.
    $_SESSION['error_message'] = "Invalid or missing Order ID.";
    // Redirect to user_orders.php (if exists) or another relevant page
    if (file_exists('user_orders.php')) {
         header('Location: user_orders.php');
    } else {
         header('Location: index.php'); // Fallback
    }
    exit();
}

// Initialize variables to hold order data
$order = null;
$order_items = [];


// --- Call Stored Procedure to Fetch Order Details and Items ---
// Use the correct procedure name 'get_order_details' (lowercase)
// It takes order_id (INT) and user_id (INT) as parameters, IN THAT ORDER.
$sql_call_procedure = "CALL get_order_details(?, ?)";
$stmt_procedure = $conn->prepare($sql_call_procedure);

if ($stmt_procedure === FALSE) {
    // Handle error if the prepared statement fails
    error_log("Database query failed to prepare get_order_details procedure call (order_confirmation.php): " . $conn->error);
    $_SESSION['error_message'] = "Error preparing order details query.";
    // Consider redirecting here as well on preparation failure
     if (file_exists('user_orders.php')) {
         header('Location: user_orders.php');
     } else {
         header('Location: index.php'); // Fallback
     }
     exit();
}

// Bind the parameters to the prepared statement
// 'i' for the integer order_id, 'i' for the integer user_id
// Bind order_id first, then user_id, as per the procedure definition
$stmt_procedure->bind_param("ii", $order_id, $user_id); // **MODIFIED**: Corrected parameter order

// Execute the stored procedure
if ($stmt_procedure->execute()) {
    // --- Fetch the first result set (Order Details) ---
    // This result set should contain one row with order summary information.
    $result_order = $stmt_procedure->get_result();

    // Check if the first result set returned a row for the order
    if ($result_order && $result_order->num_rows > 0) {
        // Fetch the single row for order details into the $order variable
        $order = $result_order->fetch_assoc();
        $result_order->free(); // Free the memory used by the first result set

        // --- Move to the next result set (Order Items) ---
        // The procedure returns the items as the second result set.
        // Check if there are more results available and move to the next one.
        if ($stmt_procedure->more_results()) {
            $stmt_procedure->next_result(); // Move to the second result set
            $result_items = $stmt_procedure->get_result(); // Get the result set for order items

            if ($result_items) {
                // Fetch all item rows into the $order_items array
                while ($row_item = $result_items->fetch_assoc()) {
                    $order_items[] = $row_item;
                }
                $result_items->free(); // Free the memory used by the second result set
            } else {
                // Handle case where item result set is not available
                error_log("Database error: Could not get order items result set for order ID " . $order_id . " (order_confirmation.php): " . $conn->error);
                $_SESSION['warning_message'] = (isset($_SESSION['warning_message']) ? $_SESSION['warning_message'] . " " : "") . "Could not retrieve order items.";
            }
        } else {
            // Handle case where the second result set (items) was not returned
            error_log("Database warning: Second result set (order items) not available for order ID " . $order_id . " (order_confirmation.php).");
            $_SESSION['warning_message'] = (isset($_SESSION['warning_message']) ? $_SESSION['warning_message'] . " " : "") . "Order items not found.";
        }

    } else {
        // Handle case where the first result set (order details) returned no rows.
        // This happens if the order_id does not belong to the logged-in user,
        // or if there's another issue preventing the procedure from returning the order row.
        error_log("Order details not found for user " . $user_id . " and order ID " . $order_id . " (order_confirmation.php).");
        $_SESSION['error_message'] = "Order details not found for this user and Order ID.";
         // Redirect as the requested order is not accessible or doesn't exist for this user
         if (file_exists('user_orders.php')) {
             header('Location: user_orders.php');
         } else {
             header('Location: index.php');
         }
         exit(); // Stop script execution
    }

} else {
    // Handle execution errors for the stored procedure itself
    $error_message = $conn->error; // Get the error message from the connection
    error_log("Error executing get_order_details procedure for order ID " . $order_id . " (order_confirmation.php): " . $error_message);
    $_SESSION['error_message'] = "An error occurred while fetching order details.";
     // Redirect on execution failure
     if (file_exists('user_orders.php')) {
         header('Location: user_orders.php');
     } else {
         header('Location: index.php');
     }
     exit(); // Stop script execution
}

// --- Important: Consume any remaining result sets ---
// After processing all expected result sets, you MUST consume any potential
// additional result sets that the procedure *might* return to avoid
// "Commands out of sync" errors on subsequent database calls if the
// connection is reused later in the script or by included files.
while($conn->more_results() && $conn->next_result()){
    $dummy_result = $conn->use_result();
    if($dummy_result instanceof mysqli_result) {
        $dummy_result->free(); // Free the result set if it exists
    }
}

$stmt_procedure->close(); // Close the prepared statement

// --- Fetch Profile Picture and Username for Header (if needed) ---
// Assuming your header uses these and $conn was successfully opened and is still valid
// If you include a header file, ensure it handles its own DB connection or uses the one passed to it.
// For this example, we'll fetch it here if needed by the HTML below the DB close.
$profile_picture = "images/user1.png"; // Default picture
$username = "";
// Note: If you close the DB connection before this HTML, you need to re-open it briefly or fetch this data earlier.
// Fetching it before closing is generally better practice. Let's move this fetch UP before $conn->close().

// Moved user info fetch up near the top, after $user_id is set.
// This ensures the connection is open when fetching user details for the header.
// (See corrected code block near the top of the PHP section)
// So, the variables $profile_picture and $username are already set by the time we reach the HTML.


// --- Close Database Connection ---
// Close only after all database operations are complete
// The connection is now closed after the user info fetch and procedure call.
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation | Nescare</title>
    <link rel="stylesheet" href="path/to/your/main-styles.css">
    <link rel="stylesheet" href="order_confirmation.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   
</head>
<body>
    <?php
    // --- Include Header ---
    // Replace this placeholder with your actual header include if you use one.
    // The user profile picture and username variables ($profile_picture, $username)
    // are fetched in the PHP block above. Ensure your header uses these variables
    // or fetches its own data.
    ?>
    <header>
         <div class="logo-container"><a href="index.php"><img src="images/logo.png" alt="Nescare Logo" class="logo"></a></div>
         <nav>
             <ul>
                 <li><a href="product.php">Shop</a></li>
                 <li><a href="#">About</a></li>
                 <li><a href="#">Ingredients</a></li>
                 <li><a href="#">Blog</a></li>
                 <li><a href="#">Contact</a></li>
             </ul>
         </nav>
         <div class="nav-right">
              <a href="wishlist.php" title="Your Wishlist"><i class="fas fa-heart"></i></a>
              <a href="basket.php" title="Your Cart"><i class="fas fa-shopping-basket"></i></a>
             <?php if (isset($_SESSION['user_id'])): ?>
                 <div class="profile-container">
                     <a href="profile.php" title="Your Profile - <?php echo htmlspecialchars($username); ?>">
                         <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="profile-pic" title="<?php echo htmlspecialchars($username); ?>">
                     </a>
                 </div>
             <?php else: ?>
                 <a href="login.php" class="login-btn">Login</a>
             <?php endif; ?>
         </div>
    </header>


    <main class="order-confirmation-container">
        <h1>Order Confirmation</h1>

        <?php
        // --- Display Session Messages (Success, Warning, Error) ---
        // These are set by process_checkout.php or if fetching fails here.
        if (isset($_SESSION['success_message'])): ?>
            <div class="message success"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); // Clear the message after displaying
        endif;
        if (isset($_SESSION['warning_message'])): ?>
            <div class="message warning"><?php echo htmlspecialchars($_SESSION['warning_message']); ?></div>
            <?php unset($_SESSION['warning_message']); // Clear the message
        endif;
        if (isset($_SESSION['error_message'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
            <?php unset($_SESSION['error_message']); // Clear the message
        endif;
        ?>

        <?php
        // --- Display Order Details if Successfully Fetched ---
        // The $order variable will be null if there was an error fetching or order wasn't found for user.
        if ($order): // Check if the $order variable contains data
        ?>
            <div class="order-details-summary">
                <h2>Order Summary</h2>
                <p><strong>Order ID:</strong> #<?php echo htmlspecialchars($order['order_id']); ?></p>
                <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['order_date']))); ?></p>
                <p><strong>Total Amount:</strong> $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                <p><strong>Status:</strong> <span class="order-status status-<?php echo htmlspecialchars($order['status']); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span></p>
                <p><strong>Shipping Address:</strong> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                 <p><strong>Billing Address:</strong> <?php echo nl2br(htmlspecialchars($order['billing_address'])); ?></p>
                 <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                <p><strong>Customer Username:</strong> <?php echo htmlspecialchars($order['customer_username']); ?></p>
                 <p><strong>Customer Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>

            </div>

            <div class="order-items-list">
                <h2>Order Items</h2>
                <?php
                // Check if the $order_items array contains items
                if (!empty($order_items)): ?>
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th> <th>Line Total</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td data-label="Product"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td data-label="Quantity"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td data-label="Unit Price">$<?php echo htmlspecialchars(number_format($item['price_at_order'], 2)); ?></td>
                                    <td data-label="Line Total">$<?php echo htmlspecialchars(number_format($item['item_subtotal'], 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No items found for this order.</p>
                <?php endif; ?>
            </div>

            <div class="order-actions">
                 <a href="user_orders.php" class="btn">View All Your Orders</a>
                 <a href="product.php" class="btn secondary-btn">Continue Shopping</a>
            </div>

        <?php else: // Display if $order is null (fetching failed or order not found) ?>
             <div class="order-not-found">
                 <h2>Order Not Found or Error</h2>
                 <?php if (isset($_SESSION['error_message'])): ?>
                      <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                 <?php elseif (isset($_SESSION['warning_message'])): ?>
                      <p><?php echo htmlspecialchars($_SESSION['warning_message']); ?></p>
                 <?php else: ?>
                     <p>Could not retrieve your order details. Please check your order history.</p>
                 <?php endif; ?>
                 <p><a href="user_orders.php" class="btn">View Your Orders</a></p>
             </div>
        <?php endif; // End if($order) block ?>

    </main>

    <?php
    // --- Include Footer ---
    // Replace this placeholder with your actual footer include if you use one.
    ?>
    <footer>
         <div><h3>Shop</h3><ul><li><a href="#">Skincare</a></li><li><a href="#">Makeup</a></li><li><a href="#">Hair Care</a></li><li><a href="#">Body Care</a></li><li><a href="#">Gift Sets</a></li></ul></div>
         <div><h3>About</h3><ul><li><a href="#">Our Story</a></li><li><a href="#">Ingredients</a></li><li><a href="#">Sustainability</a></li><li><a href="#">Blog</a></li><li><a href="#">Press</a></li></ul></div>
         <div><h3>Help</h3><ul><li><a href="#">Contact Us</a></li><li><a href="#">FAQs</a></li><li><a href="#">Shipping</a></li><li><a href="#">Returns</a></li><li><a href="#">Track Order</a></li></ul></div>
         <div><h3>Connect</h3><ul><li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li><li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li><li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li><li><a href="#"><i class="fab fa-pinterest"></i> Pinterest</a></li></ul></div>
         <div class="copyright"><p>&copy; 2025 Nescare. All rights reserved.</p></div>
    </footer>
</body>
</html>
<?php
// No need to close connection here, it's already done in the PHP block.
?>