<?php
session_start();

require_once 'db_connection.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- Guest Cart Merging Logic (Runs ONCE after login) ---
// This logic merges the session cart into the database cart upon login if not already done.
// It needs the correct cart_id lookup as well.
if ($conn && isset($_SESSION['user_id']) && !empty($_SESSION['cart']) && !isset($_SESSION['cart_merged'])) {
    $user_id = $_SESSION['user_id'];
    $merged_successfully = true;
    $conn->begin_transaction();

    // Find the user's cart_id first
    $cart_id_to_merge = null;
    $find_cart_sql = "SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1";
    $stmt_find_cart = $conn->prepare($find_cart_sql);
    if ($stmt_find_cart) {
        $stmt_find_cart->bind_param("i", $user_id);
        $stmt_find_cart->execute();
        $result_find_cart = $stmt_find_cart->get_result();
        if ($row_cart = $result_find_cart->fetch_assoc()) {
            $cart_id_to_merge = $row_cart['cart_id'];
        }
        $result_find_cart->free();
        $stmt_find_cart->close();
    } else {
        error_log("basket.php merge logic: Failed to prepare find cart statement: " . $conn->error);
        $merged_successfully = false; // Cannot merge without cart_id
    }

    // If user doesn't have a cart, create one for merging
    if ($cart_id_to_merge === null && $merged_successfully) {
        $create_cart_sql = "INSERT INTO carts (user_id) VALUES (?)";
        $stmt_create_cart = $conn->prepare($create_cart_sql);
         if ($stmt_create_cart) {
             $stmt_create_cart->bind_param("i", $user_id);
             if ($stmt_create_cart->execute()) {
                 $cart_id_to_merge = $conn->insert_id; // Get the ID of the newly created cart
             } else {
                  error_log("basket.php merge logic: Failed to execute create cart: " . $stmt_create_cart->error);
                 $merged_successfully = false;
             }
             $stmt_create_cart->close();
         } else {
             error_log("basket.php merge logic: Failed to prepare create cart statement: " . $conn->error);
             $merged_successfully = false;
         }
    }

    if ($cart_id_to_merge !== null && $merged_successfully) {
        foreach ($_SESSION['cart'] as $item_id => $item_details) {
            $quantity = isset($item_details['quantity']) ? intval($item_details['quantity']) : 0;

            if ($item_id > 0 && $quantity > 0) {
                // Check if item already exists in DB cart for this cart_id
                $sql_check = "SELECT quantity FROM cart_items WHERE cart_id = ? AND item_id = ?"; // Corrected query
                $stmt_check = $conn->prepare($sql_check);
                if ($stmt_check) {
                    $stmt_check->bind_param("ii", $cart_id_to_merge, $item_id); // Use cart_id
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();

                    if ($row = $result_check->fetch_assoc()) {
                        // Item exists, update quantity in DB (add guest quantity to existing DB quantity)
                        $new_quantity = $row['quantity'] + $quantity;
                        $sql_update = "UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND item_id = ?"; // Corrected query
                        $stmt_update = $conn->prepare($sql_update);
                        if ($stmt_update) {
                            $stmt_update->bind_param("iii", $new_quantity, $cart_id_to_merge, $item_id); // Use cart_id
                            if (!$stmt_update->execute()) {
                                 error_log("basket.php merge logic: Failed to execute merge update: " . $stmt_update->error);
                                 $merged_successfully = false;
                                 break;
                            }
                            $stmt_update->close();
                        } else {
                            error_log("basket.php merge logic: Failed to prepare merge update: " . $conn->error);
                            $merged_successfully = false;
                            break;
                        }
                    } else {
                        // Item does not exist, insert into DB
                        $sql_insert = "INSERT INTO cart_items (cart_id, item_id, quantity) VALUES (?, ?, ?)"; // Corrected query
                        $stmt_insert = $conn->prepare($sql_insert);
                        if ($stmt_insert) {
                            $stmt_insert->bind_param("iii", $cart_id_to_merge, $item_id, $quantity); // Use cart_id
                             if (!$stmt_insert->execute()) {
                                error_log("basket.php merge logic: Failed to execute merge insert: " . $stmt_insert->error);
                                $merged_successfully = false;
                                break;
                             }
                            $stmt_insert->close();
                        } else {
                            error_log("basket.php merge logic: Failed to prepare merge insert: " . $conn->error);
                            $merged_successfully = false;
                            break;
                        }
                    }
                    $result_check->free();
                    $stmt_check->close();
                } else {
                    error_log("basket.php merge logic: Failed to prepare merge check: " . $conn->error);
                    $merged_successfully = false;
                    break;
                }
            }
        }
    } else {
         // Cart ID could not be found or created, merge failed before loop started
         $merged_successfully = false;
    }


    if ($merged_successfully) {
        $conn->commit();
        $_SESSION['cart'] = []; // Clear the session cart after successful merge
        $_SESSION['cart_merged'] = true; // Set flag
         // Optional success message can be added here
    } else {
        $conn->rollback(); // Rollback changes if any step failed
        // Keep session cart so user doesn't lose items entirely if DB merge failed
        error_log("basket.php merge logic: Transaction rolled back.");
         $_SESSION['error_message'] = "There was an error merging your previous cart items. Please check your cart."; // User friendly message
    }
}


