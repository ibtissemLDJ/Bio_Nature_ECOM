<?php
session_start(); // Start the session

// Include database connection - Make sure this path is correct relative to checkout_info.php
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
//     $_SESSION['error_message'] = "An error occurred while connecting to the database. Please try again later.";
//     // Redirect or handle error gracefully
//     header('Location: index.php'); // Or an error page
//     exit();
// }


// --- Check if user is logged in ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['warning_message'] = "Please log in to proceed to checkout.";
    header('Location: login.php'); // Redirect to your login page
    exit();
}
$user_id = $_SESSION['user_id'];

// --- Fetch user details to pre-fill form (if available) ---
// We fetch first_name and last_name from the 'users' table.
// Phone number and address components are not in the 'users' schema,
// so they will remain empty unless fetched from a different source
// or if you add these columns to your 'users' table schema.
$user_full_name = '';
$user_phone = ''; // Not in users table, remains empty unless fetched otherwise
$user_street_address = ''; // Not in users table, remains empty unless fetched otherwise
$user_city = ''; // Not in users table, remains empty unless fetched otherwise
$user_postal_code = ''; // Not in users table, remains empty unless fetched otherwise
$user_country = ''; // Not in users table, remains empty unless fetched otherwise

// Use the correct table name 'users'
$stmt_user_info = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
if ($stmt_user_info) {
    $stmt_user_info->bind_param("i", $user_id);
    $stmt_user_info->execute();
    $result_user_info = $stmt_user_info->get_result();
    if ($user_info = $result_user_info->fetch_assoc()) {
        // Combine first and last name
        $user_full_name = trim($user_info['first_name'] . ' ' . $user_info['last_name']);
        // TODO: If phone, address, city, postal code, country are stored in the Users table
        // or a separate customer profile table, fetch them here to pre-fill the form.
        // Example: if (isset($user_info['phone'])) $user_phone = $user_info['phone'];
        // Example: if (isset($user_info['street_address'])) $user_street_address = $user_info['street_address']; etc.
    } else {
         // User ID from session not found in DB (shouldn't happen often)
         error_log("User ID " . $user_id . " from session not found in users table (checkout_info.php).");
         // Consider logging the user out
         // session_destroy();
         // unset($_SESSION['user_id']);
         // header("Location: login.php");
         // exit();
    }
    $result_user_info->free(); // Free result set
    $stmt_user_info->close();
} else {
    error_log("Database query failed to fetch user info for checkout (checkout_info.php): " . $conn->error);
    // Decide how to handle this error - maybe a message and proceed with empty fields?
    // $_SESSION['error_message'] = "Could not retrieve your profile information.";
}


// --- Fetch cart items for summary display ---
// This logic seems mostly correct, just need to fix table names.
$cart_items = [];
$subtotal = 0;
$shipping_cost = 0;
$total = 0;

// Use the correct table names 'cart_items' and 'items'
$sql = "SELECT ci.item_id, ci.quantity, i.name, i.price, i.image_url FROM cart_items ci JOIN items i ON ci.item_id = i.item_id WHERE ci.user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the cart is empty immediately after fetching results
    if ($result->num_rows === 0) {
         $stmt->close();
         $result->free(); // Free empty result set
         $_SESSION['warning_message'] = "Your cart is empty. Please add items before checking out.";
         header('Location: basket.php');
         exit();
    }

    while ($row = $result->fetch_assoc()) {
         $cart_items[$row['item_id']] = [
             'quantity' => $row['quantity'],
             'name' => $row['name'],
             'price' => $row['price'],
             'image_url' => $row['image_url'] // Image URL is not used in summary but included in query
         ];
         $subtotal += $row['price'] * $row['quantity'];
    }
    $result->free(); // Free result set after fetching all rows
    $stmt->close();
} else {
    error_log("Database query failed to fetch cart items for checkout info (checkout_info.php): " . $conn->error);
    $_SESSION['error_message'] = "Could not load cart for checkout. Please try again.";
    header('Location: basket.php'); // Redirect on severe error
    exit();
}

// Shipping calculation logic - keep as is
$shipping_cost = $subtotal > 100 ? 0 : 10; // Example: Free shipping over $100
$total = $subtotal + $shipping_cost;


// --- Profile picture & user info (for header) ---
// Use the correct table name 'users'
$profile_picture = "images/user1.png"; // Default
$username = ""; // Default username if not fetched
$stmt_header_user = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");
if ($stmt_header_user) {
    $stmt_header_user->bind_param("i", $user_id);
    $stmt_header_user->execute();
    $stmt_header_user->bind_result($profile_picture_db, $username_db);
    if ($stmt_header_user->fetch()) {
        $username = $username_db; // Set username for header
        if (!empty($profile_picture_db) && file_exists($profile_picture_db)) {
            $profile_picture = $profile_picture_db; // Use fetched picture if valid
        } elseif (!empty($profile_picture_db)) {
             error_log("Profile picture file not found: " . $profile_picture_db . " for user_id: " . $user_id . " (checkout_info.php)");
        }
    }
    $stmt_header_user->close();
} else {
     error_log("DB query failed (checkout_info.php header profile): " . $conn->error);
}

