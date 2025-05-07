<?php
session_start(); // Start the session at the very beginning

// --- AUTHENTICATION CHECK ---
// If the admin is not logged in, redirect them to the login page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php"); // Redirect to login page
    exit; // Stop further execution of the script
}

// --- CONFIGURATION (Should match your login.php and other files) ---
define('DB_HOST', 'localhost'); // Replace with your database host
define('DB_NAME', 'nescare'); // Replace with your DB name
define('DB_USER', 'root'); // Replace with your DB username
define('DB_PASS', ''); // Replace with your DB password

// --- DATABASE CONNECTION (Using PDO) ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Default to associative arrays
} catch (PDOException $e) {
    // In a production environment, log this error and show a user-friendly message
    error_log("Admin DB Connection Failed: " . $e->getMessage()); // Log the error
    die("A critical database error occurred. Please try again later or contact support."); // User-friendly message
}

// --- HELPER FUNCTIONS (Consider moving these to a separate 'functions.php' and including it) ---
function escape($html) {
    return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Fetches all rows from a prepared statement.
 * @param PDOStatement $stmt The prepared PDO statement object.
 * @param array $params An array of values to bind to the statement.
 * @return array The fetched rows.
 */
function fetchAllRows(PDOStatement $stmt, array $params = []) {
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetches a single row from a prepared statement.
 * @param PDOStatement $stmt The prepared PDO statement object.
 * @param array $params An array of values to bind to the statement.
 * @return mixed The fetched row or false if no row is found.
 */
function fetchOneRow(PDOStatement $stmt, array $params = []) {
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Executes a prepared statement (for INSERT, UPDATE, DELETE).
 * @param PDOStatement $stmt The prepared PDO statement object.
 * @param array $params An array of values to bind to the statement.
 * @return bool True on success, false on failure.
 */
function executeStatement(PDOStatement $stmt, array $params = []) {
    try {
        return $stmt->execute($params);
    } catch (PDOException $e) {
        // Log error, handle it, or rethrow
        error_log("SQL Execution Error: " . $e->getMessage() . " | SQL: " . $stmt->queryString);
        return false;
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard'; // Default page is dashboard
$action = isset($_GET['action']) ? $_GET['action'] : null;   // For CRUD actions like 'add', 'edit', 'delete'
$id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) : null; // For specific item IDs, basic validation


?>

<div class="admin-main-content">
    <?php
    // --- PAGE CONTENT BASED ON ROUTING ---
    // For each case, you would include a separate PHP file that handles that page's logic and presentation.
    $page_file_path = '';

    switch ($page) {
        case 'dashboard':
            $page_file_path .= 'admin_view_order.php';
            break;

        case 'products':
            $page_file_path .= 'admin_products.php';
            break;

        case 'categories':
            $page_file_path .= 'admin_categories.php';
            break;

        case 'customers':
            $page_file_path .= 'admin_costumers.php';
            break;

        case 'orders':
            $page_file_path .= 'admin_orders.php';
            break;
        
        // Add more cases for other admin pages as needed
        // e.g., case 'settings': $page_file_path .= 'settings.php'; break;

        default:
            $page_file_path .= '404.php'; // A page to show when the admin page isn't found
            // Or you can directly echo a message:
            // echo "<h1>Page Not Found</h1><p>The requested admin page was not found.</p>";
            // $page_file_path = null; // To prevent include error if 404.php doesn't exist
            break;
    }

    if ($page_file_path && file_exists($page_file_path)) {
        // Pass the PDO connection, action, and id to the included page if needed
        // These variables will be available in the scope of the included file.
        include $page_file_path;
    } elseif ($page_file_path && $page !== 'default') { // Only show error if not default and file missing
        echo "<div class='admin-alert admin-alert-danger'>Error: The content file for '<strong>" . escape($page) . "</strong>' was not found at '<code>" . escape($page_file_path) . "</code>'.</div>";
    } elseif ($page === 'default' && !file_exists($page_file_path)) {
         echo "<h1>Page Not Found</h1><p>The requested admin page was not found.</p>";
    }

    ?>
</div>

<?php
$pdo = null;
?>