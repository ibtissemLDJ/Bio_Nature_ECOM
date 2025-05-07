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
<?php
session_start(); // Start the session (useful for potential session messages)

// Include database connection - Make sure this path is correct relative to forgot_password.php
// Make sure db_connection.php connects to your 'nescare' database
require_once 'db_connection.php';

// Use a separate variable for DB password if db_connection.php doesn't handle it
// $host = "localhost";
// $user = "root";
// $password_db = "";
// $dbname = "nescare";
// $conn = new mysqli($host, $user, $password_db, $dbname);
// if ($conn->connect_error) {
//     error_log("Database Connection failed: " . $conn->connect_error);
//     // Display a user-friendly error message
//     $message = "An error occurred connecting to the database.";
//     $conn = null; // Ensure $conn is null if connection failed
// }


// Initialize message variable
$message = "";
$message_type = ""; // To distinguish success/error/warning for styling

// --- Handle Forgot Password Form Submission ---
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
            // Use the correct table name 'users'
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");

            if ($stmt) {
                // Bind the email parameter (string, hence "s")
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result(); // Store the result to check if a row was found

                // Check if a user with that email exists
                if ($stmt->num_rows === 1) {
                    // User found - Proceed with generating token and sending email
                    // Bind the result (we just need user_id)
                    $stmt->bind_result($user_id);
                    $stmt->fetch();
                    $stmt->close(); // Close the statement

                    // --- IMPORTANT TODO: GENERATE TOKEN, STORE IN DB, AND SEND EMAIL ---
                    // This part requires:
                    // 1. A 'password_resets' table (e.g., with user_id, token (VARCHAR), created_at (DATETIME), expires_at (DATETIME))
                    // 2. Generating a secure, unique token (e.g., using bin2hex(random_bytes(32)))
                    // 3. Storing the token and user_id in the 'password_resets' table with an expiration time.
                    // 4. Configuring and using a mail sending library/function (like PHPMailer, or PHP's mail() function if configured)
                    //    to send an email containing a link like:
                    //    <your_website_url>/reset_password.php?token=[generated_token]

                    // For demonstration, we'll just set a success message without doing the above steps.
                    // In a real application, the message should be the same whether the email
                    // exists or not, to prevent leaking information about registered emails.
                    $message = "If an account with that email address exists, we have sent password reset instructions.";
                    $message_type = "success";

                } else {
                    // No user found with that email
                    $stmt->close(); // Close the statement even if no rows found

                    // It's a security best practice NOT to confirm if the email exists or not.
                    // Always show the same message as if it did exist.
                    $message = "If an account with that email address exists, we have sent password reset instructions.";
                    $message_type = "success"; // Still show success type message for consistency

                    // Optionally log an attempt for a non-existent email for monitoring
                    // error_log("Password reset attempt for non-existent email: " . $email);
                }

            } else {
                 // Handle error if the prepared statement itself failed
                 error_log("Database query failed to prepare email lookup query (forgot_password.php): " . $conn->error);
                 $message = "An internal error occurred. Please try again.";
                 $message_type = "error";
            }
        } else {
            // Handle case where database connection failed initially
            $message = "Unable to process request due to a database error.";
            $message_type = "error";
        }
    }
}

// Close the database connection at the very end if it was opened successfully
if ($conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Forgot Password | Nescare</title> <link rel="stylesheet" href="forgot_password.css"> <style>
        /* Basic Message Styling - Copy from your main styles or define here */
        /* Ensure these match your site's message styling for consistency */
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            opacity: 0.95;
            font-size: 0.95em;
            text-align: center; /* Center message text */
        }
        .message.success {
            background-color: #d4edda; /* Light green */
            color: #155724; /* Dark green */
            border: 1px solid #c3e6cb;
        }
        .message.warning {
            background-color: #fff3cd; /* Light yellow */
            color: #856404; /* Dark yellow */
            border: 1px solid #ffeeba;
        }
        .message.error {
            background-color: #f8d7da; /* Light red */
            color: #721c24; /* Dark red */
            border: 1px solid #f5c6cb;
        }

        /* Basic styling for the container */
        .forgot-password-container {
            max-width: 400px;
            margin: 50px auto; /* Center block element with top/bottom margin */
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

        .forgot-password-container p {
            margin-bottom: 20px;
            color: #555;
            font-size: 0.95em;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left; /* Align form elements to the left */
        }

        .form-group label {
            display: block; /* Label on its own line */
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-group input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Include padding in width */
            font-size: 1em;
        }

        .reset-request-button {
            width: 100%;
            padding: 10px;
            background-color: #007bff; /* Primary blue color */
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .reset-request-button:hover {
            background-color: #0056b3; /* Darker blue on hover */
        }

        .forgot-password-container p a {
             color: #007bff; /* Primary blue for links */
             text-decoration: none;
             font-size: 0.9em;
        }

        .forgot-password-container p a:hover {
            text-decoration: underline;
        }

    </style>
</head>
<body>
    <div class="forgot-password-container">
        <h2>Forgot Your Password?</h2>

        <?php
        // Display the message if set
        if ($message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></p>
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