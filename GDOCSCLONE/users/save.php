<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['doc_id']) || !isset($data['content']) || !isset($data['title'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$doc_id = $data['doc_id'];
$content = $data['content'];
$title = $data['title'];

// Check if user has permission to edit
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

$can_edit = $document['owner_id'] == $_SESSION['user_id'];

if (!$can_edit) {
    $stmt = $conn->prepare("SELECT can_edit FROM document_permissions WHERE doc_id = ? AND user_id = ?");
    $stmt->execute([$doc_id, $_SESSION['user_id']]);
    $permission = $stmt->fetch();
    $can_edit = $permission && $permission['can_edit'];
}

if (!$can_edit) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No permission to edit']);
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Save document version
    $stmt = $conn->prepare("
        INSERT INTO document_versions (doc_id, content, user_id) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$doc_id, $content, $_SESSION['user_id']]);
    
    // Update document
    $stmt = $conn->prepare("
        UPDATE documents 
        SET content = ?, title = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE doc_id = ?
    ");
    $stmt->execute([$content, $title, $doc_id]);
    
    // Log the change
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (doc_id, user_id, action, details) 
        VALUES (?, ?, 'edited', 'Document content updated')
    ");
    $stmt->execute([$doc_id, $_SESSION['user_id']]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error saving document']);
}
?> 