<?php
session_start();
require_once 'db_connection.php';

$profile_picture = "images/user1.png";
$username = "";

if ($conn && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt_user = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $stmt_user->bind_result($profile_picture_db, $username_db);
         if ($stmt_user->fetch()) {
             $username = $username_db;

             if (!empty($profile_picture_db) && file_exists($profile_picture_db)) {
                 $profile_picture = $profile_picture_db;
             } elseif (!empty($profile_picture_db)) {
                 error_log("Profile picture file not found: " . $profile_picture_db . " for user_id: " . $user_id);
             }
         } else {
             error_log("User ID " . $user_id . " from session not found in users table (landing page).");
         }
         $stmt_user->close();
    } else {
        error_log("Database query failed for user profile (landing page): " . $conn->error);
    }
}

// --- Fetch Categories (for filter dropdown) ---
$categories_result = false;
if ($conn) {
    $categories_sql = "SELECT category_id, name FROM categories ORDER BY name ASC";
    $categories_result = $conn->query($categories_sql);
    if (!$categories_result) {
        error_log("Error fetching categories (landing page): " . $conn->error);
    }
}


// --- Fetch Products (a limited number for the landing page) ---
$products = [];
if ($conn) {
    $products_sql = "SELECT item_id, name, description, price, image_url FROM items ORDER BY created_at DESC LIMIT 4";
    $products_result = $conn->query($products_sql);
    if ($products_result) {
        while ($row = $products_result->fetch_assoc()) {
            $products[] = $row;
        }
         if ($products_result) $products_result->free();
    } else {
        error_log("Error fetching products (landing page): " . $conn->error);
    }
}


// --- Wishlist check function (checks session for guests, DB for logged-in) ---
function isProductInWishlist($item_id, $conn) {
     if (!$conn || !isset($_SESSION['user_id'])) {
         if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
             $_SESSION['wishlist'] = [];
         }
         return isset($_SESSION['wishlist'][$item_id]);
     }

     $user_id = $_SESSION['user_id'];
     $stmt = $conn->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND item_id = ?");
     if ($stmt) {
         $stmt->bind_param("ii", $user_id, $item_id);
         $stmt->execute();
         $stmt->bind_result($count);
         $stmt->fetch();
         $stmt->close();
         return $count > 0;
     } else {
         error_log("Database query failed for checking wishlist status (landing page function): " . $conn->error);
         if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
             $_SESSION['wishlist'] = [];
         }
         return isset($_SESSION['wishlist'][$item_id]);
     }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nescare | Organic Beauty Products</title>
    <link rel="stylesheet" href="landing.css">
    <link rel="stylesheet" href="products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>
