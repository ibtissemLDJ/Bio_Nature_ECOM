<?php
session_start();
//bessouma 1234
// Include database connection - Make sure this path is correct relative to login.php
// Make sure db_connection.php connects to your 'nescare' database
require_once 'db_connection.php';

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


// Initialize variable for error messages
$error = "";

// --- Handle Login Form Submission ---
if (isset($_POST['login'])) {
    // Sanitize input (basic sanitization for username)
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password']; // Get the raw password

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Prepare the SELECT statement
        // Use the correct table name 'users' and select the 'password_hash' column
        // Also select username and user_id
        $stmt = $conn->prepare("SELECT user_id, username, password_hash FROM users WHERE username = ?");

        if ($stmt) {
            // Bind the username parameter (string, hence "s")
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result(); // Store the result to check if a row was found

            // Check if a user with that username exists
            if ($stmt->num_rows === 1) {
                // Bind the results to variables
                // Bind the fetched password hash to a variable
                $stmt->bind_result($user_id, $db_username, $db_password_hash); // **MODIFIED**: Bind password_hash

                // Fetch the row
                $stmt->fetch();

                // --- Verify the submitted password against the stored hash ---
                // Use password_verify() to securely compare the raw password with the hash
                if (password_verify($password, $db_password_hash)) { // **MODIFIED**: Use password_verify()
                    // Password is correct, login successful
                    $_SESSION['user_id'] = $user_id; // Set the user ID in the session
                    // Optionally set username in session as well
                    // $_SESSION['username'] = $db_username;

                    // Redirect to the product page after successful login
                    header("Location: product.php");
                    exit(); // Stop script execution after redirect

                } else {
                    // Password verification failed
                    $error = "Incorrect username or password."; // Use a generic message
                }

            } else {
                // No user found with that username
                $error = "Incorrect username or password."; // Use a generic message
            }

            $stmt->close(); // Close the prepared statement

        } else {
             // Handle error if the prepared statement fails
            error_log("Database query failed to prepare login query (login.php): " . $conn->error);
            $error = "An internal error occurred. Please try again.";
        }
    }
}

// Close the database connection at the very end of the script execution
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Login | Nescare</title> <link rel="stylesheet" href="login.css"> </head>
<body>
    <div class="login-container">
        <h2>Login</h2>

        <?php
        // Display session messages (e.g., warning from checkout)
        if (isset($_SESSION['warning_message'])): ?>
            <p class="message warning"><?php echo htmlspecialchars($_SESSION['warning_message']); ?></p>
            <?php unset($_SESSION['warning_message']); // Clear the message
        endif;
         // Display error message from login attempt
        if ($error): ?>
            <p class="message error"><?php echo htmlspecialchars($error); ?></p> <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                 <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="login-button">Login</button>
        </form>

        <p class="forgot-password"><a href="forgot_password.php">Forgot Your Password?</a></p>
        <p class="signup-link">New here? <a href="signup.php">Sign up</a></p>
    </div>

    <?php /* include 'footer.php'; */ ?>

</body>
</html>
