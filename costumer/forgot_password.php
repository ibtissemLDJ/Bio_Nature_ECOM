<?php
session_start();
require_once 'db_connection.php';

// Initialize message variables
$message = "";
$message_type = "";

// Handle Forgot Password Form Submission
if (isset($_POST['reset_request'])) {
    // Get and sanitize the email input
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    // Validate the email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {
        // Check if connection is valid before querying
        if ($conn) {
            // Prepare the SELECT statement to find the user by email
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");

            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    // User found - Redirect to reset password page
                    header("Location: reset_password.php?email=" . urlencode($email));
                    exit();
                } else {
                    // No user found with that email
                    $stmt->close();
                    // For security, show the same message whether the email exists or not
                    $message = "If an account with that email address exists, we have sent password reset instructions.";
                    $message_type = "success";
                }
            } else {
                error_log("Database query failed to prepare email lookup query (forgot_password.php): " . $conn->error);
                $message = "An internal error occurred. Please try again.";
                $message_type = "error";
            }
        } else {
            $message = "Unable to process request due to a database error.";
            $message_type = "error";
        }
    }
}

// Close the database connection
if ($conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Nescare</title>
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

        .forgot-password-container {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .forgot-password-container h2 {
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

        .form-group input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }

        .reset-request-button {
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

        .reset-request-button:hover {
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
    <div class="forgot-password-container">
        <h2>Forgot Your Password?</h2>

        <?php if ($message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <p>Enter your email address below to reset your password.</p>

        <form method="post">
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" name="reset_request" class="reset-request-button">Reset Password</button>
        </form>

        <a href="login.php" class="login-link">Back to Login</a>
    </div>
</body>
</html>