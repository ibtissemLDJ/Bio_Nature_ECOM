<?php
// admin_orders.php
// This file is typically included by a main admin dashboard file (like admin.php)
// that handles session checks and database connection via db_connection.php.

// Ensure database connection is available ($conn)
// This check relies on $conn being set by db_connection.php *before* this file is included.
// Line 8 is correct: Check if $conn is set and is a valid MySQLi object.
if (!isset($conn) || !($conn instanceof mysqli)) {
    // If $conn is not set or not a valid MySQLi object, there's a setup issue.
    // In a production environment, you might redirect to an error page or login.
    die("Database connection not available. Please ensure db_connection.php is correctly included."); // Added more specific message
}

// --- Display Order List ---
// This function is called directly when the file is included.
display_order_list($conn);

// --- Function to Display Order List ---
function display_order_list($conn) {
    echo "<h2>Manage Orders</h2>";

    // Fetch orders from the database, joining with Users to show customer username.
    // Use the correct table names 'orders' and 'users' (lowercase)
    $sql = "SELECT
                o.order_id,
                o.order_date,
                o.total,
                o.status,
                u.username AS customer_username
            FROM orders o -- Corrected table name
            JOIN users u ON o.user_id = u.user_id -- Corrected table name
            ORDER BY o.order_date DESC"; // Show most recent orders first

    $result = $conn->query($sql);

    // Check for SQL errors
    if ($result === FALSE) {
        echo "<div class='message error'>Error fetching orders: " . htmlspecialchars($conn->error) . "</div>"; // Display specific DB error for admin
        error_log("Admin Orders Error: Error fetching orders: " . $conn->error); // Log the error for debugging
        return; // Stop execution of the function
    }

    if ($result->num_rows > 0) {
        // Use CSS classes for styling (from admin.css)
        echo "<table class='admin-table order-list-table'>"; // Added class for potential admin table styling
        echo "<thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>";
        echo "<tbody>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            // Using htmlspecialchars for safety when displaying data
            echo "<td>" . htmlspecialchars($row['order_id']) . "</td>";
            // Format date and time
            echo "<td>" . htmlspecialchars(date('Y-m-d H:i', strtotime($row['order_date']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['customer_username']) . "</td>";
            // Format total as currency
            echo "<td>$" . htmlspecialchars(number_format($row['total'], 2)) . "</td>";
            // Add status class for potential color coding (e.g., status-pending, status-shipped)
            // Sanitize status for use in class name
            $status_class = strtolower(str_replace(' ', '-', $row['status']));
            echo "<td class='order-status status-" . htmlspecialchars($status_class) . "'>" . htmlspecialchars(ucfirst($row['status'])) . "</td>";
            echo "<td>";
            // Link to view order details. Assumes admin.php handles routing based on 'action' and 'id'.
            echo "<a href='?action=view_order&id=" . htmlspecialchars($row['order_id']) . "' class='button view-button'>View Details</a>";
            // TODO: Add more action buttons if needed (e.g., Edit Status, Print Invoice, Delete - caution with deletion)
            // Example: Edit status link/button (requires a form or JS for PUT/POST)
            // echo "<a href='#' class='button edit-button' data-order-id='" . htmlspecialchars($row['order_id']) . "'>Edit Status</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        // Message when no orders are in the DB
        echo "<p>No orders found.</p>";
    }

   $result->free(); // Free result set memory

    // TODO: Implement pagination if the number of orders grows large.
}

// Note: Database connection $conn should be closed at the very end of the main script (like admin.php)
// that includes this file, after all includes and processing are done.
?>