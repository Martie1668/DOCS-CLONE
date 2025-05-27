
<?php
require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/models.php';

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
        // Verify this is an admin account
        if (!$user['is_admin']) {
          $_SESSION['message'] = "Access denied. Admin account required.";
          $_SESSION['status'] = '403';
          header("Location: ../login.php");
          exit();
        }
        
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
  session_unset();
  session_destroy();
  header("Location: ../login.php");
  exit();
}

// Admin Document Management Handlers
if (isset($_POST['adminDeleteDocumentBtn'])) {
    error_log("Admin delete document request received"); // Debug log
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        error_log("Admin check failed: " . print_r($_SESSION, true)); // Debug log
        $response = [
            "status" => "403",
            "message" => "Permission denied. Admin access required."
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    if (!isset($_POST['document_id'])) {
        $response = [
            "status" => "400",
            "message" => "Document ID is required"
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $result = adminDeleteDocument($pdo, $_POST['document_id']);
    error_log("Delete document result: " . print_r($result, true)); // Debug log
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// Simple Suspend User Handler
if (isset($_POST['suspendUserBtn'])) {
    header('Content-Type: application/json');
    
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        echo json_encode([
            "status" => "403",
            "message" => "Admin access required"
        ]);
        exit();
    }
    
    // Check if user_id is provided
    if (!isset($_POST['user_id'])) {
        echo json_encode([
            "status" => "400",
            "message" => "User ID is required"
        ]);
        exit();
    }
    
    // Suspend the user
    $result = suspendUser($pdo, $_POST['user_id'], $_SESSION['user_id']);
    echo json_encode($result);
    exit();
}

// Simple Unsuspend User Handler
if (isset($_POST['unsuspendUserBtn'])) {
    header('Content-Type: application/json');
    
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        echo json_encode([
            "status" => "403",
            "message" => "Admin access required"
        ]);
        exit();
    }
    
    // Check if user_id is provided
    if (!isset($_POST['user_id'])) {
        echo json_encode([
            "status" => "400",
            "message" => "User ID is required"
        ]);
        exit();
    }
    
    // Unsuspend the user
    $result = unsuspendUser($pdo, $_POST['user_id'], $_SESSION['user_id']);
    echo json_encode($result);
    exit();
}

// Check user suspension status (AJAX endpoint)
if (isset($_GET['checkUserSuspension']) && isset($_SESSION['user_id'])) {
    $result = checkUserSuspension($pdo, $_SESSION['user_id']);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}
