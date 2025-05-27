<?php
require_once "core/dbconfig.php";
require_once "core/model.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] == 1) {
    $_SESSION['message'] = "Access denied you dont have user account";
    $_SESSION['status'] = "400";
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];

// Get user documents
$result = getUserDocuments($pdo, $user_id);
$documents = $result['status'] === "200" ? $result['documents'] : [];

// Calculate statistics
$my_documents_count = 0;
$shared_with_me_count = 0;
$recent_edits = 0;

foreach ($documents as $doc) {
    if ($doc['user_id'] == $user_id) {
        $my_documents_count++;
    } else {
        $shared_with_me_count++;
    }
    if (strtotime($doc['last_edited_at']) > strtotime('-24 hours')) {
        $recent_edits++;
    }
}

// Get user details for display
$sql = "SELECT first_name, last_name, email_address FROM users WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="#" class="flex items-center">
                        <i class="fas fa-file-word text-blue-600 text-2xl mr-2"></i>
                        <span class="text-xl font-semibold">Google Docs</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search documents..." 
                               class="px-4 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-center space-x-2">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>" 
                             alt="<?php echo htmlspecialchars($user['first_name']); ?>" 
                             class="w-8 h-8 rounded-full">
                        <span class="text-gray-700"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        <a href="core/handleforms.php?logoutUserBtn=1" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-file-alt text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Documents</h3>
                        <p class="text-2xl font-semibold"><?php echo $my_documents_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-share-alt text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Shared With Me</h3>
                        <p class="text-2xl font-semibold"><?php echo $shared_with_me_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Recent Edits (24h)</h3>
                        <p class="text-2xl font-semibold"><?php echo $recent_edits; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="documentGrid">
            <!-- New Document Card -->
            <div class="bg-white rounded-lg shadow p-6 border-2 border-dashed border-gray-300 hover:border-blue-500 cursor-pointer transition-colors duration-200">
                <div class="flex flex-col items-center justify-center h-40">
                    <i class="fas fa-plus text-4xl text-gray-400 mb-4"></i>
                    <a href="document.php" class="text-gray-600 hover:text-blue-600">Create New Document</a>
                </div>
            </div>

            <?php if (empty($documents)): ?>
            <!-- Empty State -->
            <div class="col-span-2 bg-white rounded-lg shadow p-6">
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-file-alt text-4xl mb-4"></i>
                    <p>No documents available</p>
                    <p class="text-sm mt-2">Create a new document to get started</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                <!-- Document Card -->
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900 document-title" 
                                        data-document-id="<?php echo $doc['document_id']; ?>"
                                        data-current-title="<?php echo htmlspecialchars($doc['title']); ?>">
                                        <?php echo htmlspecialchars($doc['title']); ?>
                                    </h3>
                                    <?php if ($doc['user_id'] == $user_id): ?>
                                    <button onclick="renameDocument(<?php echo $doc['document_id']; ?>)" 
                                            class="text-gray-400 hover:text-blue-600" title="Rename document">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteDocument(<?php echo $doc['document_id']; ?>)" 
                                            class="text-gray-400 hover:text-red-600" title="Delete document">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500 mb-4">
                                    <?php 
                                    $content = strip_tags($doc['content']);
                                    echo strlen($content) > 100 ? substr($content, 0, 100) . '...' : $content;
                                    ?>
                                </p>
                                <div class="flex items-center text-sm text-gray-500">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doc['first_name'] . ' ' . $doc['last_name']); ?>" 
                                         alt="<?php echo htmlspecialchars($doc['first_name']); ?>" 
                                         class="w-6 h-6 rounded-full mr-2">
                                    <span><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></span>
                                    <span class="mx-2">•</span>
                               <span><?php echo date('M d, Y, h:i A', strtotime($doc['last_edited_at'] ?? $doc['created_at'])); ?></span>
                                </div>
                            </div>
                            <?php if ($doc['user_id'] != $user_id): ?>
                            <span class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full">
                                Shared
                            </span>
                            <?php endif; ?>
                        </div>
                        <a href="document.php?id=<?php echo $doc['document_id']; ?>" class="block mt-4 text-blue-600 hover:text-blue-800">
                            Open Document
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="mt-8 bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold">Recent Activity</h2>
            </div>
            <div class="p-6">
                <?php if (empty($documents)): ?>
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-history text-4xl mb-4"></i>
                    <p>No recent activity</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php 
                    // Sort documents by last edited date
                    usort($documents, function($a, $b) {
                        return strtotime($b['last_edited_at'] ?? $b['created_at']) - strtotime($a['last_edited_at'] ?? $a['created_at']);
                    });
                    // Show only the 5 most recent documents
                    $recent_docs = array_slice($documents, 0, 5);
                    foreach ($recent_docs as $doc): 
                    ?>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doc['first_name'] . ' ' . $doc['last_name']); ?>" 
                                 alt="<?php echo htmlspecialchars($doc['first_name']); ?>" 
                                 class="w-8 h-8 rounded-full">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900">
                                <a href="document.php?id=<?php echo $doc['document_id']; ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </a>
                            </p>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?> 
                                <?php echo $doc['user_id'] == $user_id ? 'edited' : 'shared with you'; ?> 
                                <?php echo date('M d, Y H:i', strtotime($doc['last_edited_at'] ?? $doc['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const documentCards = document.querySelectorAll('#documentGrid > div:not(:first-child)');
            
            documentCards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const content = card.querySelector('p').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || content.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Delete document function
        function deleteDocument(documentId) {
            if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                fetch('core/handleforms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        'deleteDocumentBtn': true,
                        'document_id': documentId
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status === "200") {
                        // Remove the document card from the UI
                        const card = document.querySelector(`[data-document-id="${documentId}"]`).closest('.bg-white');
                        card.remove();
                        
                        // Update document count
                        const myDocsCount = document.querySelector('.text-2xl.font-semibold');
                        myDocsCount.textContent = parseInt(myDocsCount.textContent) - 1;
                        
                        // Show success message
                        alert(result.message);
                    } else {
                        alert(result.message || 'Error deleting document');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting document');
                });
            }
        }

        // Rename document function
        function renameDocument(documentId) {
            const titleElement = document.querySelector(`[data-document-id="${documentId}"]`);
            const currentTitle = titleElement.dataset.currentTitle;
            const newTitle = prompt('Enter new document title:', currentTitle);
            
            if (newTitle && newTitle !== currentTitle) {
                fetch('core/handleforms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        'renameDocumentBtn': true,
                        'document_id': documentId,
                        'new_title': newTitle
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status === "200") {
                        // Update the title in the UI
                        titleElement.textContent = newTitle;
                        titleElement.dataset.currentTitle = newTitle;
                        
                        // Show success message
                        alert(result.message);
                    } else {
                        alert(result.message || 'Error renaming document');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error renaming document');
                });
            }
        }
    </script>
</body>
</html>