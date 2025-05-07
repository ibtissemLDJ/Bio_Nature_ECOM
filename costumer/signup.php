<?php
session_start(); // Start session at the very top before any output

// --- Database Connection ---
$host = "localhost";
$user = "root";
$password_db = ""; // Use a different variable name than user password
$dbname = "nescare"; // Database name is 'nescare' as per your script
$conn = new mysqli($host, $user, $password_db, $dbname);

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Variables for messages ---
$error = "";
$success = "";

// --- Handle Signup Form Submission ---
if (isset($_POST['signup'])) {
    // Sanitize inputs (basic sanitization)
    $username = htmlspecialchars(trim($_POST['username']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password']; // Get the raw password

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $error = "Please enter a valid email address.";
    } else {
        // Check if username or email already exists
        // Use correct table name 'users'
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result(); // Store result to use num_rows

            if ($stmt_check->num_rows > 0) {
                $error = "Username or email already exists.";
            } else {
                // --- Hash the password securely ---
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Prepare the INSERT statement
                // Use correct table name 'users' and column name 'password_hash'
                $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                if ($stmt) {
                    // Bind parameters: username (string), email (string), hashed_password (string)
                    $stmt->bind_param("sss", $username, $email, $hashed_password);

                    if ($stmt->execute()) {
                        // --- Signup Successful ---
                        $_SESSION['user_id'] = $conn->insert_id; // Set session after signup
                        // Optionally set username/email in session too if needed later
                        // $_SESSION['username'] = $username;
                        // $_SESSION['email'] = $email;

                        // Redirect the user
                        header("Location: product.php"); // Redirect to the product page
                        exit(); // Stop script execution after redirect
                    } else {
                        // Handle execution errors
                        $error = "Error during sign up: " . $stmt->error; // Use $stmt->error for prepared statements
                        error_log("Signup execute failed: " . $stmt->error); // Log the error server-side
                    }
                    $stmt->close(); // Close the insert statement
                } else {
                    // Handle preparation errors
                    $error = "Database error during signup preparation.";
                    error_log("Signup prepare failed: " . $conn->error); // Log the error server-side
                }
            }
            $stmt_check->close(); // Close the check statement
        } else {
             // Handle preparation errors for check query
            $error = "Database error during user check preparation.";
            error_log("User check prepare failed: " . $conn->error); // Log the error server-side
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Sign Up - Nescare</title> <link rel="stylesheet" href="signup.css">

</head>
<body>
    <div class="signup-container">
        <h2>Sign Up</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"> </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"> </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required> </div>
            <button type="submit" name="signup" class="signup-button">Sign Up</button>
        </form>
        <p class="login-link">Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>