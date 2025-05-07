<?php
session_start();

require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['warning_message'] = "Please log in to proceed to checkout.";
    if ($conn instanceof mysqli && !$conn->connect_error) { $conn->close(); }
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

$user_full_name = '';
$user_phone = '';
$user_street_address = '';
$user_city = '';
$user_postal_code = '';
$user_country = '';

$stmt_user_info = null;
if ($conn instanceof mysqli && !$conn->connect_error) {
    $stmt_user_info = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
}

if ($stmt_user_info) {
    $stmt_user_info->bind_param("i", $user_id);
    $stmt_user_info->execute();
    $result_user_info = $stmt_user_info->get_result();
    if ($user_info = $result_user_info->fetch_assoc()) {
        $user_full_name = trim($user_info['first_name'] . ' ' . $user_info['last_name']);
    } else {
         error_log("User ID " . $user_id . " from session not found in users table (checkout_info.php).");
    }
    $result_user_info->free();
    $stmt_user_info->close();
} else {
    error_log("Database query failed to fetch user info for checkout (checkout_info.php): " . ($conn ? $conn->error : 'No DB connection'));
    $_SESSION['warning_message'] = (isset($_SESSION['warning_message']) ? $_SESSION['warning_message'] . " " : "") . "Could not retrieve your profile information. Please fill in the details manually.";
}

$cart_id = null;
$stmt_find_cart = null;
if ($conn instanceof mysqli && !$conn->connect_error) {
    $find_cart_sql = "SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1";
    $stmt_find_cart = $conn->prepare($find_cart_sql);
}

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
    error_log("checkout_info.php fetch cart_id: Failed to prepare find cart statement: " . ($conn ? $conn->error : 'No DB connection'));
    $_SESSION['error_message'] = "Database error finding your cart. Please try again.";
    if ($conn instanceof mysqli && !$conn->connect_error) { $conn->close(); }
    header('Location: basket.php');
    exit();
}

$cart_items = [];
$subtotal = 0;

$stmt_cart_items = null;
if ($cart_id !== null && $conn instanceof mysqli && !$conn->connect_error) {
    $sql = "SELECT ci.item_id, ci.quantity, i.name, i.price, i.image_url
            FROM cart_items ci
            JOIN items i ON ci.item_id = i.item_id
            WHERE ci.cart_id = ?";
    $stmt_cart_items = $conn->prepare($sql);
}

if ($stmt_cart_items) {
    $stmt_cart_items->bind_param("i", $cart_id);
    $stmt_cart_items->execute();
    $result = $stmt_cart_items->get_result();

    if ($result->num_rows === 0) {
         $_SESSION['warning_message'] = "Your cart is empty. Please add items before checking out.";
         $result->free();
         $stmt_cart_items->close();
         if ($conn instanceof mysqli && !$conn->connect_error) { $conn->close(); }
         header('Location: basket.php');
         exit();
    }

    while ($row = $result->fetch_assoc()) {
         $cart_items[$row['item_id']] = [
             'quantity' => $row['quantity'],
             'name' => $row['name'],
             'price' => $row['price']
             ];
            $subtotal += $row['price'] * $row['quantity'];
    }
    $result->free();
    $stmt_cart_items->close();
} else {
    error_log("Database query failed to fetch cart items for checkout info (checkout_info.php): " . ($conn ? $conn->error : 'No DB connection'));
    $_SESSION['error_message'] = "Could not load cart for checkout. Please try again.";
    if ($conn instanceof mysqli && !$conn->connect_error) { $conn->close(); }
    header('Location: basket.php');
    exit();
}

$shipping_cost = $subtotal > 100 ? 0 : 10;
$total = $subtotal + $shipping_cost;

$profile_picture = "images/user1.png";
$username = "";
$stmt_header_user = null;
if ($conn instanceof mysqli && !$conn->connect_error) {
    $stmt_header_user = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");
}

if ($stmt_header_user) {
    $stmt_header_user->bind_param("i", $user_id);
    $stmt_header_user->execute();
    $stmt_header_user->bind_result($profile_picture_db, $username_db);
    if ($stmt_header_user->fetch()) {
        $username = $username_db;
        if (!empty($profile_picture_db) && file_exists($profile_picture_db)) {
            $profile_picture = $profile_picture_db;
        } elseif (!empty($profile_picture_db)) {
             error_log("Profile picture file not found: " . $profile_picture_db . " for user_id: " . $user_id . " (checkout_info.php)");
        }
    }
    $stmt_header_user->close();
} else {
     error_log("DB query failed (checkout_info.php header profile): " . ($conn ? $conn->error : 'No DB connection'));
}