// --- Cart Management Logic (Update/Remove) ---
// These handlers process actions and redirect back to basket.php

// Handle updating item quantity directly from basket page (via GET, triggered by JS change event)
// **These also need to use cart_id for logged-in users**
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['update_quantity']) && isset($_GET['item_id']) && isset($_GET['quantity'])) {
    $item_id = intval($_GET['item_id']);
    $quantity = intval($_GET['quantity']);

    if ($item_id > 0) {
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            // Find user's cart_id first
            $cart_id = null;
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
                 error_log("basket.php update quantity: Failed to prepare find cart statement: " . $conn->error);
                 $_SESSION['error_message'] = "Database error updating cart (find cart failed).";
                 $cart_id = null; // Ensure cart_id is null if lookup fails
            }


            if ($cart_id !== null) { // Proceed only if cart_id was found
                if ($quantity > 0) {
                    // Update quantity if greater than 0 - Use cart_id
                    $update_sql = "UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND item_id = ?"; // Corrected query
                    $stmt_update = $conn->prepare($update_sql);
                    if ($stmt_update) {
                        $stmt_update->bind_param("iii", $quantity, $cart_id, $item_id); // Use cart_id
                        if($stmt_update->execute()){
                           // Success message handled by basket.php display logic if needed, or set session message here
                           // $_SESSION['success_message'] = "Cart quantity updated.";
                        } else {
                            error_log("basket.php update quantity: Failed to execute update: " . $stmt_update->error);
                            $_SESSION['error_message'] = "Database error updating quantity.";
                        }
                        $stmt_update->close();
                    } else {
                        error_log("basket.php update quantity: Failed to prepare update statement: " . $conn->error);
                        $_SESSION['error_message'] = "Database error preparing update quantity.";
                    }
                } else {
                    // Remove item if quantity is 0 or less - Use cart_id
                    $delete_sql = "DELETE FROM cart_items WHERE cart_id = ? AND item_id = ?"; // Corrected query
                    $stmt_delete = $conn->prepare($delete_sql);
                    if ($stmt_delete) {
                        $stmt_delete->bind_param("ii", $cart_id, $item_id); // Use cart_id
                         if($stmt_delete->execute()){
                           // Success message handled by basket.php display logic if needed
                           // $_SESSION['success_message'] = "Item removed from cart.";
                        } else {
                            error_log("basket.php update quantity: Failed to execute delete: " . $stmt_delete->error);
                            $_SESSION['error_message'] = "Database error removing item.";
                        }
                        $stmt_delete->close();
                    } else {
                         error_log("basket.php update quantity: Failed to prepare delete statement: " . $conn->error);
                         $_SESSION['error_message'] = "Database error preparing remove item.";
                    }
                }
            } else {
                // Cart ID was not found for logged-in user
                $_SESSION['warning_message'] = "Cannot update cart: Your cart could not be found.";
                error_log("basket.php update quantity: Cart ID not found for user " . $user_id);
            }
        } else {
            // User is NOT logged in, update session cart - This logic was correct
            if (isset($_SESSION['cart'][$item_id]) && is_array($_SESSION['cart'][$item_id])) {
                if ($quantity > 0) {
                    $_SESSION['cart'][$item_id]['quantity'] = $quantity;
                    // $_SESSION['success_message'] = "Session cart quantity updated."; // Handled by display
                } else {
                    unset($_SESSION['cart'][$item_id]);
                    // $_SESSION['success_message'] = "Item removed from session cart."; // Handled by display
                }
            }
        }
    } else {
        $_SESSION['warning_message'] = "Invalid item specified for quantity update.";
    }
    header('Location: basket.php'); // Redirect back to basket.php
    exit();
}


