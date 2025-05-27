<?php
require_once "core/dbconfig.php";
require_once "core/models.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    $_SESSION['message'] = "You need to have an admin account first";
    $_SESSION['status'] = "400";
    header("Location: login.php");
    exit();
}

// Get dashboard statistics
$stats_result = getAdminDashboardStats($pdo);
$stats = $stats_result['status'] === "200" ? $stats_result['stats'] : [
    'total_documents' => 0,
    'total_users' => 0,
    'recent_activities' => 0
];

// Get all documents
$documents_result = getAllDocuments($pdo);
$documents = $documents_result['status'] === "200" ? $documents_result['documents'] : [];

// Get all users
$users_result = getAllUsers($pdo);
$users = $users_result['status'] === "200" ? $users_result['users'] : [];

// Debug information
if (isset($users_result['debug'])) {
    error_log("Debug info: " . print_r($users_result['debug'], true));
}

// Get activity logs
$logs_result = getActivityLogs($pdo);
$activity_logs = $logs_result['status'] === "200" ? $logs_result['logs'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-100">
    <!-- Navigation Bar -->
    <nav class="bg-slate-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="#" class="flex items-center text-white">
                        <i class="fas fa-file-word text-indigo-400 text-2xl mr-2"></i>
                        <span class="text-xl font-semibold">Admin Dashboard</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" placeholder="Search users..." class="px-4 py-2 rounded-lg border border-slate-600 bg-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <a href="activity_logs.php" class="text-indigo-400 hover:text-indigo-300 flex items-center">
                        <i class="fas fa-history mr-1"></i>
                        Activity Logs
                    </a>
                    <div class="flex items-center space-x-2">
                        <img src="https://ui-avatars.com/api/?name=Admin" alt="Admin" class="w-8 h-8 rounded-full">
                        <span class="text-slate-300">Admin</span>
                        <a href="login.php" class="text-red-400 hover:text-red-600">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-file-alt text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Total Documents</h3>
                        <p class="text-2xl font-semibold"><?php echo $stats['total_documents']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Active Users</h3>
                        <p class="text-2xl font-semibold"><?php echo $stats['total_users']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Recent Activities</h3>
                        <p class="text-2xl font-semibold"><?php echo $stats['recent_activities']; ?></p>
                    </div>
                </div>
            </div>
            <a href="activity_logs.php" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                        <i class="fas fa-history text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">View Activity Logs</h3>
                        <p class="text-lg font-semibold text-indigo-600">Detailed Logs →</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Document Management -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold flex items-center">
                    <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                    User Documents
                </h2>
                <p class="text-gray-600 mt-1">View and manage all documents created by users</p>
            </div>
            <div class="p-6">
                <?php if (empty($documents)): ?>
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-file-alt text-4xl mb-4"></i>
                    <p>No documents available</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Edited</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documents as $doc): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-file-word text-blue-600 text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($doc['title']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                ID: <?php echo $doc['document_id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doc['first_name'] . ' ' . $doc['last_name']); ?>" 
                                             alt="<?php echo htmlspecialchars($doc['first_name']); ?>" 
                                             class="w-8 h-8 rounded-full mr-2">
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                                            </div>
                                            <div class="text-gray-500">
                                                @<?php echo htmlspecialchars($doc['username']); ?>
                                                <?php if ($doc['is_admin']): ?>
                                                <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-purple-100 text-purple-800 rounded-full">Admin</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y, h:i A', strtotime($doc['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($doc['last_edited_at']): ?>
                                        <?php echo date('M d, Y, h:i A', strtotime($doc['last_edited_at'])); ?>
                                        <div class="text-xs text-gray-400">
                                            <?php
                                            $diff = time() - strtotime($doc['last_edited_at']);
                                            if ($diff < 60) {
                                                echo 'Just now';
                                            } elseif ($diff < 3600) {
                                                echo floor($diff / 60) . ' minutes ago';
                                            } elseif ($diff < 86400) {
                                                echo floor($diff / 3600) . ' hours ago';
                                            } else {
                                                echo floor($diff / 86400) . ' days ago';
                                            }
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-3">
                                        <a href="../user/document.php?id=<?php echo $doc['document_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900 flex items-center"
                                           title="View Document">
                                            <i class="fas fa-eye mr-1"></i>
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Management -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">User Management</h2>
                <?php if ($users_result['status'] !== "200"): ?>
                <div class="mt-2 text-red-600">
                    <?php echo htmlspecialchars($users_result['message']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <?php if (empty($users)): ?>
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-users text-4xl mb-4"></i>
                    <p>No users available</p>
                    <?php if (isset($users_result['debug'])): ?>
                    <div class="mt-4 text-sm text-gray-400">
                        Debug Info:
                        <pre class="mt-2 text-left"><?php echo htmlspecialchars(print_r($users_result['debug'], true)); ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>" 
                                             alt="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                             class="w-8 h-8 rounded-full mr-2">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($user['email_address']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['is_admin'] ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['is_suspended']): ?>
                                    <div class="flex items-center">
                                        <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">
                                            Suspended
                                        </span>
                                        <?php if ($user['suspended_by']): ?>
                                        <div class="ml-2 text-xs text-gray-500">
                                            by <?php echo htmlspecialchars($user['suspended_by_first_name'] . ' ' . $user['suspended_by_last_name']); ?>
                                            <div><?php echo date('M d, Y', strtotime($user['suspended_at'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">
                                        Active
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($user['date_added'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <?php if ($user['is_suspended']): ?>
                                        <button onclick="unsuspendUser(<?php echo $user['user_id']; ?>)" 
                                                class="text-green-600 hover:text-green-900 mr-3">
                                            Unsuspend
                                        </button>
                                        <?php else: ?>
                                        <button onclick="suspendUser(<?php echo $user['user_id']; ?>)" 
                                                class="text-red-600 hover:text-red-900">
                                            Suspend
                                        </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

            </div>
        </div>
    </div>

    <script>
    // Add this at the top of your script section
    const showNotification = (message, type = 'success') => {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        } text-white transition-opacity duration-300`;
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    };

    const updateUserStatus = (userId, isSuspended) => {
        const userRow = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (!userRow) return;

        const statusCell = userRow.querySelector('.user-status');
        const actionCell = userRow.querySelector('.user-actions');
        
        if (statusCell && actionCell) {
            if (isSuspended) {
                statusCell.innerHTML = `
                    <div class="flex items-center">
                        <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">
                            Suspended
                        </span>
                    </div>
                `;
                actionCell.innerHTML = `
                    <button onclick="unsuspendUser(${userId})" 
                            class="text-green-600 hover:text-green-900 mr-3">
                        Unsuspend
                    </button>
                `;
            } else {
                statusCell.innerHTML = `
                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">
                        Active
                    </span>
                `;
                actionCell.innerHTML = `
                    <button onclick="suspendUser(${userId})" 
                            class="text-red-600 hover:text-red-900">
                        Suspend
                    </button>
                `;
            }
        }
    };

    // Simple suspend user function
    function suspendUser(userId) {
        if (confirm('Are you sure you want to suspend this user?')) {
            fetch('../core/handleforms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'suspendUserBtn=1&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "200") {
                    showNotification('User suspended successfully');
                    updateUserStatus(userId, true);
                } else {
                    showNotification(data.message || 'Failed to suspend user', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while suspending the user', 'error');
            });
        }
    }

    // Simple unsuspend user function
    function unsuspendUser(userId) {
        if (confirm('Are you sure you want to unsuspend this user?')) {
            fetch('../core/handleforms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'unsuspendUserBtn=1&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "200") {
                    showNotification('User unsuspended successfully');
                    updateUserStatus(userId, false);
                } else {
                    showNotification(data.message || 'Failed to unsuspend user', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while unsuspending the user', 'error');
            });
        }
    }

    // Update the table rows to include data attributes
    document.addEventListener('DOMContentLoaded', () => {
        const userRows = document.querySelectorAll('tbody tr');
        userRows.forEach(row => {
            const userId = row.querySelector('button')?.getAttribute('onclick')?.match(/\d+/)?.[0];
            if (userId) {
                row.setAttribute('data-user-id', userId);
                const statusCell = row.querySelector('td:nth-child(4)');
                const actionCell = row.querySelector('td:nth-child(6)');
                if (statusCell) statusCell.classList.add('user-status');
                if (actionCell) actionCell.classList.add('user-actions');
            }
        });
    });

    // Check user suspension status periodically
    function checkSuspensionStatus() {
        fetch('../core/handleforms.php?checkUserSuspension=1')
            .then(response => response.json())
            .then(data => {
                if (data.status === "200" && data.is_suspended) {
                    window.location.href = 'login.php?message=suspended';
                }
            })
            .catch(error => console.error('Error checking suspension status:', error));
    }

    // Check suspension status every minute
    setInterval(checkSuspensionStatus, 60000);
    </script>
</body>
</html>