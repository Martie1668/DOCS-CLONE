<?php
require_once "core/dbconfig.php";
require_once "core/model.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize document
$document_id = isset($_GET['id']) ? $_GET['id'] : null;
$result = initializeDocument($pdo, $_SESSION['user_id'], $document_id);

// Handle redirects if needed
if (isset($result['redirect'])) {
    header("Location: " . $result['redirect']);
    exit();
}

// Handle errors
if ($result['status'] !== "200") {
    $_SESSION['message'] = $result['message'];
    $_SESSION['status'] = $result['status'];
    header("Location: index.php");
    exit();
}

// Extract data from result
$document = $result['document'];
$document_id = $result['document_id'];
$user = $result['user'];
$editor_content = $document['content'] ?? '';

// Get document comments
$comments_result = getDocumentComments($pdo, $document_id);
$comments = $comments_result['status'] === "200" ? $comments_result['comments'] : [];

?>
<!DOCTYPE html>
<html lang="en" class="bg-[#F5F7FA] text-gray-800 font-sans">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($document['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    colors: {
                        primary: '#2563EB',
                        soft: '#E0F2FE',
                        light: '#F9FAFB',
                    }
                }
            }
        }
        function formatText(command, value = null) {
    document.execCommand(command, false, value);
}

function insertImage() {
    const url = prompt("Enter image URL:");
    if (url) formatText('insertImage', url);
}

function insertLink() {
    const url = prompt("Enter link URL:");
    if (url) formatText('createLink', url);
}

function insertTable() {
    const rows = prompt("Number of rows:");
    const cols = prompt("Number of columns:");
    let table = "<table border='1'>";
    for (let i = 0; i < rows; i++) {
        table += "<tr>";
        for (let j = 0; j < cols; j++) {
            table += "<td>&nbsp;</td>";
        }
        table += "</tr>";
    }
    table += "</table>";
    document.getElementById("editor").focus();
    document.execCommand('insertHTML', false, table);
}

