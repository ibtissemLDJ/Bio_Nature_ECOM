<?php
session_start();
// Include database connection - Make sure this path is correct relative to wishlist.php
// Make sure db_connection.php connects to your 'nescare' database
require_once 'db_connection.php';

// --- Check if user is logged in ---
// Wishlist requires a logged-in user.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['warning_message'] = "Please log in to view your wishlist.";
    header('Location: login.php'); // Redirect to your login page
    exit();
}
$user_id = $_SESSION['user_id'];

// --- Handle Remove from Wishlist Action ---
if (isset($_GET['remove']) && isset($_GET['item_id'])) {
    $item_id = intval($_GET['item_id']);
    if ($item_id > 0) {
        // Use the correct table name 'favorites'
        $delete_sql = "DELETE FROM favorites WHERE user_id = ? AND item_id = ?";
        $stmt_delete = $conn->prepare($delete_sql);
        if ($stmt_delete) {
            $stmt_delete->bind_param("ii", $user_id, $item_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            $_SESSION['success_message'] = "Item removed from wishlist.";
        } else {
            error_log("DB query failed (wishlist.php remove): " . $conn->error);
            $_SESSION['error_message'] = "Error removing item from wishlist.";
        }
    }
    // Redirect back to wishlist page
    header('Location: wishlist.php');
    exit();
}

// --- Handle Add to Cart from Wishlist Action ---
// This assumes a simple form on the wishlist page submits to this script.
if (isset($_POST['add_to_cart_from_wishlist']) && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']);
    $quantity = 1; // Default quantity is 1 when adding from wishlist

    if ($item_id > 0) {
        // --- New Logic to handle carts and cart_items tables ---

        // 1. Find the user's cart_id or create a new cart if it doesn't exist
        $cart_id = null;
        $sql_find_cart = "SELECT cart_id FROM carts WHERE user_id = ?";
        $stmt_find_cart = $conn->prepare($sql_find_cart);

        if ($stmt_find_cart) {
            $stmt_find_cart->bind_param("i", $user_id);
            $stmt_find_cart->execute();
            $result_find_cart = $stmt_find_cart->get_result();

            if ($row_cart = $result_find_cart->fetch_assoc()) {
                // Cart found for the user
                $cart_id = $row_cart['cart_id'];
            } else {
                // No cart found, create a new one for this user
                $sql_create_cart = "INSERT INTO carts (user_id) VALUES (?)";
                $stmt_create_cart = $conn->prepare($sql_create_cart);
                if ($stmt_create_cart) {
                    $stmt_create_cart->bind_param("i", $user_id);
                    $stmt_create_cart->execute();
                    $cart_id = $conn->insert_id; // Get the ID of the newly created cart
                    $stmt_create_cart->close();
                    // Optionally log or message that a new cart was created
                } else {
                     error_log("DB query failed (wishlist.php create cart): " . $conn->error);
                     $_SESSION['error_message'] = "Error creating cart.";
                     // Redirect or handle error appropriately if cart creation failed
                     header('Location: wishlist.php');
                     exit(); // Stop processing if cart couldn't be created
                }
            }
            $stmt_find_cart->close();
        } else {
            error_log("DB query failed (wishlist.php find cart): " . $conn->error);
            $_SESSION['error_message'] = "Error finding cart.";
            header('Location: wishlist.php');
            exit(); // Stop processing if cart couldn't be found
        }

        // Ensure a valid cart_id was obtained before proceeding to cart_items
        if ($cart_id !== null) {
            // 2. Check if the item already exists in the cart_items table for this cart
            // Use table 'cart_items' and join with 'items' to get price for stock check if needed (though trigger handles stock)
            $sql_check_item = "SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND item_id = ?"; // Use cart_id
            $stmt_check_item = $conn->prepare($sql_check_item);

            if ($stmt_check_item) {
                $stmt_check_item->bind_param("ii", $cart_id, $item_id); // Bind cart_id and item_id
                $stmt_check_item->execute();
                $result_check_item = $stmt_check_item->get_result();

                if ($row_item = $result_check_item->fetch_assoc()) {
                    // Item already exists in cart, update the quantity
                    $new_quantity = $row_item['quantity'] + $quantity;
                    // Update the cart_items table using cart_id and item_id
                    $sql_update = "UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND item_id = ?"; // Use cart_id
                    $stmt_update = $conn->prepare($sql_update);
                    if ($stmt_update) {
                        $stmt_update->bind_param("iii", $new_quantity, $cart_id, $item_id); // Bind new_quantity, cart_id, item_id
                        if ($stmt_update->execute()) {
                             $_SESSION['success_message'] = "Item quantity updated in cart!";
                        } else {
                             error_log("DB query failed (wishlist.php add update exec): " . $conn->error);
                             $_SESSION['error_message'] = "Error updating cart.";
                        }
                        $stmt_update->close();
                    } else {
                        error_log("DB query failed (wishlist.php add update prep): " . $conn->error);
                        $_SESSION['error_message'] = "Error updating cart.";
                    }
                } else {
                    // Item does not exist in cart, insert it as a new item in cart_items
                    // Insert into cart_items (cart_id, item_id, quantity)
                    $sql_insert = "INSERT INTO cart_items (cart_id, item_id, quantity) VALUES (?, ?, ?)"; // Use cart_id
                    $stmt_insert = $conn->prepare($sql_insert);
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("iii", $cart_id, $item_id, $quantity); // Bind cart_id, item_id, quantity
                        if ($stmt_insert->execute()) {
                            $_SESSION['success_message'] = "Item added to cart!";
                        } else {
                             // Check if the error is due to insufficient stock (from your trigger)
                             if ($conn->errno == 1644) { // MySQL error code for SIGNAL
                                 $_SESSION['error_message'] = "Insufficient stock available for this item.";
                                 error_log("Attempted to add item with insufficient stock (wishlist.php insert exec): Item ID " . $item_id . ", Requested Qty " . $quantity);
                             } else {
                                error_log("DB query failed (wishlist.php add insert exec): " . $conn->error);
                                $_SESSION['error_message'] = "Error adding to cart.";
                             }
                        }
                        $stmt_insert->close();
                    } else {
                        error_log("DB query failed (wishlist.php add insert prep): " . $conn->error);
                        $_SESSION['error_message'] = "Error adding to cart.";
                    }
                }
                $stmt_check_item->close();
            } else {
                error_log("DB query failed (wishlist.php add check prep): " . $conn->error);
                $_SESSION['error_message'] = "Error checking cart item.";
            }
        } // End if $cart_id is null check

        // Optional: Uncomment the following block if you want to remove the item
        // from the wishlist after it's added to the cart.
        /*
        // Use the correct table name 'favorites'
        $delete_wishlist_sql = "DELETE FROM favorites WHERE user_id = ? AND item_id = ?";
        $stmt_delete_wishlist = $conn->prepare($delete_wishlist_sql);
        if ($stmt_delete_wishlist) {
             $stmt_delete_wishlist->bind_param("ii", $user_id, $item_id);
             $stmt_delete_wishlist->execute();
             $stmt_delete_wishlist->close();
        } else {
             error_log("DB query failed (wishlist.php remove after add): " . $conn->error);
             // Optionally set an error message for failing to remove from wishlist
        }
        */
    } // End if item_id > 0 check

    // Redirect back to wishlist page or basket page after adding
    header('Location: wishlist.php'); // Or header('Location: basket.php');
    exit();
}


