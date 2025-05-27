
<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get document ID
$doc_id = isset($_POST['doc_id']) ? $_POST['doc_id'] : null;

if (!$doc_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing document ID']);
    exit();
}

// Check if user is the document owner
$stmt = $conn->prepare("
    SELECT owner_id 
    FROM documents 
    WHERE doc_id = ?
");
$stmt->execute([$doc_id]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit();
}

if ($document['owner_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only document owner can delete']);
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Delete document versions
    $stmt = $conn->prepare("DELETE FROM document_versions WHERE doc_id = ?");
    $stmt->execute([$doc_id]);
    
    // Delete activity logs
    $stmt = $conn->prepare("DELETE FROM activity_logs WHERE doc_id = ?");
    $stmt->execute([$doc_id]);
    
    // Delete messages
    $stmt = $conn->prepare("DELETE FROM messages WHERE doc_id = ?");
    $stmt->execute([$doc_id]);
    
    // Delete permissions
    $stmt = $conn->prepare("DELETE FROM document_permissions WHERE doc_id = ?");
    $stmt->execute([$doc_id]);
    
    // Delete document
    $stmt = $conn->prepare("DELETE FROM documents WHERE doc_id = ?");
    $stmt->execute([$doc_id]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error deleting document']);
}
?> 
