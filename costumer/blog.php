<?php
// Database connection
require_once 'db_connection.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default profile picture and username
$profile_picture = "images/user1.png";
$username = "";

// Fetch user profile data if logged in
if ($conn && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt_user = $conn->prepare("SELECT profile_picture, username FROM users WHERE user_id = ?");
    if ($stmt_user) {
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $stmt_user->bind_result($profile_picture_db, $username_db);
        if ($stmt_user->fetch()) {
            $username = $username_db;
            
            // Use the profile picture from the database without file_exists check
            if (!empty($profile_picture_db)) {
                $profile_picture = $profile_picture_db;
            }
        } else {
            error_log("User ID " . $user_id . " from session not found in users table (blog page).");
        }
        $stmt_user->close();
    } else {
        error_log("Database query failed for user profile (blog page): " . $conn->error);
    }
}

// Fetch featured post
$featured_query = "SELECT * FROM blog_posts WHERE is_featured = 1 ORDER BY publish_date DESC LIMIT 1";
$featured_result = $conn->query($featured_query);
$featured_post = $featured_result->fetch_assoc();

// If no featured post, get the latest post
if (!$featured_post) {
    $latest_query = "SELECT * FROM blog_posts ORDER BY publish_date DESC LIMIT 1";
    $latest_result = $conn->query($latest_query);
    $featured_post = $latest_result->fetch_assoc();
}

// Check if featured post exists before proceeding
if ($featured_post) {
    // Fetch other blog posts (excluding the featured one)
    $posts_query = "SELECT * FROM blog_posts WHERE id != ? ORDER BY publish_date DESC LIMIT 6";
    $posts_stmt = $conn->prepare($posts_query);
    $posts_stmt->bind_param("i", $featured_post['id']);
    $posts_stmt->execute();
    $posts_result = $posts_stmt->get_result();
} else {
    // No featured post, get all posts
    $posts_query = "SELECT * FROM blog_posts ORDER BY publish_date DESC LIMIT 6";
    $posts_result = $conn->query($posts_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nescare - Bio Cosmetic Blog</title>
    <link rel="stylesheet" href="blogs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <a href="landing.php"><img src="images/logo.png" alt="Nescare Logo" class="logo" /></a>
        </div>
        <nav>
            <ul>
                <li><a href="product.php">Shop</a></li>
                <li><a href="product.php">About</a></li>
                <li><a href="product.php">Ingredients</a></li>
                <li><a href="blog.php" class="active">Blog</a></li>
                <li><a href="footer.php">Contact</a></li>
            </ul>
        </nav>
        <div class="nav-right">
            <a href="wishlist.php" title="Wishlist"><i class="fas fa-heart"></i></a>
            <a href="basket.php" title="Cart"><i class="fas fa-shopping-basket"></i></a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="profile-container">
                    <a href="profile.php" title="Your Profile - <?php echo htmlspecialchars($username); ?>">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-pic">
                    </a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
            <?php endif; ?>
        </div>
        <?php
        // Display session messages (success, warning, error)
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

    <main class="container">
        <!-- Blog Header -->
        <section class="blog-header">
            <h1>Nescare Blog</h1>
            <p>Discover the latest in natural, organic cosmetics and learn how to care for your skin with the purest ingredients from nature.</p>
        </section>

        <?php if($featured_post): ?>
        <!-- Featured Post -->
        <article class="featured-post">
            <img src="<?php echo htmlspecialchars($featured_post['featured_image']); ?>" alt="<?php echo htmlspecialchars($featured_post['title']); ?>" class="featured-image">
            <div class="featured-content">
                <span class="featured-tag">Featured</span>
                <h2 class="featured-title"><?php echo htmlspecialchars($featured_post['title']); ?></h2>
                <p class="featured-excerpt"><?php echo htmlspecialchars($featured_post['excerpt']); ?></p>
                <a href="blog_post.php?id=<?php echo $featured_post['id']; ?>" class="read-more">Read More</a>
            </div>
        </article>
        <?php endif; ?>

        <!-- Blog Posts Grid -->
        <div class="blog-grid">
            <?php while($posts_result && $post = $posts_result->fetch_assoc()): ?>
            <article class="blog-card">
                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="blog-image">
                <div class="blog-content">
                    <div class="blog-date"><?php echo date('F j, Y', strtotime($post['publish_date'])); ?></div>
                    <h3 class="blog-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p class="blog-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                    <a href="blog_post.php?id=<?php echo $post['id']; ?>" class="read-more">Read More</a>
                </div>
            </article>
            <?php endwhile; ?>
        </div>
    </main>
    <?php require_once 'footer.php' ?>
</body>
</html>

<?php 
// Close the prepared statement and connection
if (isset($posts_stmt) && $posts_stmt instanceof mysqli_stmt) {
    $posts_stmt->close();
}
if ($conn) {
    $conn->close();
}
?>