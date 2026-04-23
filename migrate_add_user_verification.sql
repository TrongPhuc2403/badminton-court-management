ALTER TABLE users
    MODIFY phone VARCHAR(20) NULL,
    ADD COLUMN email VARCHAR(120) NULL UNIQUE AFTER phone,
    ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
    ADD COLUMN verification_token VARCHAR(64) NULL AFTER is_verified,
    ADD COLUMN verification_expires_at DATETIME NULL AFTER verification_token,
    ADD COLUMN verification_sent_at DATETIME NULL AFTER verification_expires_at;

UPDATE users
SET is_verified = 1
WHERE role = 'admin' OR id IS NOT NULL;
