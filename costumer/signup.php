<?php
session_start();
require_once 'db_connection.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    // Validation
    if (empty($username)) {
        $errors['username'] = 'Username is required.';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'Username must be at least 4 characters.';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email.';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    
    // Check if username or email exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT username FROM Users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($user['username'] === $username) {
                $errors['username'] = 'Username already taken.';
            } else {
                $errors['email'] = 'Email already registered.';
            }
        }
    }
    
    // If no errors, create user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_profile = 'images/default-profile.png';
        
        $stmt = $conn->prepare("INSERT INTO Users (username, email, password, first_name, last_name, profile_picture) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $hashed_password, $first_name, $last_name, $default_profile);
        
        if ($stmt->execute()) {
            $success = 'Registration successful! You can now login.';
            // Clear form
            $_POST = array();
        } else {
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_profile = 'images/default-profile.png';
        
        $stmt = $conn->prepare("INSERT INTO Users (username, email, password, first_name, last_name, profile_picture) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $hashed_password, $first_name, $last_name, $default_profile);
        
        if ($stmt->execute()) {
            // Set success message in session
            $_SESSION['success_message'] = 'Registration successful! Welcome to Nescare.';
            
            // Get the new user's ID
            $user_id = $stmt->insert_id;
            
            // Set user session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'customer';
            $_SESSION['first_name'] = $first_name;
            $_SESSION['profile_picture'] = $default_profile;
            
            // Redirect to product.php
            header("Location: product.php");
            exit();
        } else {
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign Up | Nescare</title>
  <link rel="stylesheet" href="signup.css" />
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
      <a href="login.php" class="login-link">Already have an account? Login</a>
    </div>
  </nav>

  <div class="signup-container">
    <div class="signup-form">
      <h2>Create Your Account</h2>
      
      <?php if (!empty($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
      <?php endif; ?>
      
      <?php if (isset($errors['general'])): ?>
        <div class="error-message"><?php echo $errors['general']; ?></div>
      <?php endif; ?>
      
      <form action="signup.php" method="POST">
        <div class="form-row">
          <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
          </div>
          <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
          </div>
        </div>
        
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
          <?php if (isset($errors['username'])): ?>
            <span class="error-text"><?php echo $errors['username']; ?></span>
          <?php endif; ?>
        </div>
        
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
          <?php if (isset($errors['email'])): ?>
            <span class="error-text"><?php echo $errors['email']; ?></span>
          <?php endif; ?>
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
          <?php if (isset($errors['password'])): ?>
            <span class="error-text"><?php echo $errors['password']; ?></span>
          <?php endif; ?>
          <small class="hint">Minimum 8 characters</small>
        </div>
        
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
          <?php if (isset($errors['confirm_password'])): ?>
            <span class="error-text"><?php echo $errors['confirm_password']; ?></span>
          <?php endif; ?>
        </div>
        
        <button type="submit" class="signup-btn">Sign Up</button>
      </form>
    </div>
  </div>
</body>
</html>