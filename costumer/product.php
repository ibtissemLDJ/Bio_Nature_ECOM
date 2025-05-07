<?php
session_start();
// Include database connection
// Make sure db_connection.php connects to your 'nescare' database
require_once 'db_connection.php'; // Make sure this path is correct

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


// --- Fetch Categories ---
// Use the correct table name 'categories'
$categories_sql = "SELECT category_id, name FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);
if (!$categories_result) {
    error_log("Error fetching categories (product.php): " . $conn->error);
    // Handle error appropriately, maybe show a default state or message
    // $_SESSION['error_message'] = "Could not load categories.";
    $categories_result = false; // Ensure $categories_result is false on error
}

// --- Filter Products by Category ---
$filtered_category_id = null;
if (isset($_GET['category']) && filter_var($_GET['category'], FILTER_VALIDATE_INT)) {
    $filtered_category_id = intval($_GET['category']);
    // Table name 'items' is correct
    $sql = "SELECT item_id, name, description, price, stock, category_id, image_url FROM items WHERE category_id = ? ORDER BY name ASC";
    $stmt_items = $conn->prepare($sql);
    if ($stmt_items) {
        $stmt_items->bind_param("i", $filtered_category_id);
        $stmt_items->execute();
        $result = $stmt_items->get_result();
         if (!$result) {
             error_log("Error executing items query (filtered) (product.php): " . $stmt_items->error);
             // $_SESSION['error_message'] = "Error fetching filtered products.";
         }
         $stmt_items->close();
    } else {
         error_log("Error preparing items query (filtered) (product.php): " . $conn->error);
         // $_SESSION['error_message'] = "Database error preparing product query.";
         $result = false; // Ensure $result is false on error
    }
} else {
    // Table name 'items' is correct
    $sql = "SELECT item_id, name, description, price, stock, category_id, image_url FROM items ORDER BY name ASC";
    $result = $conn->query($sql);
     if (!$result) {
         error_log("Error fetching all items (product.php): " . $conn->error);
         // $_SESSION['error_message'] = "Error fetching all products.";
     }
}

// --- User Profile Information (for header) ---
$profile_picture = "images/user1.png"; // Default profile picture
$username = ""; // Default username

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Use the correct table name 'users' and select 'profile_picture', 'username'
    $stmt_user = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $stmt_user->bind_result($profile_picture_db, $username_db);
        if ($stmt_user->fetch()) {
            $username = $username_db;
            // Check if the fetched profile picture path exists (good for relative paths)
            if (!empty($profile_picture_db) && file_exists($profile_picture_db)) {
                $profile_picture = $profile_picture_db;
            } elseif (!empty($profile_picture_db)) {
                 // Log if the path exists in DB but file is missing
                 error_log("Profile picture file not found: " . $profile_picture_db . " for user_id: " . $user_id);
                 // Default picture is already set, no need to do anything else
            }
        } else {
             // This case means a user ID in session doesn't exist in the DB (shouldn't happen often)
             error_log("User ID " . $user_id . " from session not found in users table (product.php).");
             // Consider logging the user out in a real scenario
             // session_destroy();
             // unset($_SESSION['user_id']);
             // header("Location: login.php");
             // exit();
        }
        $stmt_user->close();
    } else {
        error_log("Database query failed for user profile (product.php): " . $conn->error);
        // $_SESSION['error_message'] = (isset($_SESSION['error_message']) ? $_SESSION['error_message'] . " " : "") . "Could not load user info.";
    }
}

// --- Wishlist check function (checks session for guests, DB for logged-in) ---
// Pass connection object to the function
function isProductInWishlist($item_id, $conn) {
    // Initialize session wishlist for guests if not already done
    if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }

     if (isset($_SESSION['user_id'])) {
         $user_id = $_SESSION['user_id'];
         // Use the correct table name 'favorites'
         $stmt = $conn->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND item_id = ?");
         if ($stmt) {
             $stmt->bind_param("ii", $user_id, $item_id);
             $stmt->execute();
             $stmt->bind_result($count);
             $stmt->fetch();
             $stmt->close();
             return $count > 0; // Return true if count > 0
         } else {
             error_log("Database query failed for checking wishlist status (product.php function): " . $conn->error);
             // Fallback to session check in case of DB error for logged-in users
             return isset($_SESSION['wishlist'][$item_id]);
         }
     } else {
         // Check session wishlist for guest users
         return isset($_SESSION['wishlist'][$item_id]); // Assumes item_id is used as key
     }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Products - Nescare</title>
    <link rel="stylesheet" href="products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    
