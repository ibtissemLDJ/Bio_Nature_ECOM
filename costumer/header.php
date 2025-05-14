<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        /* --- Header --- */
  header {
    background-color: var(--white);
    padding: 10px 4%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 5px rgba(0, 0, 0, 0.04);
    position: sticky;
    top: 0;
    z-index: 1000; /* Ensure header is above other elements */
    width: 100%; /* Ensure full width */
  }
  
  .logo-container {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .logo {
    height: 28px;
    display: block; /* Prevents potential spacing issues */
  }
  
  nav {
      margin: 0 auto; /* Center nav */
  }
  
  nav ul {
    display: flex;
    list-style: none;
    gap: 20px; /* Increased spacing */
  }
  
  nav ul li a {
    text-decoration: none;
    color: var(--dark);
    font-weight: 400;
    font-size: 13px;
    padding: 5px 0; /* Add padding for hover area */
    position: relative; /* For potential underline effect */
    transition: color 0.2s;
  }
  
  nav ul li a:hover,
  nav ul li a.active {
    color: var(--primary);
    font-weight: 500;
  }
  
  /* Optional: Underline effect on hover/active */
  /*
  nav ul li a::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: 0;
      left: 50%;
      background-color: var(--primary);
      transition: width 0.3s ease, left 0.3s ease;
  }
  nav ul li a:hover::after,
  nav ul li a.active::after {
      width: 100%;
      left: 0;
  }
  */
  
  /* --- Header Nav Right Styling --- */

.nav-right {
    display: flex;
    align-items: center;
    gap: 18px;
  }
  
  /* Style ONLY the ICON links (wishlist, basket) within nav-right */
  .nav-right a:not(.login-btn):not(.profile-link) { /* Exclude login button and profile link */
      color: var(--primary);
      font-size: 17px;
      transition: color 0.2s ease, transform 0.2s ease;
      text-decoration: none;
      display: inline-block; /* Allows transform */
  }
  
  /* Apply hover ONLY to icon links */
  .nav-right a:not(.login-btn):not(.profile-link):hover {
      color: var(--accent);
      transform: scale(1.1); /* Slight scale on hover */
  }
  
  /* Style for the profile link container */
  .profile-container {
      line-height: 0;
  }
  .profile-container a { /* Style specifically for the profile link */
      display: inline-block; /* Or block if needed */
      text-decoration: none;
      /* No color/font-size needed here as it contains only the image */
  }
  
  .profile-pic {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    border: 1.5px solid var(--primary);
    display: block;
    transition: transform 0.3s ease, border-color 0.3s ease; /* Added border-color */
  }
  /* Profile pic specific hover */
  .profile-container a:hover .profile-pic {
      transform: rotate(10deg) scale(1.1);
      border-color: var(--accent);
  }
  
  
  /* --- Login Button Styling (Should now work correctly) --- */
  .login-btn {
    padding: 6px 15px;
    background-color: var(--primary);
    color: var(--white); /* THIS IS THE FIX - Text is explicitly white */
    border: none;
    border-radius: 15px;
    font-size: 13px; /* Specific font size for the button */
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s, transform 0.2s;
    text-decoration: none;
    display: inline-block; /* Ensure proper display */
    line-height: normal; /* Ensure default line height */
    text-align: center; /* Ensure text is centered */
  }
  
  .login-btn:hover {
    background-color: var(--accent);
    color: var(--white); /* Keep text white on hover */
    transform: translateY(-2px); /* Button lift effect */
  }
  
  /* --- Container for page content --- */
  .container {
    padding: 30px 4%; /* Add padding around main content */
    width: 100%;
    max-width: 1300px; /* Limit max width for very large screens */
    margin: 0 auto; /* Center container */
  }
  
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="notifications.css">
</head>
<body>
<header>
         <div class="logo-container">
            <a href="landing.php"><img src="images/logo.png" alt="Nescare Logo" class="logo" /></a>
             </div>
        <nav>
             <ul>
                 <li><a href="product.php" class="active">Shop</a></li>
                 <li><a href="landing.php#about-section">About</a></li>
                 <li><a href="blog.php">Blog</a></li>
                 <li><a href="#contact-section">Contact</a></li>
             </ul>
        </nav>
        <div class="nav-right">
              <?php if (isset($_SESSION['user_id'])): ?>
                  <a href="wishlist.php" title="Wishlist"><i class="fas fa-heart"></i></a>
                  <a href="basket.php" title="Cart"><i class="fas fa-shopping-basket"></i></a>
                  <div class="profile-container-small">
                      <a href="profile.php" title="Your Profile - <?php echo htmlspecialchars($username); ?>">
                          <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-pic">
                      </a>
                  </div>
              <?php else: ?>
                  <a href="login.php?redirect=wishlist.php" title="Login to view wishlist"><i class="fas fa-heart"></i></a>
                  <a href="login.php?redirect=basket.php" title="Login to view cart"><i class="fas fa-shopping-basket"></i></a>
                  <a href="login.php" class="login-btn">Login</a>
              <?php endif; ?>
        </div>
</header>

<?php
// Display session messages (success, warning, error)
if (isset($_SESSION['success_message'])): ?>
    <div class="notification success">
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <span class="close-btn">&times;</span>
    </div>
    <?php unset($_SESSION['success_message']);
endif;
if (isset($_SESSION['warning_message'])): ?>
    <div class="notification warning">
        <?php echo htmlspecialchars($_SESSION['warning_message']); ?>
        <span class="close-btn">&times;</span>
    </div>
    <?php unset($_SESSION['warning_message']);
endif;
if (isset($_SESSION['error_message'])): ?>
    <div class="notification error">
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <span class="close-btn">&times;</span>
    </div>
    <?php unset($_SESSION['error_message']);
endif;
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle notification dismissal
    const notifications = document.querySelectorAll('.notification');
    
    notifications.forEach(notification => {
        const closeBtn = notification.querySelector('.close-btn');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                notification.classList.add('hide');
                setTimeout(() => {
                    notification.remove();
                }, 300); // Match this with the animation duration
            });
        }
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (notification && !notification.classList.contains('hide')) {
                notification.classList.add('hide');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }, 5000);
    });
});
</script>
</body>
</html>
  