<?php
session_start();
require_once 'db_connection.php';

// Password reset logic would go here
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password | Nescare</title>
  <link rel="stylesheet" href="login.css" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Open+Sans&display=swap"
    rel="stylesheet"
  />
</head>
<body>
  <nav class="navbar">
    <div class="nav-left">
      <img src="images/logo.png" alt="logo" class="logo" />
    </div>
    <div class="nav-center">
      <a href="index.php">Home</a>
      <a href="#">About us</a>
      <a href="#">Contact</a>
      <a href="#">Categories</a>
    </div>
    <div class="nav-right">
      <a href="login.php" class="login-link">Back to Login</a>
    </div>
  </nav>

  <div class="login-container">
    <div class="login-form">
      <h2>Reset Your Password</h2>
      <form action="forgot_password.php" method="POST">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="login-btn">Send Reset Link</button>
      </form>
    </div>
  </div>
</body>
</html>