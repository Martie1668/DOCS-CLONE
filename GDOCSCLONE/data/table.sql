create table users     (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR (255),
    email_address VARCHAR (255),
    first_name VARCHAR (255),
    last_name VARCHAR (255),
    password TEXT,
    is_admin BOOL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);




CREATE TABLE documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    created_at DATETIME NOT NULL,
    last_edited_at DATETIME,
    last_edited_by VARCHAR(255),

);

CREATE TABLE document_shares (
    share_id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    shared_by_user_id INT NOT NULL,
    shared_with_user_id INT NOT NULL,
    shared_at DATETIME NOT NULL,

);


CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type ENUM('login', 'logout', 'create_document', 'edit_document', 'share_document', 
                    'suspend_user', 'unsuspend_user', 'delete_document', 'update_profile') NOT NULL,
    action_details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 
);

CREATE TABLE document_comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)