// Handle removing item directly from basket page (via GET)
// **This also needs to use cart_id for logged-in users**
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['remove']) && isset($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    if ($item_id > 0) {
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
             // Find user's cart_id first
            $cart_id = null;
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
                 error_log("basket.php remove: Failed to prepare find cart statement: " . $conn->error);
                 $_SESSION['error_message'] = "Database error removing item (find cart failed).";
                 $cart_id = null; // Ensure cart_id is null if lookup fails
            }

            if ($cart_id !== null) { // Proceed only if cart_id was found
                // User is logged in, remove from DB cart - Use cart_id
                $delete_sql = "DELETE FROM cart_items WHERE cart_id = ? AND item_id = ?"; // Corrected query
                $stmt_delete = $conn->prepare($delete_sql);
                if ($stmt_delete) {
                    $stmt_delete->bind_param("ii", $cart_id, $item_id); // Use cart_id
                    if($stmt_delete->execute()){
                       // Success message handled by basket.php display logic if needed
                       // $_SESSION['success_message'] = "Item removed from cart.";
                    } else {
                        error_log("basket.php remove: Failed to execute delete: " . $stmt_delete->error);
                        $_SESSION['error_message'] = "Database error removing item.";
                    }
                    $stmt_delete->close();
                } else {
                    error_log("basket.php remove: Failed to prepare delete statement: " . $conn->error);
                    $_SESSION['error_message'] = "Database error preparing remove item.";
                }
            } else {
                 // Cart ID was not found for logged-in user
                $_SESSION['warning_message'] = "Cannot remove item: Your cart could not be found.";
                error_log("basket.php remove: Cart ID not found for user " . $user_id);
            }

        } else {
            // User is NOT logged in, remove from session cart - This logic was correct
            if (isset($_SESSION['cart'][$item_id]) && is_array($_SESSION['cart'][$item_id])) {
                unset($_SESSION['cart'][$item_id]);
                // $_SESSION['success_message'] = "Item removed from session cart."; // Handled by display
            }
        }
    } else {
        $_SESSION['warning_message'] = "Invalid item specified for removal.";
    }
    header('Location: basket.php'); // Redirect back to basket.php
    exit();
}


// --- Fetch Cart Items for Display ---
// **This also needs to use cart_id for logged-in users**
$cart_items = [];
// Check if user is logged in AND connection is valid
if ($conn && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

     // Find user's cart_id first
    $cart_id = null;
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
         error_log("basket.php fetch display: Failed to prepare find cart statement: " . $conn->error);
          // Fallback to session cart if we can't even find the cart ID
         $cart_items = $_SESSION['cart'];
         $_SESSION['error_message'] = "Could not load your cart from the database (find cart failed).";
         $cart_id = null; // Ensure cart_id is null
     }


    if ($cart_id !== null) { // Proceed only if cart_id was found
        // Select cart items and join with Items to get details like name, price, image - Use cart_id
        $sql = "SELECT ci.item_id, ci.quantity, i.name, i.price, i.image_url
                FROM cart_items ci
                JOIN items i ON ci.item_id = i.item_id
                WHERE ci.cart_id = ?"; // Corrected query
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $cart_id); // Use cart_id
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $cart_items[$row['item_id']] = [
                    'quantity' => $row['quantity'],
                    'name' => $row['name'],
                    'price' => $row['price'],
                    'image_url' => $row['image_url']
                ];
            }
             if ($result) $result->free(); // Free result set, check if $result is valid
            $stmt->close();

            // Sync session cart with DB data after fetching (replaces session cart with current DB cart).
            $_SESSION['cart'] = $cart_items;

        } else {
            error_log("basket.php fetch display: Database query failed to fetch cart items for cart ID " . $cart_id . ": " . $conn->error);
            // Fallback to using the session cart if DB fetch fails
            $cart_items = $_SESSION['cart'];
            $_SESSION['error_message'] = "Could not load your cart items from the database.";
        }
    } else {
        // Cart ID was not found for logged-in user, fallback already set above
        // $cart_items = $_SESSION['cart']; // Already set above if find cart failed
         if (!isset($_SESSION['error_message']) && !isset($_SESSION['warning_message'])) {
              // If no cart exists and no items in session, cart is just empty.
              // No need for a message if it's just an empty cart for a new user.
         }
    }
} else {
    // User is not logged in OR DB connection was not available, use the session cart for display
    $cart_items = $_SESSION['cart'];
}


