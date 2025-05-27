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

    if ($stmt->execute([$username, $email_address, $first_name, $last_name, false, $password])) {
      $response = [
        "status" => "200",
        "message" => "Account created successfully!"
      ];
    } else {
      $response = [
        "status" => "400",
        "message" => "Failed to create account."
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

// Document Functions
function createNewDocument($pdo, $user_id, $title = 'Untitled Document') {
    $response = [];
    try {
        $sql = "INSERT INTO documents (user_id, title, content, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$user_id, $title, ''])) {
            $document_id = $pdo->lastInsertId();
            
            // Log the document creation
            $action_details = json_encode([
                'document_id' => $document_id,
                'title' => $title
            ]);
            logActivity($pdo, $user_id, 'create_document', $action_details);
            
            $response = [
                "status" => "200",
                "message" => "Document created successfully",
                "document_id" => $document_id,
                "document" => [
                    'title' => $title,
                    'content' => '',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to create document"
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

function getDocument($pdo, $document_id, $user_id) {
    $response = [];
    try {
        $sql = "SELECT d.*, u.first_name, u.last_name, u.email_address 
                FROM documents d 
                JOIN users u ON d.user_id = u.user_id 
                WHERE d.document_id = ? 
                AND (d.user_id = ? OR d.document_id IN (SELECT document_id FROM document_shares WHERE shared_with_user_id = ?))";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$document_id, $user_id, $user_id])) {
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($document) {
                $response = [
                    "status" => "200",
                    "message" => "Document retrieved successfully",
                    "document" => $document
                ];
            } else {
                $response = [
                    "status" => "404",
                    "message" => "Document not found or access denied"
                ];
            }
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to retrieve document"
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

function updateDocument($pdo, $document_id, $user_id, $data) {
    $response = [];
    try {
        // First check if user has access to the document
        $check_sql = "SELECT document_id FROM documents 
                     WHERE document_id = ? 
                     AND (user_id = ? OR document_id IN (SELECT document_id FROM document_shares WHERE shared_with_user_id = ?))";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$document_id, $user_id, $user_id]);
        
        if ($check_stmt->fetch()) {
            $sql = "UPDATE documents 
                   SET title = ?, 
                       content = ?, 
                       last_edited_at = NOW(), 
                       last_edited_by = ? 
                   WHERE document_id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$data['title'], $data['content'], $data['last_edited_by'], $document_id])) {
                // Log the document edit
                $action_details = json_encode([
                    'document_id' => $document_id,
                    'title' => $data['title']
                ]);
                logActivity($pdo, $user_id, 'edit_document', $action_details);
                
                $response = [
                    "status" => "200",
                    "message" => "Document updated successfully"
                ];
            } else {
                $response = [
                    "status" => "400",
                    "message" => "Failed to update document"
                ];
            }
        } else {
            $response = [
                "status" => "403",
                "message" => "Access denied to this document"
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

function shareDocument($pdo, $document_id, $shared_by_user_id, $shared_with_username) {
    $response = [];
    try {
        // First check if the document belongs to the sharing user
        $check_sql = "SELECT document_id FROM documents WHERE document_id = ? AND user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$document_id, $shared_by_user_id]);
        
        if ($check_stmt->fetch()) {
            // Get the user_id of the user to share with
            $user_sql = "SELECT user_id FROM users WHERE username = ?";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute([$shared_with_username]);
            $shared_with_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($shared_with_user) {
                $shared_with_user_id = $shared_with_user['user_id'];
                
                // Check if document is already shared with this user
                $check_share_sql = "SELECT share_id FROM document_shares 
                                  WHERE document_id = ? AND shared_with_user_id = ?";
                $check_share_stmt = $pdo->prepare($check_share_sql);
                $check_share_stmt->execute([$document_id, $shared_with_user_id]);
                
                if (!$check_share_stmt->fetch()) {
                    $share_sql = "INSERT INTO document_shares 
                                (document_id, shared_by_user_id, shared_with_user_id, shared_at) 
                                VALUES (?, ?, ?, NOW())";
                    $share_stmt = $pdo->prepare($share_sql);
                    
                    if ($share_stmt->execute([$document_id, $shared_by_user_id, $shared_with_user_id])) {
                        // Log the document share
                        $action_details = json_encode([
                            'document_id' => $document_id,
                            'shared_with_username' => $shared_with_username
                        ]);
                        logActivity($pdo, $shared_by_user_id, 'share_document', $action_details);
                        
                        $response = [
                            "status" => "200",
                            "message" => "Document shared successfully"
                        ];
                    } else {
                        $response = [
                            "status" => "400",
                            "message" => "Failed to share document"
                        ];
                    }
                } else {
                    $response = [
                        "status" => "400",
                        "message" => "Document already shared with this user"
                    ];
                }
            } else {
                $response = [
                    "status" => "404",
                    "message" => "User not found"
                ];
            }
        } else {
            $response = [
                "status" => "403",
                "message" => "You don't have permission to share this document"
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

function getUserDocuments($pdo, $user_id) {
    $response = [];
    try {
        $sql = "SELECT d.*, u.first_name, u.last_name 
                FROM documents d 
                JOIN users u ON d.user_id = u.user_id 
                WHERE d.user_id = ? 
                OR d.document_id IN (SELECT document_id FROM document_shares WHERE shared_with_user_id = ?)
                ORDER BY d.last_edited_at DESC";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$user_id, $user_id])) {
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

// Admin Functions
function getAllUsers($pdo) {
    $response = [];
    try {
        $sql = "SELECT user_id, username, email_address, first_name, last_name, is_admin, 
                CASE 
                    WHEN suspended_until IS NOT NULL AND suspended_until > NOW() THEN 'suspended'
                    ELSE 'active'
                END as status,
                suspended_until,
                created_at
                FROM users 
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute()) {
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = [
                "status" => "200",
                "message" => "Users retrieved successfully",
                "users" => $users
            ];
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to retrieve users"
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

function suspendUser($pdo, $user_id, $admin_id, $duration_days = 7, $reason = '') {
    $response = [];
    try {
        // Check if admin has permission
        $check_sql = "SELECT is_admin FROM users WHERE user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$admin_id]);
        $admin = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !$admin['is_admin']) {
            $response = [
                "status" => "403",
                "message" => "Permission denied. Admin access required."
            ];
            return $response;
        }

        // Calculate suspension end date
        $suspended_until = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));

        // Update user status
        $sql = "UPDATE users 
                SET suspended_until = ?, 
                    suspension_reason = ?,
                    suspended_by = ?,
                    suspended_at = NOW()
                WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$suspended_until, $reason, $admin_id, $user_id])) {
            // Log the suspension activity
            $action_details = json_encode([
                'suspended_user_id' => $user_id,
                'duration_days' => $duration_days,
                'reason' => $reason,
                'suspended_until' => $suspended_until
            ]);
            logActivity($pdo, $admin_id, 'suspend_user', $action_details);
            
            $response = [
                "status" => "200",
                "message" => "User suspended successfully until " . date('M d, Y', strtotime($suspended_until))
            ];
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to suspend user"
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

function unsuspendUser($pdo, $user_id, $admin_id) {
    $response = [];
    try {
        // Check if admin has permission
        $check_sql = "SELECT is_admin FROM users WHERE user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$admin_id]);
        $admin = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !$admin['is_admin']) {
            $response = [
                "status" => "403",
                "message" => "Permission denied. Admin access required."
            ];
            return $response;
        }

        // Remove suspension
        $sql = "UPDATE users 
                SET suspended_until = NULL, 
                    suspension_reason = NULL,
                    suspended_by = NULL,
                    suspended_at = NULL
                WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$user_id])) {
            // Log the unsuspension activity
            $action_details = json_encode([
                'unsuspended_user_id' => $user_id
            ]);
            logActivity($pdo, $admin_id, 'unsuspend_user', $action_details);
            
            $response = [
                "status" => "200",
                "message" => "User suspension removed successfully"
            ];
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to remove user suspension"
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

function checkUserSuspension($pdo, $user_id) {
    $response = [];
    try {
        $sql = "SELECT suspended_until, suspension_reason 
                FROM users 
                WHERE user_id = ? 
                AND suspended_until IS NOT NULL 
                AND suspended_until > NOW()";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$user_id])) {
            $suspension = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($suspension) {
                $response = [
                    "status" => "403",
                    "message" => "Account suspended until " . date('M d, Y', strtotime($suspension['suspended_until'])),
                    "reason" => $suspension['suspension_reason']
                ];
            } else {
                $response = [
                    "status" => "200",
                    "message" => "Account active"
                ];
            }
        } else {
            $response = [
                "status" => "400",
                "message" => "Failed to check user status"
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

// Activity Logging Functions
function logActivity($pdo, $user_id, $action_type, $action_details = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO activity_logs (user_id, action_type, action_details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$user_id, $action_type, $action_details, $ip_address, $user_agent])) {
            return [
                "status" => "200",
                "message" => "Activity logged successfully"
            ];
        } else {
            return [
                "status" => "400",
                "message" => "Failed to log activity"
            ];
        }
    } catch (PDOException $e) {
        return [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
}

function getActivityLogs($pdo, $filters = []) {
    try {
        $where_conditions = [];
        $params = [];
        
        // Build where conditions based on filters
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "al.user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['action_type'])) {
            $where_conditions[] = "al.action_type = ?";
            $params[] = $filters['action_type'];
        }
        if (!empty($filters['start_date'])) {
            $where_conditions[] = "al.created_at >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where_conditions[] = "al.created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $sql = "SELECT al.*, u.username, u.first_name, u.last_name, u.email_address 
                FROM activity_logs al 
                JOIN users u ON al.user_id = u.user_id 
                {$where_clause} 
                ORDER BY al.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $filters['limit'] ?? 50;
        $params[] = $filters['offset'] ?? 0;
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $count_sql = "SELECT COUNT(*) FROM activity_logs al {$where_clause}";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
            $total = $count_stmt->fetchColumn();
            
            return [
                "status" => "200",
                "message" => "Activity logs retrieved successfully",
                "logs" => $logs,
                "total" => $total
            ];
        } else {
            return [
                "status" => "400",
                "message" => "Failed to retrieve activity logs"
            ];
        }
    } catch (PDOException $e) {
        return [
            "status" => "400",
            "message" => "Database error: " . $e->getMessage()
        ];
    }
}

function deleteDocument($pdo, $document_id, $user_id) {
    $response = [];
    try {
        // First check if user has permission to delete the document
        $check_sql = "SELECT document_id FROM documents WHERE document_id = ? AND user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$document_id, $user_id]);
        
        if ($check_stmt->fetch()) {
            // Delete document shares first (due to foreign key constraint)
            $delete_shares_sql = "DELETE FROM document_shares WHERE document_id = ?";
            $delete_shares_stmt = $pdo->prepare($delete_shares_sql);
            $delete_shares_stmt->execute([$document_id]);
            
            // Delete the document
            $delete_sql = "DELETE FROM documents WHERE document_id = ?";
            $delete_stmt = $pdo->prepare($delete_sql);
            
            if ($delete_stmt->execute([$document_id])) {
                // Log the document deletion
                $action_details = json_encode([
                    'document_id' => $document_id
                ]);
                logActivity($pdo, $user_id, 'delete_document', $action_details);
                
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
        } else {
            $response = [
                "status" => "403",
                "message" => "You don't have permission to delete this document"
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

function renameDocument($pdo, $document_id, $user_id, $new_title) {
    $response = [];
    try {
        // First check if user has access to the document and get current title
        $check_sql = "SELECT document_id, title FROM documents 
                     WHERE document_id = ? 
                     AND (user_id = ? OR document_id IN (SELECT document_id FROM document_shares WHERE shared_with_user_id = ?))";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$document_id, $user_id, $user_id]);
        $document = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            $sql = "UPDATE documents 
                   SET title = ?, 
                       last_edited_at = NOW(), 
                       last_edited_by = (SELECT username FROM users WHERE user_id = ?)
                   WHERE document_id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$new_title, $user_id, $document_id])) {
                // Log the document rename
                $action_details = json_encode([
                    'document_id' => $document_id,
                    'old_title' => $document['title'],
                    'new_title' => $new_title
                ]);
                logActivity($pdo, $user_id, 'rename_document', $action_details);
                
                $response = [
                    "status" => "200",
                    "message" => "Document renamed successfully"
                ];
            } else {
                $response = [
                    "status" => "400",
                    "message" => "Failed to rename document"
                ];
            }
        } else {
            $response = [
                "status" => "403",
                "message" => "Access denied to this document"
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

/**
 * Initialize or get document for editing
 * @param PDO $pdo Database connection
 * @param int $user_id Current user's ID
 * @param int|null $document_id Document ID if editing existing document
 * @return array Response with document data or error
 */
function initializeDocument($pdo, $user_id, $document_id = null) {
    try {
        // Get user information
        $sql = "SELECT first_name, last_name, email_address FROM users WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'status' => "404",
                'message' => "User not found"
            ];
        }

        $document = null;
        
        if ($document_id) {
            $result = getDocument($pdo, $document_id, $user_id);
            if ($result['status'] === "200") {
                $document = $result['document'];
                // If document title is empty or "Untitled Document", allow editing
                if (empty($document['title']) || $document['title'] === "Untitled Document") {
                    $document['title'] = "Untitled Document";
                }
            } else {
                // Create new document if not found
                $result = createNewDocument($pdo, $user_id);
                if ($result['status'] === "200") {
                    return [
                        'status' => "200",
                        'message' => "New document created",
                        'document' => $result['document'],
                        'document_id' => $result['document_id'],
                        'user' => $user,
                        'redirect' => "document.php?id=" . $result['document_id']
                    ];
                }
                return $result;
            }
        } else {
            // Create new document
            $result = createNewDocument($pdo, $user_id);
            if ($result['status'] === "200") {
                return [
                    'status' => "200",
                    'message' => "New document created",
                    'document' => $result['document'],
                    'document_id' => $result['document_id'],
                    'user' => $user,
                    'redirect' => "document.php?id=" . $result['document_id']
                ];
            }
            return $result;
        }

        return [
            'status' => "200",
            'message' => "Document retrieved successfully",
            'document' => $document,
            'document_id' => $document_id,
            'user' => $user
        ];

    } catch (PDOException $e) {
        return [
            'status' => "500",
            'message' => "Database error: " . $e->getMessage()
        ];
    }
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