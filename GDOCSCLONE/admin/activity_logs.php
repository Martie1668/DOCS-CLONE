<?php
require_once "core/dbconfig.php";
require_once "core/models.php";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

// Debug information
error_log("Fetching activity logs...");

// Get all activity logs
$logs_result = getActivityLogs($pdo, 1000); // Get up to 1000 logs
error_log("Activity logs result: " . print_r($logs_result, true));

$activity_logs = $logs_result['status'] === "200" ? $logs_result['logs'] : [];

// Debug information
if ($logs_result['status'] !== "200") {
    error_log("Error fetching activity logs: " . $logs_result['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Simple Header -->
    <header class="bg-white shadow-sm mb-6">
        <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="index.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
            <h1 class="text-xl font-semibold text-gray-800">Activity Logs</h1>
            <a href="login.php" class="text-red-600 hover:text-red-800">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4">
        <div class="bg-white rounded-lg shadow">
            <!-- Summary -->
            <div class="p-4 border-b bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        <?php if (!empty($activity_logs)): ?>
                            Showing <?php echo count($activity_logs); ?> of <?php echo $logs_result['total'] ?? 'unknown'; ?> logs
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>
                        Last 24 hours
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <?php if (empty($activity_logs)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-history text-4xl mb-3"></i>
                    <p>No activity logs found</p>
                    <?php if ($logs_result['status'] !== "200"): ?>
                        <div class="mt-2 text-sm text-red-500">
                            <?php echo htmlspecialchars($logs_result['message']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Time</th>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Action</th>
                                <th class="px-4 py-3">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($activity_logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                        <?php echo date('M d, h:i A', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-gray-200 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-600">
                                                    <?php echo strtoupper(substr($log['first_name'], 0, 1) . substr($log['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    @<?php echo htmlspecialchars($log['username']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php
                                        $actionLabels = [
                                            'login' => ['label' => 'Login', 'color' => 'bg-green-100 text-green-800'],
                                            'logout' => ['label' => 'Logout', 'color' => 'bg-gray-100 text-gray-800'],
                                            'create_document' => ['label' => 'Create', 'color' => 'bg-blue-100 text-blue-800'],
                                            'edit_document' => ['label' => 'Edit', 'color' => 'bg-yellow-100 text-yellow-800'],
                                            'delete_document' => ['label' => 'Delete', 'color' => 'bg-red-100 text-red-800'],
                                            'suspend_user' => ['label' => 'Suspend', 'color' => 'bg-red-100 text-red-800'],
                                            'unsuspend_user' => ['label' => 'Unsuspend', 'color' => 'bg-green-100 text-green-800']
                                        ];
                                        $action = $actionLabels[$log['action_type']] ?? ['label' => ucfirst($log['action_type']), 'color' => 'bg-gray-100 text-gray-800'];
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $action['color']; ?>">
                                            <?php echo $action['label']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?php 
                                        if (isset($log['formatted_details'])) {
                                            echo htmlspecialchars($log['formatted_details']);
                                        } else {
                                            echo htmlspecialchars($log['action_details'] ?? 'No details available');
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
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