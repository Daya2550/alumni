<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Simple test to check if delete functionality works
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_delete'])) {
    $user_id = $_POST['user_id'] ?? '';
    
    if ($user_id) {
        try {
            // Test if user exists
            $user = db()->fetchOne("SELECT id, name, role FROM users WHERE id = ?", [$user_id]);
            
            if ($user) {
                echo "<div class='alert alert-info'>User found: " . htmlspecialchars($user['name']) . " (Role: " . $user['role'] . ")</div>";
                
                if ($user['role'] !== 'admin') {
                    // Test delete
                    db()->execute("DELETE FROM users WHERE id = ?", [$user_id]);
                    echo "<div class='alert alert-success'>User deleted successfully!</div>";
                } else {
                    echo "<div class='alert alert-warning'>Cannot delete admin users</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>User not found</div>";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Get a list of users for testing
$users = db()->fetchAll("SELECT id, name, role FROM users ORDER BY created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Delete Functionality</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1>Test Delete Functionality</h1>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Users List</h3>
                <table class="table">
                    <thead>
                        <tr><th>Name</th><th>Role</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="test_delete" class="btn btn-danger btn-sm">
                                            Test Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <a href="user_management.php" class="btn btn-primary">Back to User Management</a>
    </div>
</body>
</html>




