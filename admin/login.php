<?php
session_start(); // Start the session at the very beginning

// --- CONFIGURATION (same as your admin.php, or in a separate config.php) ---
define('DB_HOST', 'localhost'); // Replace with your database host
define('DB_NAME', 'nescare'); // Replace with your DB name
define('DB_USER', 'root'); // Replace with your DB username
define('DB_PASS', ''); // Replace with your DB password

$error_message = ''; // To store any login error messages

// Check if the user is already logged in, if so, redirect to admin.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['username']) && !empty($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password']; // Password received directly from form

        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prepare SQL statement to find the admin user
            // WARNING: Comparing plaintext password directly from the database. HIGHLY INSECURE.
            // The 'password_hash' column is used here to store the PLAINTEXT password as per your request.
            $stmt = $pdo->prepare("SELECT user_id, username, password_hash FROM users WHERE username = :username AND is_admin = TRUE LIMIT 1");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin_user) {
                // WARNING: Direct password comparison. EXTREMELY INSECURE.
                if ($password === $admin_user['password_hash']) {
                    // Password matches
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $admin_user['user_id'];
                    $_SESSION['admin_username'] = $admin_user['username'];

                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    header("Location: admin.php"); // Redirect to the admin dashboard
                    exit;
                } else {
                    // Password does not match
                    $error_message = "Invalid username or password.";
                }
            } else {
                // No admin user found with that username
                $error_message = "Invalid username or password.";
            }

        } catch (PDOException $e) {
            // In a production environment, log this error.
            $error_message = "An error occurred. Please try again later.";
            // error_log("Login PDOException: " . $e->getMessage()); // Example logging
        }
        $pdo = null; // Close connection
    } else {
        $error_message = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="admin_login.css">
</head>
<body>
    <div class="login-container">
        <h1>Admin Login</h1>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>