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

// --- Get product ID from URL ---
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- Fetch product details from the database ---
$product = null;
$additional_images = []; // Array to hold additional image URLs

if ($product_id > 0) {
    // Table name 'items' is correct. Select all relevant columns.
    $sql = "SELECT item_id, name, description, price, image_url, ingredients, how_to_use, shipping_returns_info, stock FROM items WHERE item_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $product = $result->fetch_assoc();

            // --- Fetch Additional Product Images ---
            // Use the correct table name 'item_images'
            // Fetch images for this product, excluding the main one if it's already stored in items.image_url
            // A common approach is to fetch all and let the display logic handle main vs additional.
            // Let's fetch all from item_images for this item.
            $sql_images = "SELECT image_url FROM item_images WHERE item_id = ? ORDER BY is_main DESC, image_id ASC"; // Order by main first, then ID
            $stmt_images = $conn->prepare($sql_images);
            if ($stmt_images) {
                $stmt_images->bind_param("i", $product_id);
                $stmt_images->execute();
                $result_images = $stmt_images->get_result();
                while ($row_image = $result_images->fetch_assoc()) {
                    // Add image URL to array, but make sure not to add the main image URL twice
                    // if item_images also contains the main image and items.image_url is also used.
                    // For simplicity, let's just add all from item_images for now.
                    // If items.image_url is *always* the main one, you might check:
                    // if ($row_image['image_url'] !== $product['image_url']) {
                    //    $additional_images[] = $row_image['image_url'];
                    // }
                     $additional_images[] = $row_image['image_url'];
                }
                $result_images->free(); // Free the image result set
                $stmt_images->close();
            } else {
                 error_log("Database query failed for product images (product_detail.php): " . $conn->error);
            }

        } else {
            error_log("Product with ID " . $product_id . " not found (product_detail.php).");
             // Redirect to a 404 page or product list if product not found
             // header("Location: product_not_found.php"); // Create this page
             // exit();
        }
        $result->free(); // Free the product result set
        $stmt->close();
    } else {
        error_log("Database query failed to prepare product detail query (product_detail.php): " . $conn->error);
        // $_SESSION['error_message'] = "Database error retrieving product details.";
    }
} else {
     // If product ID is missing or invalid in the URL
     // header("Location: product.php"); // Redirect to product listing
     // exit();
     $message = "Invalid product ID."; // Set a message to display
}


// --- Profile picture & user info (for header) ---
$profile_picture = "images/user1.png"; // Default
$username = "";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Use the correct table name 'users'
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
                  error_log("Profile picture file not found: " . $profile_picture_db . " for user_id: " . $user_id);
             }
         }
         $stmt_user->close();
    } else {
        error_log("Database query failed for user profile (product_detail.php): " . $conn->error);
        // $_SESSION['error_message'] = (isset($_SESSION['error_message']) ? $_SESSION['error_message'] . " " : "") . "Could not load user info for header.";
    }
}

// --- Check if product is in wishlist (for wishlist button state) ---
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
             return $count > 0;
         } else {
             error_log("Database query failed for checking wishlist status (product_detail.php function): " . $conn->error);
             // Fallback to session check in case of DB error
             return isset($_SESSION['wishlist'][$item_id]);
         }
     } else {
         // Check session wishlist for guest users
         return isset($_SESSION['wishlist'][$item_id]); // Assumes item_id is used as key
     }
}

// Only call the function if a product was successfully fetched
$isInWishlist = false; // Default state
if ($product) {
    $isInWishlist = isProductInWishlist($product['item_id'], $conn);
}


// --- Close Database Connection ---
// Close only after all database operations are complete
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['name']) . ' | Nescare Organic Beauty' : 'Product Not Found | Nescare Organic Beauty'; ?></title>
    <link rel="stylesheet" href="product_detail.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>