// --- Calculate Subtotal, Shipping, and Total ---
$subtotal = 0;
foreach ($cart_items as $item_id => $item) {
    $item_price = isset($item['price']) ? floatval($item['price']) : 0;
    $item_quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
    $subtotal += $item_price * $item_quantity;
}

// Example Shipping Logic: Free shipping over $100, otherwise $10
$shipping_cost = $subtotal > 100 ? 0 : 10;
$total = $subtotal + $shipping_cost;


// --- Fetch Profile Picture and Username for Header (if logged in) ---
$profile_picture = "images/user1.png"; // Default picture
$username = "";
// Ensure connection is valid before DB operations AND user is logged in
if ($conn instanceof mysqli && !$conn->connect_error && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Use the correct table name 'users' and select 'profile_picture', 'username'
    $stmt_header_user = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt_header_user) {
        $stmt_header_user->bind_param("i", $user_id);
        $stmt_header_user->execute();
        $result_header_user = $stmt_header_user->get_result();
        if ($row_header_user = $result_header_user->fetch_assoc()) {
             $username = htmlspecialchars($row_header_user['username']);
            if (!empty($row_header_user['profile_picture']) && file_exists($row_header_user['profile_picture'])) {
                $profile_picture = htmlspecialchars($row_header_user['profile_picture']);
            }
        }
        if ($result_header_user) $result_header_user->free();
        $stmt_header_user->close();
    } else {
        error_log("basket.php header profile: DB query failed: " . $conn->error);
    }
}