function toggleComments() {
    const commentSection = document.getElementById('commentSection');
    commentSection.classList.toggle('hidden');
}

    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-light">

    <!-- Navigation Bar -->
    <nav class="bg-primary text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-file-word text-white text-2xl"></i>
                    <input type="text" id="documentTitle" value="<?php echo htmlspecialchars($document['title']); ?>"
                        class="bg-transparent text-xl font-semibold text-white focus:outline-none border-b border-white px-2"
                        placeholder="Untitled Document" onclick="this.select()">
                </div>
                <div class="flex items-center space-x-4">
                    <button class="hover:text-gray-300" title="Share">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    <button onclick="toggleComments()" class="hover:text-gray-300 flex items-center">
                        <i class="fas fa-comment-alt mr-2"></i> Comments
                    </button>
                    <a href="index.php" class="hover:text-red-300">
                        <i class="fas fa-sign-out-alt mr-2"></i> Exit
                    </a>
                    <div class="relative group">
                        <button class="flex items-center space-x-2">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>" class="w-8 h-8 rounded-full">
                            <span><?php echo htmlspecialchars($user['first_name']); ?></span>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white text-gray-800 shadow-lg rounded-lg py-2 hidden group-hover:block">
                            <div class="px-4 py-2 border-b">
                                <p class="font-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email_address']); ?></p>
                            </div>
                            <div class="px-4 py-2 text-sm">
                                Created: <?php echo date('M d, Y', strtotime($document['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toolbar -->
    <div class="bg-white sticky top-0 shadow z-10 border-b">
        <div class="max-w-7xl mx-auto px-4 py-2 flex space-x-4 overflow-x-auto">
            <select class="bg-gray-100 px-2 py-1 rounded text-sm" onchange="formatText('formatBlock', this.value)">
                <option value="p">Normal</option>
                <option value="h1">H1</option>
                <option value="h2">H2</option>
                <option value="h3">H3</option>
            </select>

            <select class="bg-gray-100 px-2 py-1 rounded text-sm" onchange="formatText('fontName', this.value)">
                <option>Arial</option>
                <option>Georgia</option>
                <option>Courier New</option>
            </select>

            <select class="bg-gray-100 px-2 py-1 rounded text-sm" onchange="formatText('fontSize', this.value)">
                <option value="3">10pt</option>
                <option value="4">11pt</option>
                <option value="5">12pt</option>
                <option value="6">14pt</option>
                <option value="7">16pt</option>
            </select>

            <button onclick="formatText('bold')" class="hover:bg-gray-200 p-2 rounded"><i class="fas fa-bold"></i></button>
            <button onclick="formatText('italic')" class="hover:bg-gray-200 p-2 rounded"><i class="fas fa-italic"></i></button>
            <button onclick="formatText('underline')" class="hover:bg-gray-200 p-2 rounded"><i class="fas fa-underline"></i></button>

            <input type="color" onchange="formatText('foreColor', this.value)" class="h-6 w-6 border-0">
            <input type="color" onchange="formatText('hiliteColor', this.value)" class="h-6 w-6 border-0">

            <button onclick="insertImage()" class="hover:bg-gray-200 p-2 rounded"><i class="fas fa-image"></i></button>
            <button onclick="insertLink()" class="hover:bg-gray-200 p-2 rounded"><i class="fas fa-link"></i></button>
            <button onclick="insertTable()" class="hover:bg-gray-200 p-2 rounded"><i class="fas fa-table"></i></button>
        </div>
    </div>

    <!-- Editor Area -->
    <main class="max-w-5xl mx-auto p-6 mt-6 bg-white rounded-lg shadow-lg">
        <div id="editor" contenteditable="true" class="min-h-[600px] focus:outline-none prose max-w-none">
            <?php echo $editor_content; ?>
        </div>
    </main>

    <!-- Save Status -->
    <div class="fixed bottom-4 right-4 bg-white border px-4 py-2 rounded shadow text-gray-500 text-sm">
        <i class="fas fa-circle text-green-500 mr-2 text-xs"></i> <span id="saveStatus">All changes saved</span>
    </div>

    <!-- Comments Panel -->
    <aside id="commentSection" class="hidden fixed top-0 right-0 w-96 h-full bg-white border-l shadow-lg z-50">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="font-semibold text-lg">Comments</h2>
            <button onclick="toggleComments()" class="text-gray-400 hover:text-red-400"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 space-y-4 overflow-y-auto">
            <form id="commentForm" class="bg-gray-100 p-3 rounded-lg">
                <textarea id="commentText" rows="3" class="w-full p-2 border rounded" placeholder="Write a comment..."></textarea>
                <button type="submit" class="mt-2 bg-primary text-white px-4 py-1 rounded hover:bg-blue-700">Post</button>
            </form>
            <div id="commentsList" class="space-y-4"></div>
        </div>
    </aside>

</body>
</html>

    <script>
        // Document state
        let autoSaveTimeout;
        let lastSavedContent = '';
        const editor = document.getElementById('editor');
        const saveIndicator = document.getElementById('saveIndicator');
        const documentTitle = document.getElementById('documentTitle');
        const saveStatus = document.getElementById('saveStatus');

        // Format text function
        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            editor.focus();
        }

        // Insert image
        function insertImage() {
            const url = prompt('Enter image URL:');
            if (url) {
                document.execCommand('insertImage', false, url);
            }
        }

        // Insert link
        function insertLink() {
            const url = prompt('Enter link URL:');
            if (url) {
                document.execCommand('createLink', false, url);
            }
        }

        // Insert table
        function insertTable() {
            const rows = prompt('Enter number of rows:', '3');
            const cols = prompt('Enter number of columns:', '3');
            
            if (rows && cols) {
                let table = '<table border="1" style="border-collapse: collapse; width: 100%;">';
                for (let i = 0; i < rows; i++) {
                    table += '<tr>';
                    for (let j = 0; j < cols; j++) {
                        table += '<td style="padding: 8px; border: 1px solid #ddd;">Cell</td>';
                    }
                    table += '</tr>';
                }
                table += '</table>';
                document.execCommand('insertHTML', false, table);
            }
        }

        // Auto-save functionality
        function autoSave() {
            const content = editor.innerHTML;
            if (content !== lastSavedContent) {
                saveIndicator.innerHTML = `
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-circle text-yellow-500 text-xs animate-pulse"></i>
                        <span>Saving...</span>
                    </div>
                `;
                saveStatus.textContent = 'Saving...';

                const saveData = {
                    document_id: <?php echo $document_id; ?>,
                    title: documentTitle.value,
                    content: content,
                    last_edited_by: "<?php echo addslashes($user['first_name'] . ' ' . $user['last_name']); ?>"
                };

                // Make AJAX call to save the document
                fetch('core/handleforms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        'updateDocumentBtn': true,
                        ...saveData
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status === "200") {
                        lastSavedContent = content;
                        saveIndicator.innerHTML = `
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-circle text-green-500 text-xs"></i>
                                <span>All changes saved</span>
                            </div>
                        `;
                        saveStatus.textContent = 'All changes saved';
                    } else {
                        saveIndicator.innerHTML = `
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-circle text-red-500 text-xs"></i>
                                <span>Error saving</span>
                            </div>
                        `;
                        saveStatus.textContent = 'Error saving';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    saveIndicator.innerHTML = `
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-circle text-red-500 text-xs"></i>
                            <span>Error saving</span>
                        </div>
                    `;
                    saveStatus.textContent = 'Error saving';
                });
            }
        }

        // Event listeners
        editor.addEventListener('input', () => {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(autoSave, 1000);
        });

        documentTitle.addEventListener('input', () => {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(autoSave, 1000);
        });

        // Share button functionality
        document.getElementById('shareBtn').addEventListener('click', () => {
            const username = prompt('Enter username to share with:');
            if (username) {
                fetch('core/handleforms.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        'shareDocumentBtn': true,
                        'document_id': <?php echo $document_id; ?>,
                        'username': username
                    })
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error sharing document');
                });
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'b':
                        e.preventDefault();
                        formatText('bold');
                        break;
                    case 'i':
                        e.preventDefault();
                        formatText('italic');
                        break;
                    case 'u':
                        e.preventDefault();
                        formatText('underline');
                        break;
                    case 's':
                        e.preventDefault();
                        if (!e.shiftKey) {
                            formatText('strikeThrough');
                        }
                        break;
                }
            }
        });

        // Make the title input more user-friendly
        documentTitle.addEventListener('focus', function() {
            if (this.value === "Untitled Document") {
                this.select();
            }
        });

        documentTitle.addEventListener('blur', function() {
            if (this.value.trim() === "") {
                this.value = "Untitled Document";
                autoSave(); // Save the default title
            }
        });

        // Initialize editor
        editor.focus();

        function toggleComments() {
            const commentSection = document.getElementById('commentSection');
            const isHidden = commentSection.classList.contains('hidden');
            
            if (isHidden) {
                // Show comments
                commentSection.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
                loadComments();
            } else {
                // Hide comments
                commentSection.classList.add('hidden');
                document.body.style.overflow = ''; // Restore background scrolling
            }
        }

        function loadComments() {
            const commentsList = document.getElementById('commentsList');
            const commentCount = document.getElementById('commentCount');
            commentsList.innerHTML = '<div class="flex justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>';

            fetch(`core/handleforms.php?getCommentsBtn=1&document_id=<?php echo $document_id; ?>`, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "200" && data.comments) {
                    commentCount.textContent = data.comments.length;
                    
                    if (data.comments.length === 0) {
                        commentsList.innerHTML = `
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">No comments yet</p>
                                <p class="mt-1 text-xs text-gray-400">Be the first to comment!</p>
                            </div>`;
                        return;
                    }
                    
                    commentsList.innerHTML = data.comments.map(comment => `
                        <div class="bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300 transition-colors duration-200">
                            <div class="flex justify-between items-start">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                            <span class="text-sm font-medium text-blue-600">
                                                ${comment.first_name ? comment.first_name[0] + comment.last_name[0] : comment.username[0]}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                ${comment.first_name ? comment.first_name + ' ' + comment.last_name : comment.username}
                                            </p>
                                            ${comment.is_admin ? 
                                                `<span class="px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">Admin</span>` 
                                                : ''}
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            ${new Date(comment.created_at).toLocaleString()}
                                        </p>
                                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">${comment.comment_text}</p>
                                    </div>
                                </div>
                                ${comment.user_id == <?php echo $_SESSION['user_id']; ?> || <?php echo $_SESSION['is_admin'] ? 'true' : 'false'; ?> ? 
                                    `<button onclick="deleteComment(${comment.comment_id})" 
                                             class="flex-shrink-0 text-gray-400 hover:text-red-500 focus:outline-none"
                                             title="Delete comment">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>` 
                                    : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    commentsList.innerHTML = `
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-red-500">Failed to load comments</p>
                        </div>`;
                }
            })
            .catch(error => {
                console.error('Error loading comments:', error);
                commentsList.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-red-500">Error loading comments</p>
                    </div>`;
            });
        }

        document.getElementById('commentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const commentText = document.getElementById('commentText').value.trim();
            
            if (!commentText) {
                alert('Please enter a comment');
                return;
            }

            // Add CSRF token and session check
            const formData = new FormData();
            formData.append('addCommentBtn', '1');
            formData.append('document_id', '<?php echo $document_id; ?>');
            formData.append('comment_text', commentText);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');

            // Debug session status
            console.log('Session status:', {
                userId: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>,
                username: '<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : ''; ?>',
                isAdmin: <?php echo isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : 'false'; ?>
            });

            fetch('core/handleforms.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.status === "200") {
                    document.getElementById('commentText').value = '';
                    loadComments();
                } else if (data.status === "401") {
                    console.log('Session expired or invalid');
                    // Store current URL for redirect after login
                    sessionStorage.setItem('redirectAfterLogin', window.location.href);
                    window.location.href = 'login.php';
                } else {
                    alert(data.message || 'Failed to add comment');
                }
            })
            .catch(error => {
                console.error('Error adding comment:', error);
                alert('Error adding comment. Please try again.');
            });
        });

        function deleteComment(commentId) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }

            const formData = new FormData();
            formData.append('deleteCommentBtn', '1');
            formData.append('comment_id', commentId);

            fetch('../core/handleforms.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "200") {
                    loadComments();
                } else {
                    alert(data.message || 'Failed to delete comment');
                }
            })
            .catch(error => {
                console.error('Error deleting comment:', error);
                alert('Error deleting comment');
            });
        }
    </script>
</body>
</html>