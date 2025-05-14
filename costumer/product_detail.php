<?php
session_start();

require_once 'db_connection.php'; // Make sure this path is correct

// Initialize variables
$product = null;
$additional_images = [];
$other_products = [];
$isInWishlist = false;
$profile_picture = "images/user1.png"; // Default profile picture
$username = ""; // Default username
$error_message = "";

// Validate and sanitize product ID from GET request
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

// Redirect if product ID is invalid or missing
if (!$product_id) {
    error_log("Invalid or missing product ID in product_detail.php");
    $error_message = "Invalid product ID provided.";
    // Consider a redirect to a 404 page or homepage here in a real application
}

// Database connection handling
$conn = false; // Initialize $conn to false
try {
    require 'db_connection.php'; // Include the connection file
    if ($conn === false || $conn->connect_error) {
         // If $conn was not set or connection failed during include
        throw new Exception("Database Connection failed: " . ($conn ? $conn->connect_error : "Unknown error during connection include"));
    }

    if ($product_id) {
        $sql = "SELECT * FROM items WHERE item_id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Database error preparing product query: " . $conn->error);
        }

        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error_message = "Product not found.";
        } else {
            $product = $result->fetch_assoc();

            // Fetch additional images
            $sql_images = "SELECT image_url FROM item_images WHERE item_id = ? ORDER BY is_main DESC, image_id ASC";
            $stmt_images = $conn->prepare($sql_images);

            if ($stmt_images) {
                $stmt_images->bind_param("i", $product_id);
                $stmt_images->execute();
                $result_images = $stmt_images->get_result();

                while ($row_image = $result_images->fetch_assoc()) {
                    $additional_images[] = $row_image['image_url'];
                }
                $stmt_images->close();
            } else {
                 error_log("Database error preparing item_images query: " . $conn->error);
            }
        }
        $stmt->close();
    }

    // Fetch other products only if a product was found
    if ($product) {
        // Attempt to select items from the same category first
        $category = $product['category_id'] ?? null; // Use null coalescing for safety
        $limit = 4;
        $excluded_ids = [$product_id]; // Always exclude the current product
        $other_products = []; // Initialize the array

        if ($category) {
             $sql_other_category = "SELECT item_id, name, price, image_url FROM items WHERE item_id != ? AND stock > 0 AND category_id = ? ORDER BY RAND() LIMIT ?";
             $stmt_other_category = $conn->prepare($sql_other_category);
             if ($stmt_other_category) {
                $stmt_other_category->bind_param("iii", $product_id, $category, $limit);
                $stmt_other_category->execute();
                $result_other_category = $stmt_other_category->get_result();
                 while ($row = $result_other_category->fetch_assoc()) {
                    $other_products[] = $row;
                    $excluded_ids[] = $row['item_id']; // Add found IDs to exclusion list
                }
                $stmt_other_category->close();
             } else {
                 error_log("Database error preparing other products (category) query: " . $conn->error);
             }
        }

        // If not enough products from the same category, fill with random ones
        if (count($other_products) < $limit) {
             $needed = $limit - count($other_products);

             // Handle the IN clause with potentially dynamic number of placeholders
             if (!empty($excluded_ids)) {
                 $excluded_placeholders = implode(',', array_fill(0, count($excluded_ids), '?'));
                 $sql_other_random = "SELECT item_id, name, price, image_url FROM items WHERE item_id NOT IN (" . $excluded_placeholders . ") AND stock > 0 ORDER BY RAND() LIMIT ?";
                 $stmt_other_random = $conn->prepare($sql_other_random);

                 if ($stmt_other_random) {
                      // Prepare types string and bind parameters array
                     $types = str_repeat('i', count($excluded_ids)) . 'i';
                     $bind_params = array_merge($excluded_ids, [$needed]);

                     // Use call_user_func_array to bind parameters dynamically
                     $stmt_other_random->bind_param($types, ...$bind_params);

                     $stmt_other_random->execute();
                     $result_other_random = $stmt_other_random->get_result();
                     while ($row = $result_other_random->fetch_assoc()) {
                        $other_products[] = $row;
                     }
                     $stmt_other_random->close();

                 } else {
                      error_log("Database error preparing other products (random fill) query: " . $conn->error);
                       // Fallback if the prepared statement failed
                       // (Could add a simpler random query here if needed, but logging the error is crucial)
                 }
             } else {
                 // This case should ideally not happen if $product_id is valid, but handle defensively
                  $sql_other_fallback = "SELECT item_id, name, price, image_url FROM items WHERE stock > 0 ORDER BY RAND() LIMIT ?";
                  $stmt_other_fallback = $conn->prepare($sql_other_fallback);
                  if($stmt_other_fallback) {
                      $stmt_other_fallback->bind_param("i", $limit);
                      $stmt_other_fallback->execute();
                      $result_other_fallback = $stmt_other_fallback->get_result();
                       while ($row = $result_other_fallback->fetch_assoc()) {
                            $other_products[] = $row;
                       }
                       $stmt_other_fallback->close();
                  } else {
                       error_log("Database error preparing other products (empty excluded fallback) query: " . $conn->error);
                  }

             }

             // Ensure unique products in case of overlap (unlikely with correct exclusion)
             $other_products = array_unique($other_products, SORT_REGULAR);

             // Shuffle final list and trim to exactly $limit
             shuffle($other_products);
             $other_products = array_slice($other_products, 0, $limit);

        }
    }


    // Check if product is in wishlist
    if (isset($_SESSION['user_id']) && $product) {
        $user_id = $_SESSION['user_id'];
        $sql_wishlist = "SELECT COUNT(*) FROM favorites WHERE user_id = ? AND item_id = ?";
        $stmt_wishlist = $conn->prepare($sql_wishlist);

        if ($stmt_wishlist) {
            $stmt_wishlist->bind_param("ii", $user_id, $product['item_id']);
            $stmt_wishlist->execute();
            $stmt_wishlist->bind_result($count);
            $stmt_wishlist->fetch();
            $isInWishlist = $count > 0;
            $stmt_wishlist->close();
        } else {
             error_log("Database error preparing wishlist query: " . $conn->error);
        }
    }

    // Fetch user profile picture and username if logged in (for potential future comment form)
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt_user = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");

        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $stmt_user->bind_result($profile_picture_db, $username_db);

            if ($stmt_user->fetch()) {
                $username = htmlspecialchars($username_db);
                if (!empty($profile_picture_db)) {
                    $profile_picture = htmlspecialchars($profile_picture_db);
                }
            }
            $stmt_user->close();
        } else {
             error_log("Database error preparing user info query: " . $conn->error);
        }
    }

} catch (Exception $e) {
    error_log("Error in product_detail.php: " . $e->getMessage());
    $error_message = "An error occurred while loading the product details. Please try again later.";
} finally {
    // Close connection only if it was successfully opened and is a mysqli object
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? htmlspecialchars($product['name']) . ' | Nescare' : 'Product Not Found | Nescare' ?></title>
    <link rel="stylesheet" href="product_details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<?php require_once 'header.php'; ?>

<main class="product-detail-container">
    <?php if (!empty($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php elseif ($product): ?>
        <div class="product-top-section">
            <div class="product-gallery">
                <div class="main-image">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="mainProductImage" onerror="this.src='images/default-product.jpg'">
                </div>

                <?php if (!empty($additional_images)): ?>
                    <div class="thumbnail-container">
                        <div class="thumbnail active" onclick="changeImage('<?= htmlspecialchars($product['image_url']) ?>', this)">
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?> thumbnail" onerror="this.src='images/default-product-thumb.jpg'">
                        </div>
                        <?php foreach ($additional_images as $img_url): ?>
                            <div class="thumbnail" onclick="changeImage('<?= htmlspecialchars($img_url) ?>', this)">
                                <img src="<?= htmlspecialchars($img_url) ?>" alt="<?= htmlspecialchars($product['name']) ?> thumbnail" onerror="this.src='images/default-product-thumb.jpg'">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                <div class="product-meta">
                    <div class="price">$<?= number_format($product['price'], 2) ?></div>
                    <div class="stock-status">
                        <?php if ($product['stock'] > 0): ?>
                            <span class="in-stock"><i class="fas fa-check-circle"></i> In Stock (<?= $product['stock'] ?>)</span>
                        <?php else: ?>
                            <span class="out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="product-description">
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <div class="quantity-selector">
                        <label for="quantity">Quantity</label>
                        <div class="quantity-control">
                            <button type="button" class="quantity-btn minus"><i class="fas fa-minus"></i></button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= max(1, $product['stock']) ?>">
                            <button type="button" class="quantity-btn plus"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="product-actions">
                    <?php if ($product['stock'] > 0): ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <form action="add_to_cart.php" method="post" class="add-to-cart-form">
                                <input type="hidden" name="item_id" value="<?= $product['item_id'] ?>">
                                <input type="hidden" name="quantity" id="cart-quantity-input" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i class="fas fa-shopping-basket"></i> Add to Cart
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="login.php?redirect=product_detail.php?id=<?= $product['item_id'] ?>" class="add-to-cart-btn">
                                <i class="fas fa-shopping-basket"></i> Login to Add to Cart
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form action="toggle_wishlist.php" method="post" class="wishlist-form">
                            <input type="hidden" name="item_id" value="<?= $product['item_id'] ?>">
                            <button type="submit" class="wishlist-btn <?= $isInWishlist ? 'active' : '' ?>">
                                <i class="fas fa-heart"></i>
                                <?= $isInWishlist ? 'In Wishlist' : 'Add to Wishlist' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="login.php?redirect=product_detail.php?id=<?= $product['item_id'] ?>" class="wishlist-btn">
                            <i class="fas fa-heart"></i> Login to Add to Wishlist
                        </a>
                    <?php endif; ?>
                </div>

                 <?php if (!empty($product['ingredients'])): ?>
                    <div class="product-details-section ingredients-section">
                        <h3 class="toggle-header" onclick="toggleSection('ingredients-content')">
                            Ingredients
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </h3>
                        <div id="ingredients-content" class="toggle-content">
                            <p><?= nl2br(htmlspecialchars($product['ingredients'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($product['how_to_use'])): ?>
                    <div class="product-details-section howtouse-section">
                        <h3 class="toggle-header" onclick="toggleSection('howtouse-content')">
                            How to Use
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </h3>
                        <div id="howtouse-content" class="toggle-content">
                            <p><?= nl2br(htmlspecialchars($product['how_to_use'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

            </div> </div> <?php if (!empty($other_products)): ?>
            <section class="related-products-section">
                <h2>You might also like</h2>
                <div class="products-grid">
                    <?php foreach ($other_products as $other_product): ?>
                        <a href="product_detail.php?id=<?= $other_product['item_id'] ?>" class="product-card">
                             <div class="product-card-image-container">
                                <img src="<?= htmlspecialchars($other_product['image_url']) ?>" alt="<?= htmlspecialchars($other_product['name']) ?>" onerror="this.src='images/default-product-thumb.jpg'">
                                </div>
                             <div class="product-card-info">
                                <h4><?= htmlspecialchars($other_product['name']) ?></h4>
                                <p class="product-card-description">Discover a hydrating solution for your skin...</p>
                                <p class="price">$<?= number_format($other_product['price'], 2) ?></p>
                             </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="customer-reviews-section">
            <h2>Customer Reviews </h2>
            <div class="reviews-list">
                <div class="review">
                    <div class="review-header">
                        <img src="https://randomuser.me/api/portraits/women/75.jpg" alt="Reviewer Avatar" class="reviewer-avatar">
                        <div class="reviewer-info">
                            <span class="reviewer-name">Anya S.</span>
                            <div class="rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i> (5/5)
                            </div>
                        </div>
                    </div>
                    <p class="review-text">This is exactly what my routine was missing! My skin feels so much smoother and looks visibly brighter. Will repurchase!</p>
                     <span class="review-date">Reviewed on May 5, 2025</span>
                </div>

                <div class="review">
                     <div class="review-header">
                        <img src="https://randomuser.me/api/portraits/women/81.jpg" alt="Reviewer Avatar" class="reviewer-avatar">
                        <div class="reviewer-info">
                            <span class="reviewer-name">Chloe L.</span>
                             <div class="rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i> (4.5/5)
                            </div>
                        </div>
                     </div>
                    <p class="review-text">Pretty good results! I've been using it for two weeks and can see a difference. It absorbed well and didn't irritate my sensitive skin.</p>
                    <span class="review-date">Reviewed on May 2, 2025</span>
                </div>

                 <div class="review">
                    <div class="review-header">
                        <img src="https://randomuser.me/api/portraits/women/63.jpg" alt="Reviewer Avatar" class="reviewer-avatar">
                        <div class="reviewer-info">
                            <span class="reviewer-name">Isabelle R.</span>
                             <div class="rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                 <i class="far fa-star"></i> (4/5)
                            </div>
                        </div>
                    </div>
                    <p class="review-text">Nice product, feels light on the skin. The packaging is sleek. Knocked off half a star because I wish the effects were a bit faster.</p>
                     <span class="review-date">Reviewed on April 29, 2025</span>
                </div>

                 <div class="review">
                    <div class="review-header">
                        <img src="https://randomuser.me/api/portraits/men/47.jpg" alt="Reviewer Avatar" class="reviewer-avatar">
                        <div class="reviewer-info">
                            <span class="reviewer-name">Daniel K.</span>
                             <div class="rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                                 <i class="far fa-star"></i> (3.5/5)
                            </div>
                        </div>
                    </div>
                    <p class="review-text">It's okay, noticed slight improvement. Texture is a bit unusual.</p>
                     <span class="review-date">Reviewed on April 20, 2025</span>
                </div>

                 <div class="review">
                    <div class="review-header">
                        <img src="https://randomuser.me/api/portraits/women/19.jpg" alt="Reviewer Avatar" class="reviewer-avatar">
                        <div class="reviewer-info">
                            <span class="reviewer-name">Olivia P.</span>
                             <div class="rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                 <i class="fas fa-star"></i> (5/5)
                            </div>
                        </div>
                    </div>
                    <p class="review-text">Fantastic! Absorbed quickly and left no sticky residue. My skin feels hydrated all day. Highly recommend!</p>
                     <span class="review-date">Reviewed on April 18, 2025</span>
                </div>


            </div>
        </section>


    <?php endif; ?>
</main>

<?php require_once 'footer.php'; ?>

<script>
// Image switching logic
function changeImage(src, element) {
    document.getElementById('mainProductImage').src = src;
    document.querySelectorAll('.thumbnail').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
}

// Quantity selector logic
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');
    const cartQuantityInput = document.getElementById('cart-quantity-input'); // Hidden input for the form

    if (quantityInput && minusBtn && plusBtn && cartQuantityInput) {
        minusBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue > parseInt(quantityInput.min)) {
                quantityInput.value = currentValue - 1;
                cartQuantityInput.value = quantityInput.value; // Update hidden input
            }
        });

        plusBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue < parseInt(quantityInput.max)) {
                quantityInput.value = currentValue + 1;
                 cartQuantityInput.value = quantityInput.value; // Update hidden input
            }
        });

         // Ensure hidden input is updated if user types manually
        quantityInput.addEventListener('input', function() {
             // Sanitize input: ensure it's a number within min/max range
             let value = parseInt(this.value);
             const min = parseInt(this.min);
             const max = parseInt(this.max);

             if (isNaN(value) || value < min) {
                 value = min;
             } else if (value > max) {
                 value = max;
             }
             this.value = value;
             cartQuantityInput.value = value;
        });
         // Prevent invalid input like '-', '.' etc.
         quantityInput.addEventListener('keypress', function(event) {
            // Allow only digits
            if (event.key < '0' || event.key > '9') {
                event.preventDefault();
            }
        });

    }

     // Initial update for the hidden input value
    if(quantityInput && cartQuantityInput) {
         cartQuantityInput.value = quantityInput.value;
    }
});