// Close the database connection before rendering HTML
// All necessary data has been fetched into PHP variables
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Information | Nescare</title>
    <link rel="stylesheet" href="checkout.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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
         <nav><ul><li><a href="product.php">Shop</a></li><li><a href="#">About</a></li><li><a href="#">Ingredients</a></li><li><a href="#">Blog</a></li><li><a href="#">Contact</a></li></ul></nav>
         <div class="nav-right">
              <a href="wishlist.php" title="Your Wishlist"><i class="fas fa-heart"></i></a>
              <a href="basket.php" title="Your Cart"><i class="fas fa-shopping-basket active"></i></a> <?php if (isset($_SESSION['user_id'])): ?>
                 <div class="profile-container"><a href="profile.php" title="Your Profile - <?php echo htmlspecialchars($username); ?>"><img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="profile-pic" title="<?php echo htmlspecialchars($username); ?>"></a></div>
             <?php else: ?><a href="login.php" class="login-btn">Login</a><?php endif; ?>
         </div>
    </header>

    <main class="checkout-container">
        <h1>Checkout Information</h1>

        <?php
        // --- Display Session Messages (Success, Warning, Error) ---
        // These messages might be set by basket.php or if cart fetching failed above.
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

        <div class="checkout-cart-summary">
            <h3>Order Summary</h3>
            <?php
            // Display cart items fetched from the database
            if (!empty($cart_items)):
                foreach ($cart_items as $item):
                    // Calculate the line item total
                    $line_item_total = $item['price'] * $item['quantity'];
            ?>
                    <div class="summary-item-row">
                        <span><?php echo htmlspecialchars($item['name']); ?> x <?php echo htmlspecialchars($item['quantity']); ?></span>
                        <span>$<?php echo htmlspecialchars(number_format($line_item_total, 2)); ?></span>
                    </div>
            <?php
                endforeach;
            else: // Should not happen if empty cart check redirects, but as a fallback
            ?>
                 <p>Your cart is empty.</p>
            <?php endif; ?>

            <div class="summary-row"><span>Subtotal:</span><span>$<?php echo htmlspecialchars(number_format($subtotal, 2)); ?></span></div>
            <div class="summary-row"><span>Shipping:</span><span><?php echo $shipping_cost == 0 ? 'Free' : '$' . htmlspecialchars(number_format($shipping_cost, 2)); ?></span></div>
            <div class="summary-total"><span>Total:</span><span>$<?php echo htmlspecialchars(number_format($total, 2)); ?></span></div>
        </div>


        <div class="checkout-form">
            <h2>Delivery Information</h2>
            <form action="process_checkout.php" method="post">

                <div class="form-group">
                    <label for="customer_full_name">Customer Full Name:</label>
                    <input type="text" id="customer_full_name" name="customer_full_name" value="<?php echo htmlspecialchars($user_full_name); ?>" required>
                </div>

                <div class="form-group">
                     <label for="phone_number">Phone Number:</label>
                     <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_phone); ?>" required>
                 </div>

                <div class="form-group">
                    <label for="street_address_shipping">Street Address (Shipping):</label>
                    <input type="text" id="street_address_shipping" name="street_address_shipping" value="<?php echo htmlspecialchars($user_street_address); ?>" required>
                </div>

                <div class="form-group">
                    <label for="city_shipping">City (Shipping):</label>
                    <input type="text" id="city_shipping" name="city_shipping" value="<?php echo htmlspecialchars($user_city); ?>" required>
                </div>

                <div class="form-group">
                    <label for="postal_code_shipping">Postal Code (Shipping):</label>
                    <input type="text" id="postal_code_shipping" name="postal_code_shipping" value="<?php echo htmlspecialchars($user_postal_code); ?>" required>
                </div>

                <div class="form-group">
                    <label for="country_shipping">Country (Shipping):</label>
                    <input type="text" id="country_shipping" name="country_shipping" value="<?php echo htmlspecialchars($user_country); ?>" required>
                </div>

                <h2>Billing Information</h2>

                <div class="form-group">
                     <label for="street_address_billing">Street Address (Billing):</label>
                     <input type="text" id="street_address_billing" name="street_address_billing" required> </div>

                 <div class="form-group">
                     <label for="city_billing">City (Billing):</label>
                     <input type="text" id="city_billing" name="city_billing" required> </div>

                 <div class="form-group">
                     <label for="postal_code_billing">Postal Code (Billing):</label>
                     <input type="text" id="postal_code_billing" name="postal_code_billing" required> </div>

                 <div class="form-group">
                     <label for="country_billing">Country (Billing):</label>
                     <input type="text" id="country_billing" name="country_billing" required> </div>

                <h2>Payment Method</h2>
                 <div class="form-group">
                     <label for="payment_method">Select Payment Method:</label>
                     <select id="payment_method" name="payment_method" required>
                         <option value="">-- Select --</option>
                         <option value="Credit Card">Credit Card</option>
                         <option value="PayPal">PayPal</option>
                         </select>
                 </div>

                <button type="submit">Place Order</button> </form>
        </div>

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
         <div class="copyright"><p>&copy; <?php echo date("Y"); ?> Nescare. All rights reserved.</p></div>
    </footer>

    <?php
    // No need to close connection here, it's already done in the PHP block.
    ?>
</body>
</html>