<?php
// No session_start() needed here usually, as password reset is often a standalone process
// If you *do* need sessions (e.g., for temporary messages after redirect), add session_start();
// session_start(); // Uncomment if needed for session messages/data

// --- Database Connection ---
$host = "localhost";
$user = "root";
$password_db = ""; // Use a different variable name than user's password
$dbname = "nescare"; // Database name is 'nescare' as per your script
$conn = new mysqli($host, $user, $password_db, $dbname);

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Variables for messages ---
$message = "";

// Get email from GET, sanitize it
$email = isset($_GET['email']) ? htmlspecialchars(trim($_GET['email'])) : '';

// --- Handle Password Reset Form Submission ---
if (isset($_POST['reset_password'])) {
    // Get and sanitize/trim the submitted email and passwords
    $new_password = $_POST['new_password']; // Get the raw new password
    $confirm_password = $_POST['confirm_password'];
    $reset_email = htmlspecialchars(trim($_POST['email'])); // Get email from hidden field, sanitize

    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please enter and confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        // --- SECURITY VULNERABILITY WARNING ---
        // *** IMPORTANT: In a real-world application, updating the password
        // *** based ONLY on the email is a severe security risk.
        // *** You MUST implement a proper password reset mechanism involving
        // *** sending a unique, time-limited token to the user's email and
        // *** verifying that token here before allowing the password change.
        // --- END WARNING ---

        // --- Hash the new password securely ---
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Prepare the UPDATE statement
        // Use correct table name 'users' and column name 'password_hash'
        // The WHERE clause should ideally also check a valid reset token along with the email
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        if ($stmt) {
            // Bind parameters: hashed_password (string), email (string)
            $stmt->bind_param("ss", $hashed_password, $reset_email);

            if ($stmt->execute()) {
                // --- Password Reset Successful ---
                // In a real app, invalidate the reset token here

                $message = "Password has been reset successfully. You can now <a href='login.php'>login</a> with your new password.";
                // Optionally redirect to login page after a delay or immediately
                // header("Location: login.php"); exit();

            } else {
                // Handle execution errors
                $message = "Error updating password: " . $stmt->error; // Use $stmt->error for prepared statements
                error_log("Password reset execute failed for email " . $reset_email . ": " . $stmt->error); // Log the error server-side
            }
            $stmt->close(); // Close the update statement
        } else {
            // Handle preparation errors
            $message = "Database error during password reset preparation.";
            error_log("Password reset prepare failed: " . $conn->error); // Log the error server-side
        }
    }
}

// Close the database connection *after* all database operations are done
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Reset Password - Nescare</title> <link rel="stylesheet" href="reset_password.css">
    
</head>
<body>
    <div class="reset-password-container">
        <h2>Reset Your Password</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p> <?php else: ?>
            <p>Enter your new password below.</p>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <button type="submit" name="reset_password" class="reset-password-button">Reset Password</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>