-- Table pour stocker les liaisons OAuth2
CREATE TABLE IF NOT EXISTS user_oauth (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(10) UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider_user (provider, provider_user_id),
    KEY idx_user_id (user_id),
    INDEX (user_id)
) ENGINE=Aria DEFAULT CHARSET=utf8mb3 PAGE_CHECKSUM=1;