<body>
<?php require_once 'header.php' ?>
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
     // Display database connection error if applicable
     if (isset($db_error)): ?>
          <div class="message error">
              <?php echo htmlspecialchars($db_error); ?>
              <span class="close-btn">×</span>
          </div>
      <?php endif; ?>


     <section class="hero">
          <h1>Pure Beauty, Naturally</h1>
          <p>Discover our certified organic skincare products, crafted with love and the purest ingredients nature has to offer.</p>
          <a href="#products-section" class="btn">Shop Now</a>
     </section>

     <section class="features">
          <div class="feature-card">
               <i class="fas fa-leaf"></i>
               <h3>100% Organic</h3>
               <p>All our products are made with certified organic ingredients, free from harmful chemicals.</p>
          </div>
          <div class="feature-card">
               <i class="fas fa-heart"></i>
               <h3>Cruelty-Free</h3>
               <p>We never test on animals. Our products are ethically produced with love for all living beings.</p>
          </div>
          <div class="feature-card">
               <i class="fas fa-recycle"></i>
               <h3>Sustainable Packaging</h3>
               <p>We use biodegradable and recyclable materials to minimize our environmental footprint.</p>
          </div>
     </section>

     <section id="products-section" class="products-section">
          <div class="title-filter-bar">
               <h2 class="products-title">Our Products</h2>
               <div class="filter-dropdown">
                    <i class="fas fa-filter filter-icon" onclick="toggleDropdown(event)" title="Filter by category"></i> <div id="categoryList" class="dropdown-content">
                         <a href="product.php">All Categories</a>
                         <?php
                         // Check if categories were fetched successfully and iterate
                         if ($categories_result && $categories_result->num_rows > 0):
                             $categories_result->data_seek(0); // Reset pointer if needed before looping
                             while($cat = $categories_result->fetch_assoc()):
                         ?>
                         <a href="product.php?category=<?php echo htmlspecialchars($cat['category_id']); ?>">
                              <?php echo htmlspecialchars($cat['name']); ?>
                         </a>
                         <?php
                             endwhile;
                         else: ?>
                         <span>No categories found.</span>
                         <?php endif;
                         // $categories_result is freed in the PHP section
                         ?>
                    </div>
               </div>
          </div>

          <div class="product-grid">
               <?php
               // Check if products were fetched successfully and the array is not empty
               if (!empty($products)):
                    // Loop through the fetched products and display them
                    foreach ($products as $product):
                         $item_id = $product['item_id'];
                         // Check if product is in wishlist (requires user login and valid connection)
                         $isInWishlist = ($conn && isset($_SESSION['user_id'])) ? isProductInWishlist($item_id, $conn) : false; // Pass $conn correctly

                         // Use product image_url, fallback to default
                         $image_path = !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : 'images/default_product.png';
                         $image_alt = !empty($product['name']) ? htmlspecialchars($product['name']) : 'Product Image';

                         // Determine the link destination based on login status for product actions
                         $cart_link_href = isset($_SESSION['user_id']) ? "add_to_cart.php?item_id={$item_id}&quantity=1" : "login.php?redirect=landing.php";
                         $wishlist_link_href = isset($_SESSION['user_id']) ? "add_to_wishlist.php?item_id={$item_id}" : "login.php?redirect=landing.php";
               ?>
                    <div class="product" data-product-id="<?php echo htmlspecialchars($item_id); ?>">
                         <?php if (isset($_SESSION['user_id'])): ?>
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
                         <?php else: ?>
                         <div class="product-actions">
                              <a href="login.php?redirect=landing.php" class="wishlist-icon" title="Login to add to wishlist">
                                   <i class="fas fa-heart"></i>
                              </a>
                              <a href="login.php?redirect=landing.php" class="cart-icon" title="Login to add to cart">
                                   <i class="fas fa-shopping-basket"></i>
                              </a>
                         </div>
                         <?php endif; ?>
                         <img src="<?php echo $image_path; ?>" alt="<?php echo $image_alt; ?>">
                         <div class="product-info">
                              <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                              <p><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?></p>
                              <p class="price">$<?php echo htmlspecialchars(number_format((float)$product['price'], 2)); ?></p>
                         </div>
                    </div>
               <?php
                    endforeach;
               elseif ($conn && empty($products)): // Check if connection was successful but no products found
               ?>
                    <p style="grid-column: 1 / -1; text-align: center; padding: 20px;">No products found.</p>
               <?php
               else: // Handle case where DB connection failed
               ?>
                    <p style="grid-column: 1 / -1; text-align: center; padding: 20px; color: red;">Could not load products due to a database error.</p>
               <?php
               endif;
               ?>
          </div>
     </section>

     <section class="video-section" id="about-section">
    <h2>Our Story</h2>
    <p>Discover the Nescare difference</p>
    <div class="video-container">
        <iframe src="https://www.youtube.com/embed/otej7WLdPh0?si=TtzB6ljXJSaPJaj-"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                title="Nescare - Our Story">
        </iframe>
    </div>