// Section toggle logic
function toggleSection(contentId) {
    const content = document.getElementById(contentId);
    // Find the .toggle-header sibling that directly precedes the content div
    const header = content.previousElementSibling;
    const icon = header ? header.querySelector('.toggle-icon') : null;

    // Ensure we found the elements
    if (!content || !header) {
        console.error("Toggle elements not found for ID:", contentId);
        return;
    }

    if (content.classList.contains('active')) {
        content.classList.remove('active');
        // Use scrollHeight before setting max-height to 0 for smooth collapse
        content.style.maxHeight = content.scrollHeight + "px"; // Set current height
         requestAnimationFrame(() => {
             content.style.maxHeight = '0'; // Collapse
         });


        // Add transition end listener to hide overflow/opacity after collapse animation
        const collapseEndHandler = () => {
             content.style.overflow = 'hidden';
             content.style.opacity = '0';
             content.removeEventListener('transitionend', collapseEndHandler);
        };
        // Listen for the end of the max-height transition
        content.addEventListener('transitionend', collapseEndHandler, { once: true });


        if (icon) {
             icon.classList.remove('fa-chevron-up');
             icon.classList.add('fa-chevron-down');
        }
    } else {
        content.classList.add('active');
         // Set initial styles before expanding for transition
         content.style.overflow = 'hidden'; // Ensure overflow is hidden initially
         content.style.opacity = '0'; // Ensure opacity is 0 initially
         content.style.maxHeight = '0'; // Start from 0 max height


        // Request a frame to ensure styles are applied before triggering transition
        requestAnimationFrame(() => {
             requestAnimationFrame(() => {
                // Calculate height dynamically and expand
                content.style.maxHeight = content.scrollHeight + "px";
                 content.style.opacity = '1'; // Fade in
                 // Remove overflow hidden after expand animation completes
                 const expandEndHandler = () => {
                    content.style.overflow = ''; // Remove overflow constraint
                    content.removeEventListener('transitionend', expandEndHandler);
                 };
                 // Listen for the end of the max-height transition
                 content.addEventListener('transitionend', expandEndHandler, { once: true });
             });
        });


         if (icon) {
             icon.classList.remove('fa-chevron-down');
             icon.classList.add('fa-chevron-up');
         }
    }
}

