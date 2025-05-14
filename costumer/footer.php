<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
 
  footer {
    background-color: var(--primary);
    color: var(--light);
    padding: 50px 4% 25px; /* More padding */
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Wider columns */
    gap: 40px; /* Increased gap */
    margin-top: 50px; /* More space above footer */
    width: 100%; /* Ensure full width */
  }
  
  footer div h3 {
    font-size: 1.1rem; /* Larger heading */
    margin-bottom: 20px; /* More space */
    color: var(--white);
    font-weight: 600;
    position: relative;
    padding-bottom: 5px;
  }
  /* Underline effect for footer headings */
  /*
  footer div h3::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 30px;
      height: 2px;
      background-color: var(--accent);
  }
  */
  
  footer div ul {
    list-style: none;
    padding: 0;
  }
  
  footer div ul li {
    margin-bottom: 12px; /* Slightly more space */
  }
  
  footer div ul li a {
    text-decoration: none;
    color: var(--secondary);
    font-size: 0.9rem; /* Slightly larger link text */
    transition: color 0.2s, padding-left 0.2s;
  }
  
  footer div ul li a:hover {
    color: var(--accent);
    padding-left: 5px; /* Indent on hover */
  }
  
  footer div ul li a i {
    margin-right: 10px; /* More space for icons */
    font-size: 1rem;
    width: 15px; /* Align icons */
    text-align: center;
  }
  
  .copyright {
    grid-column: 1 / -1; /* Span across all columns */
    text-align: center;
    margin-top: 30px;
    padding-top: 25px; /* More padding */
    border-top: 1px solid rgba(230, 237, 244, 0.3); /* Lighter border */
    font-size: 0.85rem; /* Slightly larger copyright */
    color: var(--secondary);
  }
  </style>
</head>
<body>
<footer id="contact-section">
        <div>
            <h3>Shop</h3>
            <ul>
                <li><a href="?category=1">Skincare</a></li>
                <li><a href="?category=2">Makeup</a></li>
                <li><a href="?category=3">Hair Care</a></li>
                <li><a href="?category=4">Body Care</a></li>
                <li><a href="#">Gift Sets</a></li> </ul>
        </div>
        <div>
            <h3>About</h3>
            <ul>
                <li><a href="about.php">Our Story</a></li>
                <li><a href="ingredients.php">Ingredients</a></li>
                <li><a href="#">Sustainability</a></li> <li><a href="blog.php">Blog</a></li>
                <li><a href="#">Press</a></li> </ul>
        </div>
        <div>
            <h3>Help</h3>
            <ul>
                <li><a href="contact.php">Contact Us</a></li>
                <li><a href="#">FAQs</a></li> <li><a href="#">Shipping</a></li> <li><a href="#">Returns</a></li> <li><a href="#">Track Order</a></li> </ul>
        </div>
        <div>
            <h3>Connect</h3>
            <ul>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i> Instagram</a></li>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i> Facebook</a></li>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i> Twitter</a></li>
                <li><a href="#" target="_blank" rel="noopener noreferrer"><i class="fab fa-pinterest"></i> Pinterest</a></li>
            </ul>
        </div>
        <div class="copyright">
            <p>&copy; <?php echo date("Y"); ?> Nescare. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>