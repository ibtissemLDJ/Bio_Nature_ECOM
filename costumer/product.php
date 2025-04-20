<?php
session_start();

$host = "localhost";
$user = "root";
$password = "";
$dbname = "nescare";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}

// Fetch categories
$categories_sql = "SELECT category_id, name FROM Categories";
$categories_result = $conn->query($categories_sql);

// Filter logic
$category_filter = "";
$filtered_category_id = null; // To track if a category is being filtered
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $filtered_category_id = intval($_GET['category']);
    $category_filter = "WHERE category_id = " . $filtered_category_id;
}

// Fetch products with filter
$sql = "SELECT name, description, price, stock, category_id, image_url FROM items $category_filter";
$result = $conn->query($sql);

// Profile picture & user info
$profile_picture = "images/user1.png";
$username = "";

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_picture, username FROM Users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($profile_picture_db, $username);
    $stmt->fetch();
    if (!empty($profile_picture_db)) {
        $profile_picture = $profile_picture_db;
    }
    $stmt->close();
}

$intro_image_url = "images/serum_drop.jpg"; // REPLACE WITH YOUR ACTUAL IMAGE PATH
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nos Produits</title>
    <link rel="stylesheet" href="product.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <img src="images/logo.png" alt="logo" class="logo" />
    </div>

    <div class="nav-center">
        <a href="#">Home</a>
        <a href="#">About us</a>
        <a href="#">Contact</a>
        <a href="#">Categories</a>
    </div>

    <div class="nav-right">
        <i class="fas fa-bell"></i>
        <i class="fas fa-shopping-basket"></i>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="profile-container">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="profile-pic" title="<?php echo htmlspecialchars($username); ?>">
            </div>
        <?php else: ?>
            <button class="login-btn"><a href="login.php">Login</a></button>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <section class="intro-section">
        <div class="intro-text">
            <h2 class="intro-title">Nourish Your Skin's Essence</h2>
            <p class="intro-paragraph">Discover the power of Nescare, where science meets nature to bring you bio-care products that transform your skin's health and radiance.</p>
            <a href="#" class="intro-button">Explore More</a>
        </div>
        <div class="intro-image-container">
            <img src="images/P.png" alt="Nescare Essence" class="intro-image">
        </div>
    </section>

    <div class="title-filter-bar">
        <h2 class="products-title">Nos Produits</h2>
        <div class="filter-dropdown">
            <i class="fas fa-filter filter-icon" onclick="toggleDropdown()" title="Filtrer par catégorie"></i>
            <div id="categoryList" class="dropdown-content">
                <a href="products.php">Toutes les catégories</a>
                <?php

                $categories_result = $conn->query("SELECT category_id, name FROM Categories");
                while($cat = $categories_result->fetch_assoc()): ?>
                    <a href="?category=<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="product-grid">
        <?php
        if ($result->num_rows > 0):
            while($row = $result->fetch_assoc()): ?>
                <div class="product">
                    <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                        <p><?php echo htmlspecialchars($row['description']); ?></p>
                        <p class="price">$<?php echo htmlspecialchars(number_format($row['price'], 2)); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Aucun produit trouvé.</p>
        <?php endif; ?>
    </div>
</div>

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
</script>

</body>
</html>

<?php $conn->close(); ?>