if ($conn instanceof mysqli && !$conn->connect_error) {
    $conn->close();
    $conn = null;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Information | Nescare</title>
    <link rel="stylesheet" href="checkout.css">
    <link rel="stylesheet" href="landing.css">     
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
         <div class="logo-container">
             <a href="landing.php"><img src="images/logo.png" alt="Nescare Logo" class="logo"></a>
         </div>
         <nav>
              <ul>
                  <li><a href="product.php">Shop</a></li>
                  <li><a href="about.php">About</a></li>
                  <li><a href="ingredients.php">Ingredients</a></li>
                  <li><a href="blog.php">Blog</a></li>
                  <li><a href="contact.php">Contact</a></li>
              </ul>
         </nav>
         <div class="nav-right">
              <a href="wishlist.php" title="Your Wishlist"><i class="fas fa-heart"></i></a>
              <?php if (isset($_SESSION['user_id'])): ?>
                 <a href="basket.php" title="Cart"><i class="fas fa-shopping-basket active"></i></a>
             <?php else: ?>
                 <a href="login.php?redirect=checkout_info.php" title="Login to view Cart"><i class="fas fa-shopping-basket"></i></a>
             <?php endif; ?>

             <?php if (isset($_SESSION['user_id'])): ?>
                  <div class="profile-container-small"> <a href="profile.php" title="Your Profile - <?php echo htmlspecialchars($username); ?>">
                           <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-pic">
                       </a>
                   </div>
              <?php else: ?>
                   <a href="login.php" class="login-btn">Login</a>
              <?php endif; ?>
         </div>

         <?php
         if (isset($_SESSION['success_message'])): ?>
            <div class="message success">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <span class="close-btn">×</span>
            </div>
            <?php unset($_SESSION['success_message']);
         endif;
         if (isset($_SESSION['warning_message'])): ?>
            <div class="message warning">
                <?php echo htmlspecialchars($_SESSION['warning_message']); ?>
                <span class="close-btn">×</span>
            </div>
            <?php unset($_SESSION['warning_message']);
         endif;
         if (isset($_SESSION['error_message'])): ?>
            <div class="message error">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <span class="close-btn">×</span>
            </div>
            <?php unset($_SESSION['error_message']);
         endif;
         if (isset($db_error)): ?>
             <div class="message error">
                <?php echo htmlspecialchars($db_error); ?>
                <span class="close-btn">×</span>
            </div>
          <?php endif; ?>

    </header>

    <main class="checkout-container">
        <h1>Checkout</h1>
        <p>Please provide your shipping and billing information.</p>

        <div class="checkout-content-wrapper">             
            <div class="checkout-form">
                <h2>Delivery Information</h2>
                <form action="process_checkout.php" method="post" id="checkoutForm">

                    <div class="form-group">
                        <label for="customer_full_name">Customer Full Name:</label>
                        <input type="text" id="customer_full_name" name="customer_full_name" value="<?php echo htmlspecialchars($user_full_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number:</label>
                        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_phone); ?>" required>
                    </div>

                    <h3>Shipping Address</h3>

                    <div class="form-group">
                        <label for="street_address_shipping">Street Address:</label>
                        <input type="text" id="street_address_shipping" name="street_address_shipping" value="<?php echo htmlspecialchars($user_street_address); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="city_shipping">City:</label>
                        <input type="text" id="city_shipping" name="city_shipping" value="<?php echo htmlspecialchars($user_city); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="postal_code_shipping">Postal Code:</label>
                        <input type="text" id="postal_code_shipping" name="postal_code_shipping" value="<?php echo htmlspecialchars($user_postal_code); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="country_shipping">Country:</label>
                        <input type="text" id="country_shipping" name="country_shipping" value="<?php echo htmlspecialchars($user_country); ?>" required>
                    </div>

                    <h3>Billing Information</h3>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="billing_same_as_shipping" name="billing_same_as_shipping" checked>
                        <label for="billing_same_as_shipping">Billing address is the same as shipping address</label>
                    </div>

                    <div id="billing_address_fields">
                        <div class="form-group">
                            <label for="street_address_billing">Street Address:</label>
                            <input type="text" id="street_address_billing" name="street_address_billing">
                        </div>

                        <div class="form-group">
                            <label for="city_billing">City:</label>
                            <input type="text" id="city_billing" name="city_billing">
                        </div>

                        <div class="form-group">
                            <label for="postal_code_billing">Postal Code:</label>
                            <input type="text" id="postal_code_billing" name="postal_code_billing">
                        </div>

                        <div class="form-group">
                            <label for="country_billing">Country:</label>
                            <input type="text" id="country_billing" name="country_billing">
                        </div>
                    </div>

                    <h3>Payment Method</h3>
                    <div class="form-group">
                        <label for="payment_method">Select Payment Method:</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">-- Select --</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="PayPal">PayPal</option>
                        </select>
                    </div>

                    <button type="submit" class="btn place-order-btn" name="place_order">Place Order</button>
                </form>
            </div>                         
            <div class="checkout-order-summary">
                <h3>Order Summary</h3>
                <div class="summary-items-list">
                    <?php
                    if (!empty($cart_items)):
                        foreach ($cart_items as $item):
                            $line_item_total = $item['price'] * $item['quantity'];
                    ?>
                            <div class="summary-item-row">
                                <span class="item-name"><?php echo htmlspecialchars($item['name']); ?> x <?php echo htmlspecialchars($item['quantity']); ?></span>
                                <span class="item-price">$<?php echo htmlspecialchars(number_format($line_item_total, 2)); ?></span>
                            </div>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <p>Your cart is empty.</p>
                    <?php endif; ?>
                </div>

                <div class="summary-totals">
                    <div class="summary-row"><span>Subtotal:</span><span>$<?php echo htmlspecialchars(number_format($subtotal, 2)); ?></span></div>
                    <div class="summary-row"><span>Shipping:</span><span><?php echo $shipping_cost == 0 ? 'Free' : '$' . htmlspecialchars(number_format($shipping_cost, 2)); ?></span></div>
                    <div class="summary-total"><span>Total:</span><span>$<?php echo htmlspecialchars(number_format($total, 2)); ?></span></div>
                </div>
            </div>         
        </div>     
    </main>

    <footer>
         <div><h3>Shop</h3><ul><li><a href="#">Skincare</a></li><li><a href="#">Makeup</a></li><li><a href="#">Hair Care</a></li><li><a href="#">Body Care</a></li><li><a href="#">Gift Sets</a></li></ul></div>
         <div><h3>About</h3><ul><li><a href="#">Our Story</a></li><li><a href="#">Ingredients</a></li><li><a href="#">Sustainability</a></li><li><a href="#">Blog</a></li><li><a href="#">Press</a></li></ul></div>
         <div><h3>Help</h3><ul><li><a href="#">Contact Us</a></li><li><a href="#">FAQs</a></li><li><a href="#">Shipping</a></li><li><a href="#">Returns</a></li><li><a href="#">Track Order</a></li></ul></div>
         <div><h3>Connect</h3><ul><li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li><li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li><li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li><li><a href="#"><i class="fab fa-pinterest"></i> Pinterest</a></li></ul></div>
         <div class="copyright"><p>&copy; <?php echo date("Y"); ?> Nescare. All rights reserved.</p></div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const billingCheckbox = document.getElementById('billing_same_as_shipping');
            const billingFields = document.getElementById('billing_address_fields');
            const billingInputs = billingFields.querySelectorAll('input');

            function toggleBillingFields() {
                if (billingCheckbox.checked) {
                    billingFields.style.display = 'none';
                    billingInputs.forEach(input => input.removeAttribute('required'));
                } else {
                    billingFields.style.display = 'block';
                    billingInputs.forEach(input => input.setAttribute('required', 'required'));
                }
            }

            toggleBillingFields();

            billingCheckbox.addEventListener('change', toggleBillingFields);

            const messages = document.querySelectorAll('.message');

            messages.forEach(message => {
                const closeBtn = message.querySelector('.close-btn');

                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        message.style.opacity = '0';
                        setTimeout(function() {
                            message.remove();
                        }, 500);
                    });
                }

                if (message.classList.contains('success') || message.classList.contains('warning')) {
                    setTimeout(function() {
                        message.style.opacity = '0';
                        setTimeout(function() {
                            message.remove();
                        }, 500);
                    }, 5000);
                }
            });

        });
    </script>
</body>
</html>