</section>

     <section class="testimonials">
          <h2>What Our Customers Say</h2>
          <p>Real results from real people who love our products</p>

          <div class="testimonial-grid">
               <div class="testimonial-card">
                    <img src="https://randomuser.me/api/portraits/women/32.jpg" alt="Sarah J.">
                    <p>"I've struggled with sensitive skin my whole life. Nescare's products are the only ones that don't cause irritation."</p>
                    <h4>Sarah J.</h4>
                    <div class="stars">
                         <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
               </div>
               <div class="testimonial-card">
                    <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Maria L.">
                    <p>"The glow I get from their vitamin C serum is unreal. My coworkers keep asking what I'm using!"</p>
                    <h4>Maria L.</h4>
                    <div class="stars">
                         <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
               </div>
               <div class="testimonial-card">
                    <img src="https://randomuser.me/api/portraits/men/75.jpg" alt="David K.">
                    <p>"As someone who cares about skincare and the environment, Nescare checks all my boxes."</p>
                    <h4>David K.</h4>
                    <div class="stars">
                         <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                    </div>
               </div>
          </div>
     </section>

     <?php require_once 'footer.php' ?>

     <script>
          // JavaScript for category filter dropdown
          function toggleDropdown(event) {
               event.stopPropagation();
               const dropdown = document.getElementById("categoryList");
               dropdown.classList.toggle("show");
          }

          // Close the dropdown if the user clicks outside of it
          window.onclick = function(event) {
               if (!event.target.matches('.filter-icon') && !event.target.closest('.dropdown-content')) {
                    const dropdowns = document.getElementsByClassName("dropdown-content");
                    for (let i = 0; i < dropdowns.length; i++) {
                         const openDropdown = dropdowns[i];
                         if (openDropdown.classList.contains('show')) {
                              openDropdown.classList.remove('show');
                         }
                    }
               }
          };

          // JavaScript for clicking product cards to go to detail page
          document.addEventListener('DOMContentLoaded', function() {
               const productCards = document.querySelectorAll('.product');

               productCards.forEach(card => {
                    card.addEventListener('click', function(event) {
                         // Prevent navigating if a link/button *inside* the card was clicked
                         if (event.target.closest('.product-actions') || event.target.tagName === 'A' || event.target.tagName === 'BUTTON' || event.target.parentElement.tagName === 'A') {
                              return;
                         }
                         const productId = this.getAttribute('data-product-id');
                         if (productId) {
                              window.location.href = 'product_detail.php?id=' + productId;
                         } else {
                              console.error("Product ID not found for this card.");
                         }
                    });
               });

               // JS for intro image cycling (optional) - Ensure CSS handles the 'active' class
               const introImages = document.querySelectorAll('.intro-image-container .intro-image');
               let currentImageIndex = 0;
               function cycleImages() {
                   if (introImages.length < 2) return;
                   introImages[currentImageIndex].classList.remove('active');
                   currentImageIndex = (currentImageIndex + 1) % introImages.length;
                   introImages[currentImageIndex].classList.add('active');
               }
               if (introImages.length > 0) {
                   introImages[0].classList.add('active'); // Set first image active initially
               }
               if (introImages.length > 1) {
                   setInterval(cycleImages, 7000); // Change image every 7 seconds
               }

              // --- JavaScript for Message Dismissal ---
              const messages = document.querySelectorAll('.message');

              messages.forEach(message => {
                  const closeBtn = message.querySelector('.close-btn');

                  // Close button click handler
                  if (closeBtn) {
                      closeBtn.addEventListener('click', function() {
                          message.style.opacity = '0'; // Start fade out
                          setTimeout(function() {
                              message.remove(); // Remove element after fade out
                          }, 500); // Match this duration to the CSS transition time
                      });
                  }

                  // Optional: Auto-dismiss after a few seconds (e.g., 5000ms = 5 seconds)
                  // You might only want this for success/warning messages, not errors
                   if (message.classList.contains('success') || message.classList.contains('warning')) {
                       setTimeout(function() {
                           message.style.opacity = '0'; // Start fade out
                           setTimeout(function() {
                               message.remove(); // Remove element after fade out
                           }, 500); // Match this duration to the CSS transition time
                       }, 5000); // Time before auto-dismiss starts (5 seconds)
                   }
              });

          }); // End DOMContentLoaded
     </script>

</body>
</html>
<?php
// --- Close database connection ---
if ($conn instanceof mysqli && !$conn->connect_error) {
    $conn->close();
    $conn = null;
}
?>