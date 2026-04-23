ALTER TABLE bookings
    ADD COLUMN payment_method ENUM('cash', 'bank_transfer') NOT NULL DEFAULT 'cash' AFTER total_price,
    ADD COLUMN payment_reference VARCHAR(50) NULL AFTER payment_method;

UPDATE bookings
SET payment_method = 'cash',
    payment_reference = CONCAT('BOOKING-', LPAD(id, 4, '0'))
WHERE payment_reference IS NULL;