</head>
<body>
    <?php // Include header - you might have a separate header file ?>
    <header>
         <div class="logo-container">
            <a href="landing.php"><img src="images/logo.png" alt="Nescare Logo" class="logo" /></a>
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

    <div class="container">
         <?php
         // If header is fixed, messages might be hidden under it.
         // Consider displaying messages here, inside the .container, instead of in header.
         ?>

         <section class="intro-section">
             <div class="intro-text">
                 <h2 class="intro-title">Nourish Your Skin's Essence</h2>
                 <p class="intro-paragraph">Discover the power of Nescare, where science meets nature to bring you bio-care products that transform your skin's health and radiance.</p>
                 <a href="#products" class="intro-button">Explore Products</a> </div>
             <div class="intro-image-container">
                 <img src="images/P.png" alt="Product Image 1" class="intro-image">
                 <img src="images/product1.png" alt="Product Image 2" class="intro-image">
                 <img src="images/product2.png" alt="Product Image 3" class="intro-image">
                 <img src="images/product3.png" alt="Product Image 4" class="intro-image">
             </div>
         </section>

         <div id="products">
             <div class="title-filter-bar">
                  <h2 class="products-title">Our Products</h2>
                  <div class="filter-dropdown">
                      <i class="fas fa-filter filter-icon" onclick="toggleDropdown(event)" title="Filter by category"></i>
                      <div id="categoryList" class="dropdown-content">
                          <a href="product.php" <?php if ($filtered_category_id === null) echo 'style="font-weight:bold; color: #ff69b4;"'; ?>>All Categories</a> <?php
                          if ($categories_result && $categories_result->num_rows > 0):
                               $categories_result->data_seek(0); // Reset pointer if needed
                              while($cat = $categories_result->fetch_assoc()):
                                  $is_active_cat = ($filtered_category_id === (int)$cat['category_id']); // Cast for strict comparison
                          ?>
                                  <a href="?category=<?php echo $cat['category_id']; ?>" <?php if ($is_active_cat) echo 'style="font-weight:bold; color: #ff69b4;"'; ?>> <?php echo htmlspecialchars($cat['name']); ?>
                                  </a>
                          <?php
                              endwhile;
                          else: ?>
                              <span style="padding: 10px 15px; color: #888; font-size: 13px;">No categories found.</span>
                          <?php endif;
                          // $categories_result is freed in the PHP section
                          ?>
                      </div>
                  </div>
             </div>

             <div class="product-grid">
                 <?php
                 if ($result && $result->num_rows > 0):
                     while($row = $result->fetch_assoc()):
                         $item_id = $row['item_id'];
                         // Pass connection object to the function
                         $isInWishlist = isProductInWishlist($item_id, $conn);
                         $image_path = !empty($row['image_url']) ? htmlspecialchars($row['image_url']) : 'images/default_product.png';
                         $image_alt = !empty($row['name']) ? htmlspecialchars($row['name']) : 'Product Image';
                 ?>
                          <div class="product" data-product-id="<?php echo htmlspecialchars($item_id); ?>">
                              <div class="product-actions">
                                  <a href="add_to_wishlist.php?item_id=<?php echo htmlspecialchars($item_id); ?>"
                                     class="wishlist-icon <?php echo $isInWishlist ? 'active' : ''; ?>"
                                     title="<?php echo $isInWishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                                      <i class="fas fa-heart"></i>
                                  </a>
                                  <a href="add_to_cart.php?item_id=<?php echo htmlspecialchars($item_id); ?>&quantity=1"
                                    class="cart-icon" title="Add to cart">
                                     <i class="fas fa-shopping-basket"></i>
                                 </a>
                              </div>
                              <img src="<?php echo $image_path; ?>" alt="<?php echo $image_alt; ?>">
                              <div class="product-info">
                                  <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                                  <p><?php echo htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : ''); ?></p>
                                  <p class="price">$<?php echo htmlspecialchars(number_format((float)$row['price'], 2)); ?></p>
                              </div>
                          </div>
                 <?php
                     endwhile;
                     // $result is freed in the PHP section
                 elseif ($result):
                     ?>
                     <p style="grid-column: 1 / -1; text-align: center; padding: 20px;">No products found <?php echo $filtered_category_id ? 'in this category' : ''; ?>.</p>
                 <?php
                  else: // Error fetching products
                 ?>
                      <p style="grid-column: 1 / -1; text-align: center; padding: 20px; color: red;">Error fetching products.</p>
                 <?php
                 endif;
                 ?>
             </div>
         </div>
          </div>

    <?php // Include footer - you might have a separate footer file ?>
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
    // JavaScript for category filter dropdown
        function toggleDropdown(event) {
            event.stopPropagation(); // Prevent click from closing immediately
            const dropdown = document.getElementById("categoryList");
            dropdown.classList.toggle("show");
        }

        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
             // Check if the click target is NOT the filter icon AND NOT inside the dropdown content
            if (!event.target.matches('.filter-icon') && !event.target.closest('.dropdown-content')) {
                const dropdowns = document.getElementsByClassName("dropdown-content");
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // JavaScript for clicking product cards to go to detail page
        document.addEventListener('DOMContentLoaded', function() {
            const productCards = document.querySelectorAll('.product');

            productCards.forEach(card => {
                 // Add click listener to the card itself
                card.addEventListener('click', function(event) {
                    // Prevent navigating if a link/button *inside* the card was clicked
                    if (event.target.closest('.product-actions') || event.target.tagName === 'A' || event.target.tagName === 'BUTTON') {
                        return;
                    }
                    const productId = this.getAttribute('data-product-id');
                    if (productId) {
                        window.location.href = 'product_detail.php?id=' + productId; // Navigate to product detail page
                    } else {
                        console.error("Product ID not found for this card.");
                    }
                });
            });

            // JavaScript for intro image cycling (optional)
             const introImages = document.querySelectorAll('.intro-image-container .intro-image');
             let currentImageIndex = 0;
             function cycleImages() {
                 if (introImages.length < 2) return; // Need at least 2 images to cycle
                 // Remove active class from current image
                 introImages[currentImageIndex].classList.remove('active');
                 // Calculate next image index
                 currentImageIndex = (currentImageIndex + 1) % introImages.length;
                 // Add active class to the next image
                 introImages[currentImageIndex].classList.add('active');
             }
             // Set the first image as active initially
             if (introImages.length > 0) {
                 introImages[0].classList.add('active');
             }
             // Start cycling if there's more than one image
             if (introImages.length > 1) {
                 setInterval(cycleImages, 7000); // Change image every 7 seconds
             }
        });
    </script>

</body>
</html>
<?php
// --- Close database connection ---
// Close only after all database operations are complete
if ($categories_result && method_exists($categories_result, 'free')) $categories_result->free(); // Free result sets if not already done by fetch_assoc loop
if ($result && method_exists($result, 'free')) $result->free(); // Free result sets if not already done by fetch_assoc loop
$conn->close();
?>