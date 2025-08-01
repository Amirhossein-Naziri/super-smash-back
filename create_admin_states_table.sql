-- Create admin_states table for persistent admin state storage
CREATE TABLE IF NOT EXISTS `admin_states` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `chat_id` bigint(20) NOT NULL,
    `state_data` json NOT NULL,
    `expires_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `admin_states_chat_id_index` (`chat_id`),
    KEY `admin_states_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 