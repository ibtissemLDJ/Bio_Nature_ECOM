<?php
session_start();
require_once 'db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['profile_picture'] = $user['profile_picture'];
        
        // Set success message in session
        $_SESSION['success_message'] = 'Login successful! Welcome back.';
        
        // Redirect to product.php
        header("Location: product.php");
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, role, first_name, profile_picture FROM Users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                
                header("Location: index.php");
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | Nescare</title>
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
      <a href="signup.php" class="signup-link">Don't have an account? Sign up</a>
    </div>
  </nav>

  <div class="login-container">
    <div class="login-form">
      <h2>Login to Your Account</h2>
      <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
      <?php endif; ?>
      <form action="login.php" method="POST">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="login-btn">Login</button>
      </form>
      <div class="forgot-password">
        <a href="forgot_password.php">Forgot your password?</a>
      </div>
    </div>
  </div>
</body>
</html>