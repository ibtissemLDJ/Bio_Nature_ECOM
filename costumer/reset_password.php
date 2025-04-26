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
$email = isset($_GET['email']) ? $_GET['email'] : '';

if (isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $reset_email = $_POST['email'];

    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please enter and confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        // In a real application, you would verify the token here.
        // For this basic example, we'll just update the password based on the email.
        $stmt = $conn->prepare("UPDATE Users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $new_password, $reset_email);

        if ($stmt->execute()) {
            $message = "Password has been reset successfully. You can now <a href='login.php'>login</a> with your new password.";
        } else {
            $message = "Error updating password: " . $conn->error;
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
    <title>Reset Password</title>
    <link rel="stylesheet" href="reset_password.css">
</head>
<body>
    <div class="reset-password-container">
        <h2>Reset Your Password</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php else: ?>
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