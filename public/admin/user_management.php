<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin', 'staff']);

// Get current user
$current_user = Auth::getCurrentUser();

// Handle export
if (isset($_GET['export'])) {
    $format = $_GET['format'] ?? 'csv';
    $export_url = "../api/user_export.php?" . http_build_query(array_merge($_GET, ['format' => $format]));
    header('Location: ' . $export_url);
    exit;
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    if ($action === 'delete' && $user_id) {
        // Prevent deleting admins
        $target_user = db()->fetchOne("SELECT role FROM users WHERE id = ?", [$user_id]);
        if ($target_user && $target_user['role'] !== 'admin') {
            try {
                db()->beginTransaction();
                
                // Disable foreign key checks temporarily
                db()->execute("SET FOREIGN_KEY_CHECKS = 0");
                
                // Clean up related data
                $cleanup_queries = [
                    "DELETE FROM user_sessions WHERE user_id = ?",
                    "DELETE FROM notifications WHERE user_id = ?", 
                    "DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?",
                    "DELETE FROM notices WHERE created_by = ?",
                    "DELETE FROM news WHERE created_by = ?",
                    "DELETE FROM jobs WHERE created_by = ?",
                    "DELETE FROM events WHERE created_by = ?",
                    "DELETE FROM gallery WHERE uploaded_by = ?",
                    "DELETE FROM feed WHERE created_by = ?",
                    "DELETE FROM surveys WHERE owner_id = ?",
                    "DELETE FROM survey_responses WHERE user_id = ?",
                    "DELETE FROM feedback WHERE user_id = ?"
                ];
                
                foreach ($cleanup_queries as $query) {
                    try {
                        if (strpos($query, 'sender_id') !== false || strpos($query, 'receiver_id') !== false) {
                            db()->execute($query, [$user_id, $user_id]);
                        } else {
                            db()->execute($query, [$user_id]);
                        }
                        error_log("Cleanup query executed: $query");
                    } catch (Exception $e) {
                        error_log("Cleanup query failed (table might not exist): $query - " . $e->getMessage());
                        // Continue with other cleanup queries
                    }
                }
                
                // Now delete the user
                $result = db()->execute("DELETE FROM users WHERE id = ?", [$user_id]);
                
                // Re-enable foreign key checks
                db()->execute("SET FOREIGN_KEY_CHECKS = 1");
                
                if ($result) {
                    db()->commit();
                    $success_message = "User permanently deleted from database.";
                    error_log("User deleted successfully: ID $user_id");
                } else {
                    throw new Exception("No rows were affected by the delete operation");
                }
                
            } catch (Exception $e) {
                // Re-enable foreign key checks in case of error
                try {
                    db()->execute("SET FOREIGN_KEY_CHECKS = 1");
                } catch (Exception $e2) {
                    error_log("Failed to re-enable foreign key checks: " . $e2->getMessage());
                }
                
                db()->rollback();
                $error_message = "Failed to delete user: " . $e->getMessage();
                error_log("Delete user error: " . $e->getMessage());
                error_log("Delete user error trace: " . $e->getTraceAsString());
            }
        } else {
            $error_message = "Cannot delete admin accounts.";
        }
    } elseif ($action === 'toggle_status' && $user_id) {
        $new_status = $_POST['status'] ?? '';
        if (in_array($new_status, ['0', '1'])) {
            try {
                db()->execute("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?", [$new_status, $user_id]);
                $success_message = "User status updated successfully.";
            } catch (Exception $e) {
                $error_message = "Failed to update user status: " . $e->getMessage();
            }
        }
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$batch_filter = $_GET['batch'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ['1=1'];
$params = [];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = (int)$status_filter;
}

if ($batch_filter) {
    $where_conditions[] = "batch = ?";
    $params[] = $batch_filter;
}

$where_sql = implode(' AND ', $where_conditions);

// Get total count
$total_users = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE $where_sql", $params)['count'];
$total_pages = ceil($total_users / $per_page);

// Get users
$users = db()->fetchAll(
    "SELECT id, name, email, mobile, role, batch, department, profile_photo, created_at, updated_at, is_active, email_verified 
     FROM users 
     WHERE $where_sql 
     ORDER BY $sort_by $sort_order 
     LIMIT $per_page OFFSET $offset",
    $params
);

// Get statistics
$stats = [
    'total' => db()->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
    'active' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'students' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1")['count'],
    'alumni' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'alumni' AND is_active = 1")['count'],
    'staff' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND is_active = 1")['count'],
    'admins' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1")['count']
];

// Get unique batches and departments for filters
$batches = db()->fetchAll("SELECT DISTINCT batch FROM users WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC");
$departments = db()->fetchAll("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .user-row {
            transition: background-color 0.2s;
        }
        .user-row:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
    
    <body>
        <?php include '../includes/header.php'; ?>
        
        <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="fas fa-users text-primary"></i> User Management</h1>
                <p class="text-muted mb-0">Manage all users, view data, and perform administrative actions</p>
            </div>
            <div>
                <div class="btn-group">
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Export Users
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportUsers('csv')">
                            <i class="fas fa-file-csv"></i> Export as CSV
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportUsers('excel')">
                            <i class="fas fa-file-excel"></i> Export as Excel
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportUsers('json')">
                            <i class="fas fa-file-code"></i> Export as JSON
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                        <small>Total Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['active']; ?></h4>
                        <small>Active Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['students']; ?></h4>
                        <small>Students</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-tie fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['alumni']; ?></h4>
                        <small>Alumni</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-secondary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['staff']; ?></h4>
                        <small>Staff</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-dark text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-crown fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo $stats['admins']; ?></h4>
                        <small>Admins</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filters & Search</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, or mobile">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="">All Roles</option>
                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="alumni" <?php echo $role_filter === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                            <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Batch</label>
                        <select class="form-select" name="batch">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo htmlspecialchars($batch['batch']); ?>" <?php echo $batch_filter === $batch['batch'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['batch']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Join Date</option>
                            <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                            <option value="role" <?php echo $sort_by === 'role' ? 'selected' : ''; ?>>Role</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Order</label>
                        <select class="form-select" name="order">
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Desc</option>
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Asc</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="user_management.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Users (<?php echo $total_users; ?> total)</h5>
                <div>
                    <span class="text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Role & Batch</th>
                                <th>Status</th>
                                <th>Join Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="user-row">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($user['profile_photo']): ?>
                                                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                                     class="rounded-circle user-avatar me-3" alt="Profile">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center user-avatar me-3">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <?php if ($user['department']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['department']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="small"><?php echo htmlspecialchars($user['email']); ?></div>
                                            <?php if ($user['mobile']): ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($user['mobile']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'dark' : ($user['role'] === 'staff' ? 'secondary' : ($user['role'] === 'alumni' ? 'warning' : 'info')); ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                            <?php if ($user['batch']): ?>
                                                <div class="small text-muted mt-1"><?php echo htmlspecialchars($user['batch']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            <div class="text-muted"><?php echo date('g:i A', strtotime($user['created_at'])); ?></div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewUserDetails('<?php echo $user['id']; ?>')" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <?php if ($user['is_active']): ?>
                                                    <button class="btn btn-outline-warning" onclick="toggleUserStatus('<?php echo $user['id']; ?>', '0')" title="Deactivate User">
                                                        <i class="fas fa-ban"></i> Block
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-success" onclick="toggleUserStatus('<?php echo $user['id']; ?>', '1')" title="Activate User">
                                                        <i class="fas fa-check"></i> Activate
                                                    </button>
                                                <?php endif; ?>
                                            <button class="btn btn-outline-info" onclick="debugUser('<?php echo $user['id']; ?>')" title="Debug User">
                                                <i class="fas fa-bug"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteUser('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['name']); ?>')" title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="User pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user"></i> User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to <strong class="text-danger">PERMANENTLY DELETE</strong> user <strong id="deleteUserName"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will completely remove the user from the database. This cannot be undone!
                    </div>
                    <p class="text-muted small">The user and all their associated data will be permanently deleted from the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Permanently Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View user details
        function viewUserDetails(userId) {
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;

            fetch(`../api/user_details_simple.php?id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('userDetailsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
                    } else {
                        showAlert('danger', 'Failed to load user details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'Failed to load user details. Please try again.');
                })
                .finally(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }

        // Toggle user status
        function toggleUserStatus(userId, newStatus) {
            const action = newStatus === '1' ? 'activate' : 'block';
            const actionText = newStatus === '1' ? 'activate' : 'block';
            if (!confirm(`Are you sure you want to ${actionText} this user?`)) return;

            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('user_id', userId);
            formData.append('status', newStatus);

            // Show loading state
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;

            fetch('user_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(() => {
                // Show success message
                showAlert('success', `User ${actionText}d successfully!`);
                // Reload page after a short delay
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', `Failed to ${actionText} user. Please try again.`);
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        // Debug user
        function debugUser(userId) {
            fetch(`debug_delete.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Debug info for user:', data.debug_info);
                        alert('Debug info logged to console. Check browser console for details.\n\nConstraints found: ' + data.debug_info.constraints.length);
                    } else {
                        alert('Debug failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Debug error:', error);
                    alert('Debug failed: ' + error.message);
                });
        }

        // Delete user
        function deleteUser(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Show alert function
        function showAlert(type, message) {
            const alertContainer = document.querySelector('.container-fluid');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.insertBefore(alertDiv, alertContainer.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Export users
        function exportUsers(format = 'csv') {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            params.set('format', format);
            window.open('user_management.php?' + params.toString(), '_blank');
        }

        // Add confirmation to delete form
        document.addEventListener('DOMContentLoaded', function() {
            const deleteForm = document.getElementById('deleteForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const userName = document.getElementById('deleteUserName').textContent;
                    if (confirm(`Are you absolutely sure you want to PERMANENTLY DELETE user "${userName}"?\n\nThis action cannot be undone!`)) {
                        // Show loading state
                        const submitBtn = this.querySelector('button[type="submit"]');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                        submitBtn.disabled = true;
                        
                        // Submit the form
                        this.submit();
                    }
                });
            }
        });
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
