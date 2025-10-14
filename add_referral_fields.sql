-- Add referral fields to users table
-- Run this SQL directly on your database

-- Add referral fields to users table
ALTER TABLE users 
ADD COLUMN referral_code VARCHAR(8) UNIQUE NULL,
ADD COLUMN referred_by BIGINT UNSIGNED NULL,
ADD COLUMN referral_count INT DEFAULT 0,
ADD COLUMN referral_rewards INT DEFAULT 0;

-- Add foreign key constraint
ALTER TABLE users 
ADD CONSTRAINT users_referred_by_foreign 
FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create referrals table
CREATE TABLE referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id BIGINT UNSIGNED NOT NULL,
    referred_id BIGINT UNSIGNED NOT NULL,
    reward_amount INT DEFAULT 0,
    referral_order INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY referrals_referrer_referred_unique (referrer_id, referred_id)
);

-- Add indexes for better performance
CREATE INDEX idx_users_referral_code ON users(referral_code);
CREATE INDEX idx_users_referred_by ON users(referred_by);
CREATE INDEX idx_referrals_referrer_id ON referrals(referrer_id);
CREATE INDEX idx_referrals_referred_id ON referrals(referred_id);
CREATE INDEX idx_referrals_is_active ON referrals(is_active);
