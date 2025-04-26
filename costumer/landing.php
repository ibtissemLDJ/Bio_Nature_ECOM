<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nescare | Organic Beauty Products</title>
    <link rel="stylesheet" href="landing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="images/logo.png" alt="Nescare Logo" class="logo">
        </div>
        <nav>
            <ul>
                <li><a href="#">Shop</a></li>
                <li><a href="#">About</a></li>
                <li><a href="#">Ingredients</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
        <div class="nav-right">
            <i class="fas fa-shopping-basket"></i>
            <i class="fas fa-heart"></i>
            <div class="profile-container">
                <img src="images/user1.png" alt="Profile" class="profile-pic">
            </div>
        </div>
    </header>
    
    <section class="hero">
        <h1>Pure Beauty, Naturally</h1>
        <p>Discover our certified organic skincare products, crafted with love and the purest ingredients nature has to offer.</p>
        <button class="btn">Shop Now</button>
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

    <section class="products-section">
        <div class="title-filter-bar">
            <h2 class="products-title">Our Products</h2>
            <div class="filter-dropdown">
                <i class="fas fa-filter filter-icon" onclick="toggleDropdown()" title="Filter by category"></i>
                <div id="categoryList" class="dropdown-content">
                    <a href="#">All Categories</a>
                    <a href="#">Skincare</a>
                    <a href="#">Makeup</a>
                    <a href="#">Hair Care</a>
                    <a href="#">Body Care</a>
                </div>
            </div>
        </div>

        <div class="product-grid">
            <div class="product">
                <div class="product-actions">
                    <i class="fas fa-heart"></i>
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <img src="images/product1.png" alt="Vitamin C Serum">
                <div class="product-info">
                    <h3>Vitamin C Serum</h3>
                    <p>Brightening serum with 20% vitamin C and hyaluronic acid</p>
                    <p class="price">$29.99</p>
                </div>
            </div>
            
            <div class="product">
                <div class="product-actions">
                    <i class="fas fa-heart"></i>
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <img src="images/product2.png" alt="Hydrating Moisturizer">
                <div class="product-info">
                    <h3>Hydrating Moisturizer</h3>
                    <p>Daily moisturizer with aloe vera and jojoba oil</p>
                    <p class="price">$24.99</p>
                </div>
            </div>
            
            <div class="product">
                <div class="product-actions">
                    <i class="fas fa-heart"></i>
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <img src="images/product3.png" alt="Gentle Cleanser">
                <div class="product-info">
                    <h3>Gentle Cleanser</h3>
                    <p>pH-balanced cleanser with chamomile and green tea</p>
                    <p class="price">$18.99</p>
                </div>
            </div>
            
            <div class="product">
                <div class="product-actions">
                    <i class="fas fa-heart"></i>
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <img src="images/product4.png" alt="Detox Mask">
                <div class="product-info">
                    <h3>Detox Mask</h3>
                    <p>Clay mask with activated charcoal and tea tree oil</p>
                    <p class="price">$22.99</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="video-section">
        <h2>Our Story</h2>
        <p>Discover the Nescare difference</p>
        <div class="video-container">
            <iframe src="https://www.youtube.com/embed/otej7WLdPh0?si=BBOKkUhVVgyFSeJi" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen
                    title="Nescare Brand Story">
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
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>
            </div>
            <div class="testimonial-card">
                <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Maria L.">
                <p>"The glow I get from their vitamin C serum is unreal. My coworkers keep asking what I'm using!"</p>
                <h4>Maria L.</h4>
                <div class="stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>
            </div>
            <div class="testimonial-card">
                <img src="https://randomuser.me/api/portraits/men/75.jpg" alt="David K.">
                <p>"As someone who cares about skincare and the environment, Nescare checks all my boxes."</p>
                <h4>David K.</h4>
                <div class="stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star-half-alt"></i>
                </div>
            </div>
        </div>
    </section>
    
    <footer>
        <div>
            <h3>Shop</h3>
            <ul>
                <li><a href="#">Skincare</a></li>
                <li><a href="#">Makeup</a></li>
                <li><a href="#">Hair Care</a></li>
                <li><a href="#">Body Care</a></li>
                <li><a href="#">Gift Sets</a></li>
            </ul>
        </div>
        <div>
            <h3>About</h3>
            <ul>
                <li><a href="#">Our Story</a></li>
                <li><a href="#">Ingredients</a></li>
                <li><a href="#">Sustainability</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Press</a></li>
            </ul>
        </div>
        <div>
            <h3>Help</h3>
            <ul>
                <li><a href="#">Contact Us</a></li>
                <li><a href="#">FAQs</a></li>
                <li><a href="#">Shipping</a></li>
                <li><a href="#">Returns</a></li>
                <li><a href="#">Track Order</a></li>
            </ul>
        </div>
        <div>
            <h3>Connect</h3>
            <ul>
                <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                <li><a href="#"><i class="fab fa-pinterest"></i> Pinterest</a></li>
            </ul>
        </div>
        <div class="copyright">
            <p>&copy; 2025 Nescare. All rights reserved.</p>
        </div>
    </footer>

    <script>
    function toggleDropdown() {
        const dropdown = document.getElementById("categoryList");
        dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
    }

    window.onclick = function(event) {
        if (!event.target.matches('.filter-icon')) {
            const dropdown = document.getElementById("categoryList");
            if (dropdown && dropdown.style.display === "block") {
                dropdown.style.display = "none";
            }
        }
    };

    document.querySelectorAll('.fa-shopping-basket').forEach(icon => {
        icon.addEventListener('click', function() {
            this.classList.toggle('active');
            // Add cart functionality here
        });
    });

    document.querySelectorAll('.fa-heart').forEach(icon => {
        icon.addEventListener('click', function() {
            this.classList.toggle('active');
            // Add favorites functionality here
        });
    });
    </script>
</body>
</html>
