<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "nescare";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if (isset($_POST['reset_request'])) {
    $email = $_POST['email'];

    if (empty($email)) {
        $message = "Please enter your email address.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            // In a real application, you would generate a token,
            // store it in the database, and send a reset link to the email.
            // For this basic example, we'll just redirect to the reset password page.
            header("Location: reset_password.php?email=" . urlencode($email));
            exit();
        } else {
            $message = "Email address not found.";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="forgot_password.css">
</head>
<body>
    <div class="forgot-password-container">
        <h2>Forgot Your Password?</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <p>Enter your email address below and we'll send you instructions on how to reset your password.</p>
        <form method="post">
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" name="reset_request" class="reset-request-button">Request Password Reset</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>