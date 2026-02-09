-- User Messages table for banned users to contact admins
CREATE TABLE IF NOT EXISTS user_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT NULL,
    status ENUM('pending', 'replied', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
);

-- Update reports table to add user notifications
ALTER TABLE reports 
ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL,
ADD COLUMN IF NOT EXISTS reply_read BOOLEAN DEFAULT FALSE;