// Close the database connection if it was successfully opened and is a valid object
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
    <title>Your Cart | Nescare</title>
    <link rel="stylesheet" href="path/to/your/main-styles.css">
    <link rel="stylesheet" href="basket.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<header>
         <div class="logo-container">
             <a href="index.php"><img src="images/logo.png" alt="Nescare Logo" class="logo" /></a>
              </div>
         <nav>
              <ul>
                  <li><a href="product.php" class="active">Shop</a></li>
                  <li><a href="about.php">About</a></li>
                  <li><a href="ingredients.php">Ingredients</a></li>
                  <li><a href="blog.php">Blog</a></li>
                  <li><a href="contact.php">Contact</a></li>
              </ul>
         </nav>
         <div class="nav-right">
              <a href="wishlist.php" title="Wishlist"><i class="fas fa-heart"></i></a>
              <a href="basket.php" title="Cart"><i class="fas fa-shopping-basket"></i></a>
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
           // Display session messages (success, warning, error)
           // You might want to place this outside the fixed header if header is fixed
           if (isset($_SESSION['success_message'])): ?>
               <div class="message success"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
               <?php unset($_SESSION['success_message']);
           endif;
           if (isset($_SESSION['warning_message'])): ?>
               <div class="message warning"><?php echo htmlspecialchars($_SESSION['warning_message']); ?></div>
               <?php unset($_SESSION['warning_message']);
           endif;
           if (isset($_SESSION['error_message'])): ?>
               <div class="message error"><?php echo htmlspecialchars($_SESSION['error_message']); ?></div>
               <?php unset($_SESSION['error_message']);
           endif;
           ?>
     </header>

    <main class="basket-container">
        <h1>Your Cart</h1>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-basket empty-icon"></i>
                <p>Your cart is empty.</p>
                <a href="product.php" class="btn">Discover our products</a>
            </div>
        <?php else: ?>
            <div class="basket-content">
                <div class="cart-items-list">
                     <div class="cart-item-header">
                        <span>Product</span>
                        <span>Price</span>
                        <span>Quantity</span>
                        <span>Subtotal</span>
                        <span></span>
                    </div>
                    <?php foreach ($cart_items as $item_id => $item): ?>
                        <div class="cart-item">
                            <div class="item-details">
                                <img src="<?php echo htmlspecialchars(isset($item['image_url']) ? $item['image_url'] : ''); ?>" alt="<?php echo htmlspecialchars(isset($item['name']) ? $item['name'] : 'Unknown Item'); ?>" class="item-image">
                                <div class="item-info">
                                    <h3 class="item-name"><?php echo htmlspecialchars(isset($item['name']) ? $item['name'] : 'Unknown Item'); ?></h3>
                                </div>
                            </div>
                            <div class="item-price">$<?php echo htmlspecialchars(number_format(isset($item['price']) ? $item['price'] : 0, 2)); ?></div>
                            <div class="item-quantity">
                                <input type="number" value="<?php echo htmlspecialchars(isset($item['quantity']) ? $item['quantity'] : 0); ?>" min="0" class="quantity-input" data-item-id="<?php echo $item_id; ?>">
                            </div>
                            <div class="item-subtotal">$<?php
                                $item_price = isset($item['price']) ? floatval($item['price']) : 0;
                                $item_quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                                echo htmlspecialchars(number_format($item_price * $item_quantity, 2));
                            ?></div>
                            <div class="item-remove">
                                <a href="basket.php?remove=1&item_id=<?php echo $item_id; ?>" class="remove-btn" title="Remove item">
                                    <i class="fas fa-times-circle"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="basket-summary">
                    <h2>Order Summary</h2>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>$<?php echo htmlspecialchars(number_format($subtotal, 2)); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span><?php echo $shipping_cost == 0 ? 'Free' : '$' . htmlspecialchars(number_format($shipping_cost, 2)); ?></span>
                    </div>
                    <div class="summary-total">
                        <span>Total:</span>
                        <span>$<?php echo htmlspecialchars(number_format($total, 2)); ?></span>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="checkout_info.php" class="btn checkout-btn">Proceed to Checkout</a>
                    <?php else: ?>
                        <p class="login-prompt">Please log in to proceed to checkout.</p>
                         <a href="login.php?redirect=basket.php" class="btn checkout-btn login-required-btn">Login to Checkout</a>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div>
            <h3>Shop</h3>
            <ul>
                <li><a href="?category=1">Skincare</a></li>
                <li><a href="?category=2">Makeup</a></li>
                <li><a href="?category=3">Hair Care</a></li>
                <li><a href="?category=4">Body Care</a></li>
                <li><a href="#">Gift Sets</a></li> </ul>
        </div>
        <div>
            <h3>About</h3>
            <ul>
                <li><a href="about.php">Our Story</a></li>
                <li><a href="ingredients.php">Ingredients</a></li>
                <li><a href="#">Sustainability</a></li> <li><a href="blog.php">Blog</a></li>
                <li><a href="#">Press</a></li> </ul>
        </div>
        <div>
            <h3>Help</h3>
            <ul>
                <li><a href="contact.php">Contact Us</a></li>
                <li><a href="#">FAQs</a></li> <li><a href="#">Shipping</a></li> <li><a href="#">Returns</a></li> <li><a href="#">Track Order</a></li> </ul>
        </div>
        <div>
            <h3>Connect</h3>
            <ul>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i> Instagram</a></li>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i> Facebook</a></li>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i> Twitter</a></li>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-pinterest"></i> Pinterest</a></li>
            </ul>
        </div>
        <div class="copyright">
            <p>&copy; <?php echo date("Y"); ?> Nescare. All rights reserved.</p>
        </div>
    </footer>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.setAttribute('data-initial-value', input.value);

                input.addEventListener('change', function() {
                    let newQuantity = parseInt(this.value);
                    const initialQuantity = parseInt(this.getAttribute('data-initial-value'));

                    if (newQuantity === initialQuantity) {
                        return;
                    }

                    if (isNaN(newQuantity) || newQuantity < 0) {
                        this.value = initialQuantity;
                        alert('Please enter a valid quantity.');
                        return;
                    }

                    const itemId = this.getAttribute('data-item-id');
                    window.location.href = `basket.php?update_quantity=1&item_id=${itemId}&quantity=${newQuantity}`;
                });
            });
        });
    </script>
</body>
</html>