// --- Fetch Wishlist Items for Display ---
$wishlist_items = [];
// Select favorite items and join with items to get details
// Use the correct table names 'favorites' and 'items'
$sql = "SELECT f.item_id, i.name, i.price, i.image_url FROM favorites f JOIN items i ON f.item_id = i.item_id WHERE f.user_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
           $wishlist_items[] = $row; // Store items in an array
    }
    $stmt->close();
} else {
    error_log("Database query failed to fetch wishlist items (wishlist.php): " . $conn->error);
      $_SESSION['error_message'] = "Could not load your wishlist.";
}


// --- Fetch Profile Picture and Username for Header ---
$profile_picture = "images/user1.png"; // Default picture
$username = "";
// Assume $user_id is already set from the login check above
// Use the correct table name 'users'
$stmt = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($profile_picture_db, $username);
    $stmt->fetch();
    $stmt->close();
    // Check if the fetched profile picture path exists
    // Note: file_exists checks server filesystem. If images are served via URL, this check might need adjustment.
    if (!empty($profile_picture_db) && file_exists($profile_picture_db)) {
        $profile_picture = $profile_picture_db;
    }
} else {
    error_log("DB query failed (wishlist.php profile): " . $conn->error);
    // Optionally set an error message for failing to fetch profile info
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Wishlist | Nescare</title>
    <link rel="stylesheet" href="path/to/your/main-styles.css">
    <link rel="stylesheet" href="wishlist.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Basic styling for messages */
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php // Include header - replace with your actual header include if you use one ?>
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
             <a href="wishlist.php" title="Your Wishlist"><i class="fas fa-heart active"></i></a>
             <a href="basket.php" title="Your Cart"><i class="fas fa-shopping-basket"></i></a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="profile-container">
                    <a href="profile.php" title="Your Profile">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="profile-pic" title="<?php echo htmlspecialchars($username); ?>">
                    </a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="wishlist-container">
        <h1>Your Wishlist</h1>

        <?php
        // Display session messages (success, warning, error)
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


        <?php if (empty($wishlist_items)): // Check if the wishlist is empty ?>
            <div class="empty-wishlist">
                 <i class="fas fa-heart-broken empty-icon"></i>
                 <p>Your wishlist is empty.</p>
                 <a href="product.php" class="btn">Discover products to love</a>
            </div>
        <?php else: // Display wishlist items if not empty ?>
            <div class="wishlist-items-list">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="wishlist-item">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                        <div class="item-info">
                            <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div class="item-price">$<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></div>
                             </div>
                        <div class="item-actions">
                             <form action="wishlist.php" method="post" class="add-to-cart-form">
                                 <input type="hidden" name="add_to_cart_from_wishlist" value="1">
                                 <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['item_id']); ?>">
                                 <button type="submit" class="btn add-to-cart-btn">Add to Cart</button>
                             </form>
                            <a href="wishlist.php?remove=1&item_id=<?php echo htmlspecialchars($item['item_id']); ?>" class="remove-btn" title="Remove item from wishlist">
                                 <i class="fas fa-times-circle"></i> Remove
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

   
    <footer>
        <div><h3>Shop</h3><ul><li><a href="#">Skincare</a></li><li><a href="#">Makeup</a></li><li><a href="#">Hair Care</a></li><li><a href="#">Body Care</a></li><li><a href="#">Gift Sets</a></li></ul></div>
        <div><h3>About</h3><ul><li><a href="#">Our Story</a></li><li><a href="#">Ingredients</a></li><li><a href="#">Sustainability</a></li><li><a href="#">Blog</a></li><li><a href="#">Press</a></li></ul></div>
        <div><h3>Help</h3><ul><li><a href="#">Contact Us</a></li><li><a href="#">FAQs</a></li><li><a href="#">Shipping</a></li><li><a href="#">Returns</a></li><li><a href="#">Track Order</a></li></ul></div>
        <div><h3>Connect</h3><ul><li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li><li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li><li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li><li><a href="#"><i class="fab fa-pinterest"></i> Pinterest</a></li></ul></div>
        <div class="copyright"><p>&copy; 2025 Nescare. All rights reserved.</p></div>
    </footer>
</body>
</html>
<?php $conn->close(); // Close database connection ?>