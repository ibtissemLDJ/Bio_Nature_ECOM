<?php
// No session_start() needed here usually, as password reset is often a standalone process
// If you *do* need sessions (e.g., for temporary messages after redirect), add session_start();
session_start();
require_once 'db_connection.php';

// Initialize message variables
$message = "";
$message_type = "";
$email = "";

// Get email from GET parameter
if (isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update the user's password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if ($stmt->execute()) {
                $message = "Your password has been successfully reset. You can now login with your new password.";
                $message_type = "success";
                // Redirect to login page after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $message = "An error occurred while resetting your password. Please try again.";
                $message_type = "error";
            }
            $stmt->close();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Nescare</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .reset-password-container {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .reset-password-container h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            opacity: 0.95;
            font-size: 0.95em;
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }

        .reset-button {
            width: 100%;
            padding: 10px;
            background-color: #0c2d57;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .reset-button:hover {
            background-color: #081e3d;
        }

        .login-link {
            margin-top: 20px;
            display: block;
            color: #0c2d57;
            text-decoration: none;
        }

        .login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <h2>Reset Your Password</h2>

        <?php if ($message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <div class="form-group">
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <button type="submit" name="reset_password" class="reset-button">Reset Password</button>
        </form>

        <a href="login.php" class="login-link">Back to Login</a>
    </div>
</body>
</html>