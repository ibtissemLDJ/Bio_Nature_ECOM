<?php
// admin_customers.php
// Included by admin.php
// Expects $conn (database connection) to be available.

// Ensure database connection is available ($conn)
if (!isset($conn) || $conn->connect_error) {
   // If conn is not set or connection failed, display an error message.
   echo "<div class='message error'>Database connection not available. Please check your connection settings.</div>";
   // Prevent further execution of DB-dependent code in this file
   return; // Stop execution of this include file
}

// This script primarily just displays the customer list when included,
// unless called with specific actions which are not defined in the provided snippet.
// Assuming the default action when this file is included is to display the list.
display_customer_list($conn);

// --- Function to Display Customer List ---

function display_customer_list($conn) {
   echo "<h2>Manage Customers</h2>";

   // Fetch users with the 'customer' role from the database
   // Using the correct table name 'users' (lowercase)
   $sql = "SELECT user_id, username, email, registration_date FROM users WHERE role = 'customer' ORDER BY registration_date DESC"; // Corrected table name
   $result = $conn->query($sql);

   if ($result === FALSE) {
       echo "<div class='message error'>Error fetching customers: " . $conn->error . "</div>"; // Display specific DB error for admin
       return; // Stop execution of this function
   }

   if ($result->num_rows > 0) {
      echo "<table class='admin-table'>"; // Added class for potential admin table styling
      echo "<thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Registration Date</th><th>Actions</th></tr></thead>";
      echo "<tbody>";
      while($row = $result->fetch_assoc()) {
         echo "<tr>";
         // Using htmlspecialchars for safety when displaying data
         echo "<td data-label='ID'>" . htmlspecialchars($row['user_id']) . "</td>";
         echo "<td data-label='Username'>" . htmlspecialchars($row['username']) . "</td>";
         echo "<td data-label='Email'>" . htmlspecialchars($row['email']) . "</td>";
         echo "<td data-label='Registration Date'>" . htmlspecialchars($row['registration_date']) . "</td>";
         echo "<td data-label='Actions'>";
         // Link to view customer's orders - assuming admin.php handles 'view_customer' action
         echo "<a href='?action=view_customer_orders&id=" . htmlspecialchars($row['user_id']) . "' class='button view-button'>View Orders</a>"; // Changed action name for clarity, added button class
             // Optional: Add Edit/Delete actions if needed for customer accounts (be cautious with admin permissions)
             // echo "<a href='?action=edit_customer&id=" . htmlspecialchars($row['user_id']) . "' class='button edit-button'>Edit</a>";
             // echo "<a href='?action=delete_customer&id=" . htmlspecialchars($row['user_id']) . "' class='button delete-button' onclick='return confirm(\"Are you sure you want to delete this customer account?\");'>Delete</a>";

         echo "</td>";
         echo "</tr>";
      }
      echo "</tbody>";
      echo "</table>";
   } else {
      echo "<p>No customers found.</p>";
   }
   $result->free(); // Free result set
}

// No $conn->close() needed here as $conn is expected to be managed by admin.php

?>