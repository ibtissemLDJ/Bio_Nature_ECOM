<?php
// Database connection
require_once 'db_connection.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to blog index if no ID is provided
    header('Location: index.php');
    exit();
}

// Get the post ID
$post_id = intval($_GET['id']);

// Prepare and execute query to get the post details
$query = "SELECT * FROM blog_posts WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if post exists
if ($result->num_rows === 0) {
    // Post not found, redirect to blog index
    header('Location: index.php');
    exit();
}

// Fetch the post data
$post = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - Nescare Blog</title>
    <link rel="stylesheet" href="blog_post.css">
   
</head>
<body>
<?php require_once 'header.php' ?>

<main class="blog-post-container">
    <article>
        <header class="post-header">
            <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="post-meta">Published on <?php echo date('F j, Y', strtotime($post['publish_date'])); ?></div>
        </header>
        
        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-image">
        
        <div class="post-content">
            <?php echo $post['content']; ?>
        </div>
        
        <a href="blog.php" class="back-to-blog">Back to Blog</a>
    </article>
</main>

<?php require_once 'footer.php' ?>
</body>
</html>

<?php
// Close the prepared statement and connection
$stmt->close();
$conn->close();
?>