// Optional: Adjust max-height on window resize for toggled sections
// This ensures the section expands correctly if screen size changes while it's open
window.addEventListener('resize', function() {
     // Use a small delay to ensure scrollHeight is calculated after resize is potentially finished
     setTimeout(() => {
         document.querySelectorAll('.toggle-content.active').forEach(content => {
             // Temporarily remove transition to avoid animation during resize
             content.style.transition = 'none';
             // Reset max-height based on new scrollHeight
             content.style.maxHeight = content.scrollHeight + "px";
             // Use requestAnimationFrame to re-apply transition after repaint
             requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                   content.style.transition = 'max-height 0.6s ease-in-out, opacity 0.6s ease-in-out';
                });
             });
         });
     }, 50); // Small delay in ms
});


// Set initial state (closed) for toggled sections on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-content').forEach(content => {
        content.style.maxHeight = '0'; // Collapse them initially
        content.style.opacity = '0'; // Hide initially
        content.style.overflow = 'hidden'; // Hide overflow initially
        content.classList.remove('active'); // Ensure active class is not present initially
         // Ensure icons are pointing down
         // Find the preceding h3 with .toggle-header
         let header = content.previousElementSibling;
         if (header && header.classList.contains('toggle-header')) {
             const icon = header.querySelector('.toggle-icon');
             if (icon) {
                 icon.classList.remove('fa-chevron-up');
                 icon.classList.add('fa-chevron-down');
             }
         }
    });
});


</script>

</body>
</html>