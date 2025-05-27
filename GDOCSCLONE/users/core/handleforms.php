
<?php
require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/model.php';

if (isset($_POST['insertNewUserBtn'])) {
  $username = trim($_POST['username']);
  $email_address = trim($_POST['email_address']);
  $first_name = trim($_POST['first_name']);
  $last_name = trim($_POST['last_name']);
  $password = trim($_POST['password']);
  $confirmPassword = trim($_POST['confirmPassword']);

  if (!empty($username) && !empty($email_address) && !empty($first_name) && !empty($last_name) && !empty($password) && !empty($confirmPassword)) {
    if ($password === $confirmPassword) {
      $result = insertNewUser($pdo, $username, $email_address, $first_name, $last_name, password_hash($password, PASSWORD_DEFAULT));
      $_SESSION['message'] = $result['message'];
      $_SESSION['status'] = $result['status'];
      header("Location: ../login.php");
    } else {
      $_SESSION['message'] = "Passwords do not match.";
      $_SESSION['status'] = '400';
      header("Location: ../register.php");
    }
  } else {
    $_SESSION['message'] = "All fields are required.";
    $_SESSION['status'] = '400';
    header("Location: ../register.php");
  }
  exit();
}

if (isset($_POST['loginUserBtn'])) {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  if (!empty($username) && !empty($password)) {
    $loginQuery = checkIfUserExists($pdo, $username);

    if ($loginQuery['result']) {
      $user = $loginQuery['userInfoArray'];
      
      // Check if user is suspended
      if ($user['is_suspended']) {
        $_SESSION['message'] = "Your account has been suspended. Please contact an administrator.";
        $_SESSION['status'] = '403';
        header("Location: ../login.php");
        exit();
      }
      
      if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];

        // Log successful login
        logActivity($pdo, $user['user_id'], 'login', json_encode([
          'username' => $user['username'],
          'is_admin' => $user['is_admin']
        ]));

        header("Location: ../index.php");
        exit();
      } else {
        $_SESSION['message'] = "Invalid password.";
        $_SESSION['status'] = '400';
        header("Location: ../login.php");
      }
    } else {
      $_SESSION['message'] = "User not found.";
      $_SESSION['status'] = '400';
      header("Location: ../login.php");
    }
  } else {
    $_SESSION['message'] = "All fields are required.";
    $_SESSION['status'] = '400';
    header("Location: ../login.php");
  }
  exit();
}

if (isset($_GET['logoutUserBtn'])) {
  if (isset($_SESSION['user_id'])) {
    // Log logout before destroying session
    logActivity($pdo, $_SESSION['user_id'], 'logout', json_encode([
      'username' => $_SESSION['username'],
      'is_admin' => $_SESSION['is_admin']
    ]));
  }
  session_unset();
  session_destroy();
  header("Location: ../login.php");
  exit();
}

// Document Handlers
if (isset($_POST['createDocumentBtn'])) {
    if (isset($_SESSION['user_id'])) {
        $title = isset($_POST['title']) ? trim($_POST['title']) : 'Untitled Document';
        $result = createNewDocument($pdo, $_SESSION['user_id'], $title);
        
        $_SESSION['message'] = $result['message'];
        $_SESSION['status'] = $result['status'];
        
        if ($result['status'] === "200") {
            header("Location: ../document.php?id=" . $result['document_id']);
        } else {
            header("Location: ../index.php");
        }
        exit();
    }
}

if (isset($_POST['updateDocumentBtn'])) {
    if (isset($_SESSION['user_id']) && isset($_POST['document_id'])) {
        $data = [
            'title' => trim($_POST['title']),
            'content' => trim($_POST['content']),
            'last_edited_by' => $_SESSION['username']
        ];
        
        $result = updateDocument($pdo, $_POST['document_id'], $_SESSION['user_id'], $data);
        
        // For AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit();
        }
        
        // For regular form submissions
        $_SESSION['message'] = $result['message'];
        $_SESSION['status'] = $result['status'];
        header("Location: ../document.php?id=" . $_POST['document_id']);
        exit();
    }
}

if (isset($_POST['shareDocumentBtn'])) {
    if (isset($_SESSION['user_id']) && isset($_POST['document_id']) && isset($_POST['username'])) {
        $result = shareDocument($pdo, $_POST['document_id'], $_SESSION['user_id'], trim($_POST['username']));
        
        // For AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit();
        }
        
        // For regular form submissions
        $_SESSION['message'] = $result['message'];
        $_SESSION['status'] = $result['status'];
        header("Location: ../document.php?id=" . $_POST['document_id']);
        exit();
    }
}

