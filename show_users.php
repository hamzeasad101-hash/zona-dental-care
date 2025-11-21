<?php
include 'config.php';

echo "<h1>Database Users</h1>";

try {
    $users = $pdo->query("SELECT username, full_name, role, created_at FROM users WHERE is_active = 1")->fetchAll();
    
    if (empty($users)) {
        echo "<p>No users found in database.</p>";
        echo "<a href='setup_default_user.php'>Create Default User</a>";
    } else {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Username</th><th>Full Name</th><th>Role</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td><strong>" . $user['username'] . "</strong></td>";
            echo "<td>" . $user['full_name'] . "</td>";
            echo "<td>" . $user['role'] . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>