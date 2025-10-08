-- Create stage_photos table
CREATE TABLE IF NOT EXISTS stage_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stage_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    blurred_image_path VARCHAR(255) NOT NULL,
    photo_order INT NOT NULL,
    code_1 VARCHAR(6) NOT NULL,
    code_2 VARCHAR(6) NOT NULL,
    is_unlocked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE
);

-- Create user_voice_recordings table
CREATE TABLE IF NOT EXISTS user_voice_recordings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    stage_photo_id BIGINT UNSIGNED NOT NULL,
    voice_file_path VARCHAR(255) NOT NULL,
    duration_seconds INT NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_photo_id) REFERENCES stage_photos(id) ON DELETE CASCADE
);

-- Create user_stage_progress table
CREATE TABLE IF NOT EXISTS user_stage_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    stage_id BIGINT UNSIGNED NOT NULL,
    unlocked_photos_count INT DEFAULT 0,
    completed_voice_recordings INT DEFAULT 0,
    stage_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_user_stage (user_id, stage_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE
);