// AJAX endpoint for getting document list
if (isset($_GET['getUserDocuments']) && isset($_SESSION['user_id'])) {
    $result = getUserDocuments($pdo, $_SESSION['user_id']);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// AJAX endpoint for getting a single document
if (isset($_GET['getDocument']) && isset($_SESSION['user_id']) && isset($_GET['document_id'])) {
    $result = getDocument($pdo, $_GET['document_id'], $_SESSION['user_id']);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// Admin User Management Handlers
if (isset($_POST['suspendUserBtn']) && isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $user_id = $_POST['user_id'];
    $duration_days = isset($_POST['duration_days']) ? (int)$_POST['duration_days'] : 7;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    $result = suspendUser($pdo, $user_id, $_SESSION['user_id'], $duration_days, $reason);
    
    // For AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
    
    // For regular form submissions
    $_SESSION['message'] = $result['message'];
    $_SESSION['status'] = $result['status'];
    header("Location: ../admin/users.php");
    exit();
}

if (isset($_POST['unsuspendUserBtn']) && isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $user_id = $_POST['user_id'];
    $result = unsuspendUser($pdo, $user_id, $_SESSION['user_id']);
    
    // For AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
    
    // For regular form submissions
    $_SESSION['message'] = $result['message'];
    $_SESSION['status'] = $result['status'];
    header("Location: ../admin/users.php");
    exit();
}

// AJAX endpoint for getting all users (admin only)
if (isset($_GET['getAllUsers']) && isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $result = getAllUsers($pdo);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// Document deletion handler
if (isset($_POST['deleteDocumentBtn'])) {
    if (isset($_SESSION['user_id']) && isset($_POST['document_id'])) {
        $result = deleteDocument($pdo, $_POST['document_id'], $_SESSION['user_id']);
        
        // For AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit();
        }
        
        // For regular form submissions
        $_SESSION['message'] = $result['message'];
        $_SESSION['status'] = $result['status'];
        header("Location: ../index.php");
        exit();
    }
}

// Document rename handler
if (isset($_POST['renameDocumentBtn'])) {
    if (isset($_SESSION['user_id']) && isset($_POST['document_id']) && isset($_POST['new_title'])) {
        $result = renameDocument($pdo, $_POST['document_id'], $_SESSION['user_id'], trim($_POST['new_title']));
        
        // For AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit();
        }
        
        // For regular form submissions
        $_SESSION['message'] = $result['message'];
        $_SESSION['status'] = $result['status'];
        header("Location: ../document.php?id=" . $_POST['document_id']);
        exit();
    }
}

// Handle comment submission
if (isset($_POST['addCommentBtn'])) {
    // Debug session information
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("POST data: " . print_r($_POST, true));

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        error_log("User not logged in. Session data: " . print_r($_SESSION, true));
        echo json_encode([
            "status" => "401",
            "message" => "You must be logged in to comment",
            "debug" => [
                "session_exists" => isset($_SESSION),
                "user_id_exists" => isset($_SESSION['user_id']),
                "session_id" => session_id()
            ]
        ]);
        exit();
    }

    // Validate required fields
    if (!isset($_POST['document_id']) || !isset($_POST['comment_text']) || empty($_POST['comment_text'])) {
        echo json_encode([
            "status" => "400",
            "message" => "Comment text is required"
        ]);
        exit();
    }

    $document_id = intval($_POST['document_id']);
    $comment_text = trim($_POST['comment_text']);
    $user_id = $_SESSION['user_id'];

    // Add the comment
    $result = addDocumentComment($pdo, $document_id, $user_id, $comment_text);
    echo json_encode($result);
    exit();
}

// Handle getting comments
if (isset($_GET['getCommentsBtn'])) {
    // Debug session information
    error_log("Getting comments - Session data: " . print_r($_SESSION, true));
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        error_log("User not logged in while getting comments. Session data: " . print_r($_SESSION, true));
        echo json_encode([
            "status" => "401",
            "message" => "You must be logged in to view comments",
            "debug" => [
                "session_exists" => isset($_SESSION),
                "user_id_exists" => isset($_SESSION['user_id']),
                "session_id" => session_id()
            ]
        ]);
        exit();
    }

    if (!isset($_GET['document_id'])) {
        echo json_encode([
            "status" => "400",
            "message" => "Document ID is required"
        ]);
        exit();
    }

    $document_id = intval($_GET['document_id']);
    $result = getDocumentComments($pdo, $document_id);
    echo json_encode($result);
    exit();
}

// Get user details from database
$sql = "SELECT first_name, last_name, email_address FROM users WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get document information if document_id is provided
$document_id = isset($_GET['id']) ? $_GET['id'] : null;
$document = null;

if ($document_id) {
    $result = getDocument($pdo, $document_id, $user_id);
    if ($result['status'] === "200") {
        $document = $result['document'];
    } else {
        $_SESSION['message'] = $result['message'];
        $_SESSION['status'] = $result['status'];
        header("Location: index.php");
        exit();
    }
} else {
    // Create new document
    $result = createNewDocument($pdo, $user_id);
    if ($result['status'] === "200") {
        $document_id = $result['document_id'];
        $document = $result['document'];
        header("Location: document.php?id=" . $document_id);
        exit();
    } else {
        $_SESSION['message'] = $result['message'];
        $_SESSION['status'] = $result['status'];
        header("Location: index.php");
        exit();
    }
}
