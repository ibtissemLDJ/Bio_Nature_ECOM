<?php
session_start();

// --- Database Connection ---
$host = "localhost";
$user = "root";
$password_db = ""; // Use a different variable name for DB password
$dbname = "nescare"; // Database name is 'nescare'

$conn = new mysqli($host, $user, $password_db, $dbname);

if ($conn->connect_error) {
    // Log the error instead of dying directly on a live site
    error_log("Database Connection failed: " . $conn->connect_error);
    // Display a user-friendly error message
    die("An error occurred while connecting to the database. Please try again later.");
}

// --- Check if the user is logged in ---
if (!isset($_SESSION['user_id'])) {
    // Use session message for better user feedback after redirect
    $_SESSION['warning_message'] = "Please log in to view your profile.";
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Fetch User Profile Information ---
$username = "Guest"; // Default values
$email = "N/A";
$profile_picture = "images/user1.png"; // Default picture path

// Use the correct table name 'users' and select relevant columns
$stmt = $conn->prepare("SELECT username, email, profile_picture FROM users WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    // Bind results to variables
    $stmt->bind_result($db_username, $db_email, $db_profile_picture);
    $stmt->fetch();

    // Assign fetched values if user exists
    if ($db_username) { // Check if a row was fetched
        $username = $db_username;
        $email = $db_email;
        // Use the fetched profile picture path if it exists and is not empty
        // Note: file_exists check is good for relative paths on the server.
        // If you store URLs, you might not need file_exists here.
        if (!empty($db_profile_picture) && file_exists($db_profile_picture)) {
            $profile_picture = $db_profile_picture;
        } elseif (!empty($db_profile_picture)) {
             // If path is not empty but file doesn't exist (e.g., wrong path, deleted file)
             error_log("Profile picture file not found: " . $db_profile_picture . " for user_id: " . $user_id);
             // Keep the default image
        }
    }
    $stmt->close();
} else {
    error_log("Database query failed to fetch user profile (profile.php): " . $conn->error);
    // Optionally set an error message for the user
    $_SESSION['error_message'] = "Could not load profile information.";
}

// --- Fetch User Order History ---
$order_history = [];
// Call the stored procedure get_customer_order_history
$stmt_orders = $conn->prepare("CALL get_customer_order_history(?)");
if ($stmt_orders) {
    $stmt_orders->bind_param("i", $user_id);
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result(); // Get the result set

    while ($row_order = $result_orders->fetch_assoc()) {
        $order_history[] = $row_order; // Store order details in the array
    }

    $stmt_orders->close();

    // Need to handle potential multiple result sets if the SP returns more (yours returns 2)
    // Since get_customer_order_history returns 2 result sets (order summary and order items),
    // we need to advance past the first one (which we just fetched) to avoid issues
    // if you were calling another SP/query afterwards. For just this one SP,
    // closing the statement might be sufficient depending on MySQLi's behavior,
    // but using next_result() is safer practice after fetching results.
    while($conn->more_results() && $conn->next_result()){
        // clean out subsequent results
        $extra_result = $conn->use_result();
        if($extra_result) $extra_result->free();
    }


} else {
    error_log("Database query failed to fetch order history (profile.php): " . $conn->error);
    // Optionally set an error message for the user
    $_SESSION['error_message'] = (isset($_SESSION['error_message']) ? $_SESSION['error_message'] . " " : "") . "Could not load order history.";
}


// --- Close Database Connection ---
// Close after *all* database operations are complete
$conn->close();

// --- Include Header (Optional, replace with your actual header include) ---
// Assuming you have a reusable header file that includes navigation, etc.
// For this example, I'll include a simple placeholder HTML header structure.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile | Nescare</title> 
     <link rel="stylesheet" href="profiles.css">
     <link rel="stylesheet" href="landing.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   
</head>
<body>
<?php require_once 'header.php' ?>

    <main class="profile-container">
        <div class="profile-header">
            <div class="profile-picture-container">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="<?php echo htmlspecialchars($username); ?>'s Profile Picture">
            </div>
            <div class="profile-basic-info">
                <h2>Hello, <?php echo htmlspecialchars($username); ?></h2>
                <p>Email: <?php echo htmlspecialchars($email); ?></p>
                </div>
        </div>

        <div class="profile-section">
            <h3>Order History</h3>
            <?php if (empty($order_history)): ?>
                <p>You haven't placed any orders yet.</p>
            <?php else: ?>
                <ul class="order-list">
                    <?php foreach ($order_history as $order): ?>
                        <li class="order-item">
                            <div class="order-item-info">
                                <strong>Order #<?php echo htmlspecialchars($order['order_id']); ?></strong>
                                <br>
                                Date: <?php echo htmlspecialchars((new DateTime($order['order_date']))->format('Y-m-d H:i')); ?>
                                <br>
                                Total: $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?>
                            </div>
                            <div class="order-status status-<?php echo htmlspecialchars($order['status']); ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </div>
                            <div class="action-buttons">
                                <a href="order_details.php?order_id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn btn-primary">View Details</a>
                                </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="profile-section">
             <h3>Account Settings</h3>
             <p>Manage your account details, change password, etc.</p>
             </div>

        <button class="logout-button" onclick="confirmLogout()">Logout</button>

        <p><a href="product.php">Back to Products</a></p>
    </main>

    <?php
    // --- Include Footer (Placeholder) ---
    // Replace this with your actual footer include if you have one
    // Example: include 'footer.php';
    ?>
   <?php require_once 'footer.php' ?>

    <script>
        // Logout confirmation function remains the same
        function confirmLogout() {
            if (confirm("Are you sure you want to log out?")) {
                window.location.href = "logout.php"; // Ensure logout.php handles session destruction
            }
        }
    </script>

</body>
</html>