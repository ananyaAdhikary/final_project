-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bus Routes Table
CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(100) NOT NULL,
    from_city VARCHAR(50) NOT NULL,
    to_city VARCHAR(50) NOT NULL,
    distance INT NOT NULL,
    duration VARCHAR(20) NOT NULL,
    fare DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Buses Table
CREATE TABLE IF NOT EXISTS buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number VARCHAR(20) UNIQUE NOT NULL,
    bus_name VARCHAR(100) NOT NULL,
    route_id INT,
    total_seats INT DEFAULT 40,
    bus_type ENUM('AC', 'Non-AC') DEFAULT 'Non-AC',
    current_lat DECIMAL(10, 8) DEFAULT NULL,
    current_lng DECIMAL(11, 8) DEFAULT NULL,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    FOREIGN KEY (route_id) REFERENCES routes(id)
);

-- Bookings Table (updated)
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    bus_id INT,
    route_id INT,
    passenger_name VARCHAR(100) NOT NULL,
    passenger_phone VARCHAR(15) NOT NULL,
    passenger_email VARCHAR(100) DEFAULT NULL,
    passenger_count INT DEFAULT 1,
    seat_numbers VARCHAR(100) NOT NULL,
    bus_type ENUM('AC', 'Non-AC') DEFAULT 'Non-AC',
    total_fare DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'bkash', 'nagad', 'card') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    booking_date DATE NOT NULL,
    journey_date DATE NOT NULL,
    status ENUM('confirmed', 'cancelled', 'completed') DEFAULT 'confirmed',
    cancel_reason VARCHAR(255) DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (route_id) REFERENCES routes(id)
);

-- Sample Data
INSERT IGNORE INTO users (name, email, phone, password, role) VALUES
('Admin User', 'admin@bustrack.bd', '01700000000', MD5('admin123'), 'admin'),
('Rahul Ahmed', 'rahul@email.com', '01711111111', MD5('user123'), 'user'),
('Fatima Khan', 'fatima@email.com', '01722222222', MD5('user123'), 'user');

INSERT IGNORE INTO routes (route_name, from_city, to_city, distance, duration, fare) VALUES
('Dhaka-Chittagong Express', 'Dhaka', 'Chittagong', 264, '5h 30m', 450.00),
('Dhaka-Sylhet Highway', 'Dhaka', 'Sylhet', 247, '5h 00m', 400.00),
('Dhaka-Rajshahi Route', 'Dhaka', 'Rajshahi', 256, '4h 45m', 380.00),
('Chittagong-Cox\'s Bazar', 'Chittagong', 'Cox\'s Bazar', 152, '3h 30m', 250.00);

INSERT IGNORE INTO buses (bus_number, bus_name, route_id, total_seats, bus_type, departure_time, arrival_time, current_lat, current_lng) VALUES
('DH-1234', 'Green Line Paribahan', 1, 40, 'AC', '08:00:00', '13:30:00', 23.8103, 90.4125),
('DH-5678', 'Shyamoli NR Travels', 2, 36, 'AC', '09:00:00', '14:00:00', 23.8103, 90.4125),
('DH-9012', 'Hanif Enterprise', 3, 40, 'Non-AC', '07:30:00', '12:15:00', 23.8103, 90.4125),
('CH-3456', 'Soudia Transport', 4, 32, 'Non-AC', '10:00:00', '13:30:00', 22.3569, 91.7832),
('DH-1111', 'Green Line AC Express', 1, 36, 'AC', '14:00:00', '19:30:00', 23.8103, 90.4125),
('DH-2222', 'Shyamoli Non-AC', 2, 40, 'Non-AC', '15:00:00', '20:00:00', 23.8103, 90.4125);

-- Migration: Add new columns if upgrading from old version
-- Run these if bookings table already exists without the new columns:
-- ALTER TABLE bookings ADD COLUMN IF NOT EXISTS passenger_email VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE bookings ADD COLUMN IF NOT EXISTS passenger_count INT DEFAULT 1;
-- ALTER TABLE bookings ADD COLUMN IF NOT EXISTS bus_type ENUM('AC','Non-AC') DEFAULT 'Non-AC';
-- ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_method ENUM('cash','bkash','nagad','card') DEFAULT 'cash';
-- ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','refunded') DEFAULT 'pending';
-- ALTER TABLE bookings ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE bookings ADD COLUMN IF NOT EXISTS refund_amount DECIMAL(10,2) DEFAULT 0;
