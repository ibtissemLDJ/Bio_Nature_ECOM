<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "nescare";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM Users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($firstName, $lastName, $email);
$stmt->fetch();
$stmt->close();

$fullName = trim($firstName . ' ' . $lastName);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Profile</title>
    <link rel="stylesheet" href="profile.css">
    <script>
        function confirmLogout() {
            if (confirm("Are you sure you want to log out?")) {
                window.location.href = "logout.php";
            }
        }
    </script>
</head>
<body>
    <div class="profile-container">
        <h2>Your Profile</h2>
        <div class="profile-info">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($fullName); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
        </div>
        <button class="logout-button" onclick="confirmLogout()">Logout</button>
        <p><a href="product.php">Back to Products</a></p>
    </div>
</body>
</html>