<?php
require_once __DIR__ . '/dbconfig.php';

function checkIfUserExists($pdo, $username) {
  $response = [];
  $sql = "SELECT * FROM users WHERE username = ?";
  $stmt = $pdo->prepare($sql);

  if ($stmt->execute([$username])) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
      $response = [
        "result" => true,
        "status" => "200",
        "userInfoArray" => $user
      ];
    } else {
      $response = [
        "result" => false,
        "status" => "400",
        "message" => "User not found."
      ];
    }
  }
  return $response;
}

function insertNewUser($pdo, $username, $email_address, $first_name, $last_name, $password) {
  $response = [];
  $check = checkIfUserExists($pdo, $username);

  if (!$check['result']) {
    $sql = "INSERT INTO users (username, email_address, first_name, last_name, is_admin, password) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$username, $email_address, $first_name, $last_name, true, $password])) {
      $response = [
        "status" => "200",
        "message" => "Admin registered successfully!"
      ];
    } else {
      $response = [
        "status" => "400",
        "message" => "Failed to register admin."
      ];
    }
  } else {
    $response = [
      "status" => "400",
      "message" => "Username already taken."
    ];
  }

  return $response;
}

// Admin Functions
function getAllDocuments($pdo) {
    $response = [];
    try {
        $sql = "SELECT d.*, u.username, u.first_name, u.last_name, u.email_address, u.is_admin 
                FROM documents d 
                JOIN users u ON d.user_id = u.user_id 
                ORDER BY d.last_edited_at DESC";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute()) {
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = [
                "status" => "200",
                "message" => "Documents retrieved successfully",
                "documents" => $documents
            ];
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to retrieve documents"
            ];
        }
    } catch (PDOException $e) {
        $response = [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
    return $response;
}

function getActivityLogs($pdo, $limit = 50) {
    $response = [];
    try {
        error_log("Getting activity logs with limit: " . $limit);
        
        // First verify the table exists
        $check_table_sql = "SHOW TABLES LIKE 'activity_logs'";
        $table_exists = $pdo->query($check_table_sql)->rowCount() > 0;
        
        if (!$table_exists) {
            error_log("Activity logs table does not exist!");
            return [
                "status" => "400",
                "message" => "Activity logs table does not exist"
            ];
        }

        // Get total count of logs
        $count_sql = "SELECT COUNT(*) FROM activity_logs";
        $total_logs = $pdo->query($count_sql)->fetchColumn();
        error_log("Total activity logs in database: " . $total_logs);

        // Get the logs with user information - using LIMIT directly in the query
        $sql = "SELECT al.*, 
                u.username, 
                u.first_name, 
                u.last_name,
                u.is_admin,
                s.username as suspended_by_username,
                s.first_name as suspended_by_first_name,
                s.last_name as suspended_by_last_name
                FROM activity_logs al 
                LEFT JOIN users u ON al.user_id = u.user_id 
                LEFT JOIN users s ON al.action_details LIKE '%\"suspended_by\":%' 
                    AND JSON_EXTRACT(al.action_details, '$.suspended_by') = s.user_id
                ORDER BY al.created_at DESC 
                LIMIT " . intval($limit); // Safely cast limit to integer
        
        error_log("Executing SQL: " . $sql);
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute()) { // No parameters needed now
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Retrieved " . count($logs) . " activity logs");
            
            // Format the action details for better display
            foreach ($logs as &$log) {
                if ($log['action_details']) {
                    $details = json_decode($log['action_details'], true);
                    if ($details) {
                        // Format the details based on action type
                        switch ($log['action_type']) {
                            case 'suspend_user':
                                $log['formatted_details'] = "Suspended user ID: " . ($details['suspended_user_id'] ?? 'N/A');
                                break;
                            case 'unsuspend_user':
                                $log['formatted_details'] = "Unsuspended user ID: " . ($details['unsuspended_user_id'] ?? 'N/A');
                                break;
                            case 'delete_document':
                                $log['formatted_details'] = "Deleted document ID: " . ($details['document_id'] ?? 'N/A');
                                break;
                            default:
                                $log['formatted_details'] = $log['action_details'];
                        }
                    }
                }
            }
            
            $response = [
                "status" => "200",
                "message" => "Activity logs retrieved successfully",
                "logs" => $logs,
                "total" => $total_logs
            ];
        } else {
            $error = $stmt->errorInfo();
            error_log("SQL Error in getActivityLogs: " . print_r($error, true));
            $response = [
                "status" => "400",
                "message" => "Failed to retrieve activity logs: " . $error[2]
            ];
        }
    } catch (PDOException $e) {
        error_log("Database error in getActivityLogs: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $response = [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
    return $response;
}

function getAdminDashboardStats($pdo) {
    $response = [];
    try {
        // Get total documents count
        $documents_sql = "SELECT COUNT(*) FROM documents";
        $total_documents = $pdo->query($documents_sql)->fetchColumn();

        // Get total users count (excluding admins)
        $users_sql = "SELECT COUNT(*) FROM users WHERE is_admin = 0";
        $total_users = $pdo->query($users_sql)->fetchColumn();

        // Get recent activities count (last 24 hours)
        $activities_sql = "SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $recent_activities = $pdo->query($activities_sql)->fetchColumn();

        $response = [
            "status" => "200",
            "message" => "Dashboard statistics retrieved successfully",
            "stats" => [
                "total_documents" => $total_documents,
                "total_users" => $total_users,
                "recent_activities" => $recent_activities
            ]
        ];
    } catch (PDOException $e) {
        $response = [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
    return $response;
}

function adminDeleteDocument($pdo, $document_id) {
    $response = [];
    try {
        // Delete document shares first (due to foreign key constraint)
        $delete_shares_sql = "DELETE FROM document_shares WHERE document_id = ?";
        $delete_shares_stmt = $pdo->prepare($delete_shares_sql);
        $delete_shares_stmt->execute([$document_id]);
        
        // Delete the document
        $delete_sql = "DELETE FROM documents WHERE document_id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        
        if ($delete_stmt->execute([$document_id])) {
            // Log the document deletion by admin
            $action_details = json_encode([
                'document_id' => $document_id,
                'deleted_by_admin' => true
            ]);
            logActivity($pdo, $_SESSION['user_id'], 'delete_document', $action_details);
            
            $response = [
                "status" => "200",
                "message" => "Document deleted successfully"
            ];
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to delete document"
            ];
        }
    } catch (PDOException $e) {
        $response = [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
    return $response;
}

function suspendUser($pdo, $user_id_to_suspend, $suspended_by_user_id) {
    try {
        // Log the incoming parameters
        error_log("Attempting to suspend user. User ID to suspend: " . $user_id_to_suspend . ", Suspended by: " . $suspended_by_user_id);
        error_log("Session data: " . print_r($_SESSION, true));

        // Verify admin status
        $admin_check_sql = "SELECT is_admin FROM users WHERE user_id = ?";
        $admin_check_stmt = $pdo->prepare($admin_check_sql);
        $admin_check_stmt->execute([$suspended_by_user_id]);
        $admin = $admin_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin || !$admin['is_admin']) {
            error_log("Permission denied. User " . $suspended_by_user_id . " is not an admin");
            return [
                "status" => "403",
                "message" => "Permission denied. Admin access required."
            ];
        }

        // Check if the user exists and is not already suspended
        $check_sql = "SELECT user_id, is_suspended, username FROM users WHERE user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id_to_suspend]);
        $user = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("User not found for suspension: " . $user_id_to_suspend);
            return [
                "status" => "404",
                "message" => "User not found"
            ];
        }

        if ($user['is_suspended']) {
            error_log("User already suspended: " . $user['username'] . " (ID: " . $user_id_to_suspend . ")");
            return [
                "status" => "400",
                "message" => "User is already suspended"
            ];
        }

        // Update user suspension status
        $update_sql = "UPDATE users SET is_suspended = 1, suspended_at = NOW(), suspended_by = ? WHERE user_id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        
        if (!$update_stmt->execute([$suspended_by_user_id, $user_id_to_suspend])) {
            $error = $update_stmt->errorInfo();
            error_log("Failed to suspend user. SQL Error: " . print_r($error, true));
            return [
                "status" => "400",
                "message" => "Failed to suspend user: " . $error[2]
            ];
        }

        // Log the suspension
        $action_details = json_encode([
            'suspended_user_id' => $user_id_to_suspend,
            'suspended_user_username' => $user['username'],
            'suspended_by' => $suspended_by_user_id,
            'suspended_at' => date('Y-m-d H:i:s')
        ]);
        
        $log_result = logActivity($pdo, $suspended_by_user_id, 'suspend_user', $action_details);
        error_log("Activity log result: " . ($log_result ? "success" : "failed"));
        
        error_log("User suspended successfully: " . $user['username'] . " (ID: " . $user_id_to_suspend . ")");
        return [
            "status" => "200",
            "message" => "User suspended successfully"
        ];

    } catch (PDOException $e) {
        error_log("Database error while suspending user: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
}

function unsuspendUser($pdo, $user_id_to_unsuspend, $unsuspended_by_user_id) {
    $response = [];
    try {
        // Check if the user exists and is suspended
        $check_sql = "SELECT user_id, is_suspended FROM users WHERE user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id_to_unsuspend]);
        $user = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("User not found for unsuspension: " . $user_id_to_unsuspend);
            return [
                "status" => "404",
                "message" => "User not found"
            ];
        }

        if (!$user['is_suspended']) {
            error_log("User not suspended: " . $user_id_to_unsuspend);
            return [
                "status" => "400",
                "message" => "User is not suspended"
            ];
        }

        // Update user suspension status
        $update_sql = "UPDATE users 
                      SET is_suspended = 0, 
                          suspended_at = NULL, 
                          suspended_by = NULL 
                      WHERE user_id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        
        if ($update_stmt->execute([$user_id_to_unsuspend])) {
            // Log the unsuspension
            $action_details = json_encode([
                'unsuspended_user_id' => $user_id_to_unsuspend,
                'unsuspended_by' => $unsuspended_by_user_id,
                'unsuspended_at' => date('Y-m-d H:i:s')
            ]);
            logActivity($pdo, $unsuspended_by_user_id, 'unsuspend_user', $action_details);
            
            error_log("User unsuspended successfully: " . $user_id_to_unsuspend);
            $response = [
                "status" => "200",
                "message" => "User unsuspended successfully"
            ];
        } else {
            error_log("Failed to unsuspend user: " . $user_id_to_unsuspend);
            $response = [
                "status" => "400",
                "message" => "Failed to unsuspend user"
            ];
        }
    } catch (PDOException $e) {
        error_log("Database error while unsuspending user: " . $e->getMessage());
        $response = [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
    return $response;
}

function logActivity($pdo, $user_id, $action_type, $action_details = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // First check if the activity_logs table has the required columns
        $check_columns_sql = "SHOW COLUMNS FROM activity_logs LIKE 'ip_address'";
        $has_ip_column = $pdo->query($check_columns_sql)->rowCount() > 0;
        
        if (!$has_ip_column) {
            // Add missing columns if they don't exist
            $alter_sql = "ALTER TABLE activity_logs 
                         ADD COLUMN ip_address VARCHAR(45) NULL,
                         ADD COLUMN user_agent TEXT NULL";
            $pdo->exec($alter_sql);
        }
        
        // Insert the activity log
        $sql = "INSERT INTO activity_logs (user_id, action_type, action_details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$user_id, $action_type, $action_details, $ip_address, $user_agent])) {
            error_log("Activity logged successfully: " . $action_type . " by user " . $user_id);
            return true;
        } else {
            error_log("Failed to log activity: " . $action_type . " by user " . $user_id);
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

function getAllUsers($pdo) {
    $response = [];
    try {
        // First, let's check if there are any users at all
        $check_sql = "SELECT COUNT(*) FROM users";
        $total_users = $pdo->query($check_sql)->fetchColumn();
        
        if ($total_users === 0) {
            return [
                "status" => "200",
                "message" => "No users found in the database",
                "users" => []
            ];
        }

        // Check if is_suspended column exists
        $check_column_sql = "SHOW COLUMNS FROM users LIKE 'is_suspended'";
        $column_exists = $pdo->query($check_column_sql)->rowCount() > 0;

        if (!$column_exists) {
            // Add is_suspended column if it doesn't exist
            $alter_sql = "ALTER TABLE users 
                         ADD COLUMN is_suspended TINYINT(1) DEFAULT 0,
                         ADD COLUMN suspended_at DATETIME DEFAULT NULL,
                         ADD COLUMN suspended_by INT DEFAULT NULL,
                         ADD FOREIGN KEY (suspended_by) REFERENCES users(user_id)";
            $pdo->exec($alter_sql);
        }

        $sql = "SELECT u.*, 
                s.username as suspended_by_username,
                s.first_name as suspended_by_first_name,
                s.last_name as suspended_by_last_name
                FROM users u 
                LEFT JOIN users s ON u.suspended_by = s.user_id
                ORDER BY u.date_added DESC";
        
        // Log the SQL query for debugging
        error_log("Executing SQL: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute()) {
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Log the number of users found
            error_log("Found " . count($users) . " users");
            
            $response = [
                "status" => "200",
                "message" => "Users retrieved successfully",
                "users" => $users,
                "debug" => [
                    "total_users" => $total_users,
                    "users_found" => count($users)
                ]
            ];
        } else {
            $error = $stmt->errorInfo();
            error_log("SQL Error: " . print_r($error, true));
            $response = [
                "status" => "400",
                "message" => "Failed to retrieve users: " . $error[2]
            ];
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $response = [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
    return $response;
}

function checkUserSuspension($pdo, $user_id) {
    $response = [];
    try {
        $sql = "SELECT is_suspended, suspended_at, suspended_by FROM users WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$user_id])) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $response = [
                    "status" => "200",
                    "is_suspended" => (bool)$user['is_suspended'],
                    "suspended_at" => $user['suspended_at'],
                    "suspended_by" => $user['suspended_by']
                ];
            } else {
                $response = [
                    "status" => "404",
                    "message" => "User not found"
                ];
            }
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to check user suspension status"
            ];
        }
    } catch (PDOException $e) {
        $response = [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
    return $response;
}

function addDocumentComment($pdo, $document_id, $user_id, $comment_text) {
    try {
        // First check if the comments table exists
        $check_table_sql = "SHOW TABLES LIKE 'document_comments'";
        $table_exists = $pdo->query($check_table_sql)->rowCount() > 0;
        
        if (!$table_exists) {
            // Create the comments table if it doesn't exist
            $create_table_sql = "CREATE TABLE document_comments (
                comment_id INT PRIMARY KEY AUTO_INCREMENT,
                document_id INT NOT NULL,
                user_id INT NOT NULL,
                comment_text TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            $pdo->exec($create_table_sql);
        }

        // Insert the comment
        $sql = "INSERT INTO document_comments (document_id, user_id, comment_text) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$document_id, $user_id, $comment_text])) {
            // Log the comment activity
            $action_details = json_encode([
                'document_id' => $document_id,
                'comment_id' => $pdo->lastInsertId()
            ]);
            logActivity($pdo, $user_id, 'add_comment', $action_details);
            
            return [
                "status" => "200",
                "message" => "Comment added successfully"
            ];
        } else {
            return [
                "status" => "400",
                "message" => "Failed to add comment"
            ];
        }
    } catch (PDOException $e) {
        error_log("Error adding comment: " . $e->getMessage());
        return [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
}

function getDocumentComments($pdo, $document_id) {
    try {
        $sql = "SELECT c.*, u.username, u.first_name, u.last_name, u.is_admin 
                FROM document_comments c 
                JOIN users u ON c.user_id = u.user_id 
                WHERE c.document_id = ? 
                ORDER BY c.created_at DESC";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$document_id])) {
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return [
                "status" => "200",
                "message" => "Comments retrieved successfully",
                "comments" => $comments
            ];
        } else {
            return [
                "status" => "400",
                "message" => "Failed to retrieve comments"
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting comments: " . $e->getMessage());
        return [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
}

function deleteDocumentComment($pdo, $comment_id, $user_id) {
    try {
        // First check if the user is the comment owner or an admin
        $check_sql = "SELECT c.*, u.is_admin 
                     FROM document_comments c 
                     JOIN users u ON c.user_id = u.user_id 
                     WHERE c.comment_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$comment_id]);
        $comment = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            return [
                "status" => "404",
                "message" => "Comment not found"
            ];
        }

        if ($comment['user_id'] != $user_id && !$comment['is_admin']) {
            return [
                "status" => "403",
                "message" => "Permission denied. You can only delete your own comments."
            ];
        }

        // Delete the comment
        $delete_sql = "DELETE FROM document_comments WHERE comment_id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        
        if ($delete_stmt->execute([$comment_id])) {
            // Log the deletion
            $action_details = json_encode([
                'comment_id' => $comment_id,
                'document_id' => $comment['document_id']
            ]);
            logActivity($pdo, $user_id, 'delete_comment', $action_details);
            
            return [
                "status" => "200",
                "message" => "Comment deleted successfully"
            ];
        } else {
            return [
                "status" => "400",
                "message" => "Failed to delete comment"
            ];
        }
    } catch (PDOException $e) {
        error_log("Error deleting comment: " . $e->getMessage());
        return [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
}