<body>
    <?php // Include header - replace with your actual header include if you use one ?>
    <header>
         <div class="logo-container">
             <a href="index.php"><img src="images/logo.png" alt="Nescare Logo" class="logo"></a>
         </div>
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
                         <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="profile-pic">
                     </a>
                 </div>
             <?php else: ?>
                 <a href="login.php" class="login-btn">Login</a>
             <?php endif; ?>
         </div>
    </header>

    <main class="product-detail-container">
        <?php
        // Display session messages (success, warning, error)
        // If header is fixed, consider displaying these inside main or a container
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

        <?php if ($product): ?>
            <div class="product-gallery">
                <div class="main-image">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainProductImage">
                </div>
                <div class="thumbnail-container">
                    <div class="thumbnail active" onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> thumbnail">
                    </div>
                    <?php
                    // --- Loop and display additional images from item_images table ---
                    // Filter out the main image if it's duplicated in item_images
                    $displayed_thumbnails = [$product['image_url']]; // Keep track of images already displayed

                    foreach ($additional_images as $img_url):
                        if (!in_array($img_url, $displayed_thumbnails)): // Avoid duplicates
                           $displayed_thumbnails[] = $img_url; // Add to displayed list
                    ?>
                         <div class="thumbnail" onclick="changeImage('<?php echo htmlspecialchars($img_url); ?>', this)">
                             <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> thumbnail">
                         </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            </div>

            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                <div class="product-meta">
                    <div class="rating">
                        <?php // TODO: Replace with dynamic rating calculation from a 'reviews' table ?>
                        <div class="stars">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                        </div>
                        <span class="review-count">(TODO: Dynamic Review Count)</span>
                    </div>
                    <div class="price">$<?php echo htmlspecialchars(number_format($product['price'], 2)); ?></div>
                </div>

                <div class="product-description">
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    <ul class="benefits-list">
                        <li><i class="fas fa-check"></i> 100% organic ingredients</li>
                        <li><i class="fas fa-check"></i> Cruelty-free & vegan</li>
                        <li><i class="fas fa-check"></i> Suitable for all skin types</li>
                        <li><i class="fas fa-check"></i> Free from parabens and sulfates</li>
                    </ul>
                </div>

                <div class="product-variants">
                    <?php // TODO: Implement dynamic variants based on a 'product_variants' table ?>
                    <div class="variant-option">
                         <label for="size">Size</label>
                         <select id="size" name="size">
                             <option value="30ml">30ml ($<?php echo htmlspecialchars(number_format($product['price'], 2)); ?>)</option>
                             <option value="50ml">50ml (Price varies)</option>
                         </select>
                    </div>
                </div>

                <div class="quantity-selector">
                    <label for="quantity">Quantity</label>
                    <div class="quantity-control">
                        <button class="quantity-btn minus"><i class="fas fa-minus"></i></button>
                        <input type="number" id="quantity" name="quantity" value="1" min="1">
                        <button class="quantity-btn plus"><i class="fas fa-plus"></i></button>
                    </div>
                </div>

                <div class="product-actions">
                     <form action="add_to_cart.php" method="post" style="display:inline-block;">
                         <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($product['item_id']); ?>">
                         <input type="hidden" name="quantity" id="cart-quantity-input" value="1">
                         <button type="submit" class="add-to-cart-btn">
                             <i class="fas fa-shopping-basket"></i> Add to Cart
                         </button>
                     </form>
                     <a href="add_to_wishlist.php?item_id=<?php echo htmlspecialchars($product['item_id']); ?>"
                        class="wishlist-btn <?php echo $isInWishlist ? 'active' : ''; ?>">
                          <i class="fas fa-heart"></i> <?php echo $isInWishlist ? 'In Wishlist' : 'Add to Wishlist'; ?>
                     </a>
                </div>

                <div class="product-details-accordion">
                    <div class="accordion-item">
                        <button class="accordion-header"><span>Ingredients</span><i class="fas fa-chevron-down"></i></button>
                        <div class="accordion-content"><p><?php echo nl2br(htmlspecialchars($product['ingredients'])); ?></p></div>
                    </div>
                    <div class="accordion-item">
                        <button class="accordion-header"><span>How To Use</span><i class="fas fa-chevron-down"></i></button>
                        <div class="accordion-content"><p><?php echo nl2br(htmlspecialchars($product['how_to_use'])); ?></p></div>
                    </div>
                    <div class="accordion-item">
                        <button class="accordion-header"><span>Shipping & Returns</span><i class="fas fa-chevron-down"></i></button>
                        <div class="accordion-content"><p><?php echo nl2br(htmlspecialchars($product['shipping_returns_info'])); ?></p></div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="product-not-found">
                <h2>Product Not Found</h2>
                 <?php if (isset($message)): ?>
                     <p><?php echo htmlspecialchars($message); ?></p>
                 <?php else: ?>
                     <p>The product you are looking for does not exist or is currently unavailable.</p>
                 <?php endif; ?>
                <a href="product.php" class="btn">Back to Shop</a>
            </div>
        <?php endif; ?>
    </main>

    <?php // Static Related Products Placeholder ?>
    <section class="related-products">
        <h2>You May Also Like</h2>
        <div class="product-grid">
             <div class="product">
                 <div class="product-actions"><a href="#" title="Add to Wishlist"><i class="fas fa-heart"></i></a><a href="#" title="Add to Cart"><i class="fas fa-shopping-basket"></i></a></div>
                 <img src="images/product2.png" alt="Hydrating Moisturizer">
                 <div class="product-info"><h3>Hydrating Moisturizer</h3><p>Daily moisturizer with aloe vera and jojoba oil</p><p class="price">$24.99</p></div>
             </div>
             </div>
    </section>

    <?php // Static Customer Reviews Placeholder ?>
    <section class="product-reviews">
        <h2>Customer Reviews</h2>
        <div class="reviews-container">
             <div class="review">
                 <div class="reviewer-info"><img src="https://randomuser.me/api/portraits/women/32.jpg" alt="Sarah J." class="reviewer-avatar"><div class="reviewer-meta"><h4>Sarah J.</h4><div class="review-rating"><div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div><span class="review-date">April 15, 2025</span></div></div></div>
                 <div class="review-content"><h5>Amazing results!</h5><p>I've been using this serum for 3 weeks and already see a noticeable difference in my skin tone. My dark spots are fading and my complexion looks brighter. It absorbs quickly without leaving any sticky residue.</p></div>
             </div>
            </div>
        <button class="view-all-reviews">View All Reviews (Static 42)</button>
    </section>

    <?php // Include footer - replace with your actual footer include ?>
    <footer>
         <div><h3>Shop</h3><ul><li><a href="#">Skincare</a></li><li><a href="#">Makeup</a></li><li><a href="#">Hair Care</a></li><li><a href="#">Body Care</a></li><li><a href="#">Gift Sets</a></li></ul></div>
         <div><h3>About</h3><ul><li><a href="#">Our Story</a></li><li><a href="#">Ingredients</a></li><li><a href="#">Sustainability</a></li><li><a href="#">Blog</a></li><li><a href="#">Press</a></li></ul></div>
         <div><h3>Help</h3><ul><li><a href="#">Contact Us</a></li><li><a href="#">FAQs</a></li><li><a href="#">Shipping</a></li><li><a href="#">Returns</a></li><li><a href="#">Track Order</a></li></ul></div>
         <div><h3>Connect</h3><ul><li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li><li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li><li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li><li><a href="#"><i class="fab fa-pinterest"></i> Pinterest</a></li></ul></div>
         <div class="copyright"><p>&copy; 2025 Nescare. All rights reserved.</p></div>
    </footer>

    <script>
        // JavaScript for quantity selector
        const quantityInput = document.getElementById('quantity');
        const cartQuantityInput = document.getElementById('cart-quantity-input'); // Link to hidden form input
        if(quantityInput && cartQuantityInput) {
             // Update hidden input when visible input changes
            quantityInput.addEventListener('change', function() {
                 // Ensure value is at least 1
                if (parseInt(this.value) < 1 || isNaN(parseInt(this.value))) {
                    this.value = 1;
                }
                cartQuantityInput.value = this.value;
            });
            // Add event listeners to plus/minus buttons
             const minusBtn = document.querySelector('.quantity-btn.minus');
             const plusBtn = document.querySelector('.quantity-btn.plus');

             if (minusBtn && plusBtn) {
                 minusBtn.addEventListener('click', function() {
                     const currentValue = parseInt(quantityInput.value);
                     if (currentValue > 1) {
                         quantityInput.value = currentValue - 1;
                         cartQuantityInput.value = quantityInput.value; // Update hidden input
                     }
                 });

                 plusBtn.addEventListener('click', function() {
                     const currentValue = parseInt(quantityInput.value);
                     quantityInput.value = currentValue + 1;
                     cartQuantityInput.value = quantityInput.value; // Update hidden input
                 });
             } else {
                 console.error("Quantity control buttons not found.");
             }

        } else {
             console.error("Quantity input elements not found.");
        }

        // JavaScript for changing main image from thumbnails
        function changeImage(src, clickedThumbnail) {
            document.getElementById('mainProductImage').src = src;

            // Remove 'active' class from all thumbnails
            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach(thumb => thumb.classList.remove('active'));

            // Add 'active' class to the clicked thumbnail
            if (clickedThumbnail) {
                clickedThumbnail.classList.add('active');
            } else {
                // If clickedThumbnail is not passed (e.g., on page load),
                // find the thumbnail matching the initial main image source and make it active.
                 document.querySelectorAll('.thumbnail img').forEach(img => {
                     // Use endsWith for robustness if paths might differ slightly
                     if (img.src.endsWith(src)) {
                         img.parentElement.classList.add('active');
                     }
                 });
            }
        }

        // Initialize thumbnail active state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const initialMainImageSrc = document.getElementById('mainProductImage').src;
            changeImage(initialMainImageSrc); // Call changeImage to set the initial active thumbnail

             // JavaScript for accordion functionality
             const accordionHeaders = document.querySelectorAll('.accordion-header');
             accordionHeaders.forEach(header => {
                 header.addEventListener('click', function() {
                     const accordionItem = this.parentElement; // Get the parent accordion-item
                     const accordionContent = this.nextElementSibling;
                     const icon = this.querySelector('i');

                     // Close all other open accordions (optional)
                     document.querySelectorAll('.accordion-item.active').forEach(item => {
                         if (item !== accordionItem) {
                             item.classList.remove('active');
                             item.querySelector('.accordion-content').style.display = 'none';
                             item.querySelector('.accordion-header i').classList.remove('fa-chevron-up');
                             item.querySelector('.accordion-header i').classList.add('fa-chevron-down');
                         }
                     });

                     // Toggle the clicked accordion
                     const isVisible = accordionContent.style.display === 'block';
                     accordionContent.style.display = isVisible ? 'none' : 'block';

                     accordionItem.classList.toggle('active', !isVisible); // Add active class to item

                     icon.classList.toggle('fa-chevron-down', isVisible);
                     icon.classList.toggle('fa-chevron-up', !isVisible);
                 });
             });
             // Initially hide all accordion content
             document.querySelectorAll('.accordion-content').forEach(content => { content.style.display = 'none'; });
        });

    </script>
</body>
</html>
