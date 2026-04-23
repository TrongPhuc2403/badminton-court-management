ALTER TABLE users
    CHANGE COLUMN otp_code verification_token VARCHAR(64) NULL,
    CHANGE COLUMN otp_expires_at verification_expires_at DATETIME NULL,
    CHANGE COLUMN otp_sent_at verification_sent_at DATETIME NULL;
