-- Freelance Services Marketplace Database Schema
-- Character Set: utf8mb4, Collation: utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS freelance_marketplace
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE freelance_marketplace;

-- =====================================================
-- Users Table
-- Purpose: Stores all user accounts for both clients and freelancers
-- =====================================================
CREATE TABLE users (
    user_id VARCHAR(10) PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(10) NOT NULL,
    country VARCHAR(50) NOT NULL,
    city VARCHAR(50) NOT NULL,
    role ENUM('Client', 'Freelancer') NOT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    profile_photo VARCHAR(255),
    bio TEXT,
    professional_title VARCHAR(100),
    skills VARCHAR(200),
    years_experience INT DEFAULT 0,
    failed_login_attempts INT DEFAULT 0,
    lockout_until TIMESTAMP NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Categories Table
-- Purpose: Stores service categories for organizing and filtering
-- =====================================================
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255)
);

-- Insert predefined categories
INSERT INTO categories (category_name, description) VALUES
('Web Development', 'Web and software development services'),
('Graphic Design', 'Visual design and branding services'),
('Writing & Translation', 'Content writing and translation services'),
('Digital Marketing', 'Online marketing and SEO services'),
('Video & Animation', 'Video production and animation services'),
('Music & Audio', 'Audio production and music services'),
('Business Consulting', 'Business and professional consulting'),
('Tutoring & Education', 'Educational and tutoring services');

-- =====================================================
-- Services Table
-- Purpose: Stores all services offered by freelancers
-- =====================================================
CREATE TABLE services (
    service_id VARCHAR(10) PRIMARY KEY,
    freelancer_id VARCHAR(10) NOT NULL,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    delivery_time INT NOT NULL,
    revisions_included INT NOT NULL,
    image_1 VARCHAR(255) NOT NULL,
    image_2 VARCHAR(255),
    image_3 VARCHAR(255),
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    featured_status ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =====================================================
-- Orders Table
-- Purpose: Stores all order transactions between clients and freelancers
-- =====================================================
CREATE TABLE orders (
    order_id VARCHAR(10) PRIMARY KEY,
    client_id VARCHAR(10) NOT NULL,
    freelancer_id VARCHAR(10) NOT NULL,
    service_id VARCHAR(10) NOT NULL,
    service_title VARCHAR(200) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    service_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_time INT NOT NULL,
    revisions_included INT NOT NULL,
    requirements TEXT NOT NULL,
    special_instructions TEXT,
    preferred_deadline DATE,
    deliverable_notes TEXT,
    status ENUM('Pending', 'In Progress', 'Delivered', 'Completed', 'Revision Requested', 'Cancelled') NOT NULL DEFAULT 'Pending',
    payment_method VARCHAR(50) NOT NULL,
    cancellation_reason TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_delivery DATE NOT NULL,
    actual_delivery_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE RESTRICT
);

-- =====================================================
-- Revision Requests Table
-- Purpose: Tracks revision requests made by clients on delivered orders
-- =====================================================
CREATE TABLE revision_requests (
    revision_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(10) NOT NULL,
    revision_notes TEXT NOT NULL,
    revision_file VARCHAR(255),
    request_status ENUM('Pending', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    freelancer_response TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- =====================================================
-- File Attachments Table
-- Purpose: Stores multiple file attachments for orders
-- =====================================================
CREATE TABLE file_attachments (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(10) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type ENUM('requirement', 'deliverable', 'revision') NOT NULL,
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- =====================================================
-- Sample Test Data
-- =====================================================

-- Test Users (Password is 'Test@123' hashed with password_hash)
INSERT INTO users (user_id, first_name, last_name, email, password, phone, country, city, role, bio, professional_title, skills, years_experience) VALUES
('1000000001', 'John', 'Client', 'client@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0501234567', 'Jordan', 'Amman', 'Client', 'I am a client looking for freelance services.', NULL, NULL, NULL),
('1000000002', 'Sarah', 'Designer', 'freelancer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0509876543', 'Jordan', 'Irbid', 'Freelancer', 'Professional graphic designer with 5 years of experience in branding and web design.', 'Senior Graphic Designer', 'Logo Design, Brand Identity, Web Design, Illustration', 5),
('1000000003', 'Mike', 'Developer', 'mike.dev@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0505551234', 'Jordan', 'Zarqa', 'Freelancer', 'Full-stack web developer specializing in PHP, MySQL, and modern frameworks.', 'Full Stack Developer', 'PHP, MySQL, JavaScript, React, Node.js', 7),
('1000000004', 'Emma', 'Writer', 'emma.writer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0507778899', 'Jordan', 'Aqaba', 'Freelancer', 'Content writer and translator with expertise in technical and creative writing.', 'Content Writer & Translator', 'Article Writing, Copywriting, Translation, Proofreading', 4);

-- Test Services
INSERT INTO services (service_id, freelancer_id, title, category, subcategory, description, price, delivery_time, revisions_included, image_1, status, featured_status) VALUES
('2000000001', '1000000002', 'Professional Logo Design', 'Graphic Design', 'Logo Design', 'I will create a unique and professional logo design for your business. This includes multiple concepts, revisions, and all source files. Perfect for startups and established businesses looking to rebrand.', 150.00, 5, 3, '/uploads/services/2000000001/image_01.jpg', 'Active', 'Yes'),
('2000000002', '1000000002', 'Complete Brand Identity Package', 'Graphic Design', 'Brand Identity', 'Get a complete brand identity including logo, business cards, letterhead, and brand guidelines. Everything you need to establish a professional brand image.', 500.00, 14, 5, '/uploads/services/2000000002/image_01.jpg', 'Active', 'No'),
('2000000003', '1000000003', 'Custom WordPress Website', 'Web Development', 'WordPress Development', 'I will build a fully responsive WordPress website with custom theme design, SEO optimization, and all essential plugins. Perfect for businesses and portfolios.', 800.00, 21, 3, '/uploads/services/2000000003/image_01.jpg', 'Active', 'Yes'),
('2000000004', '1000000003', 'PHP Web Application Development', 'Web Development', 'Backend Development', 'Custom PHP web application development with MySQL database. Including user authentication, admin panel, and API integration.', 1200.00, 30, 2, '/uploads/services/2000000004/image_01.jpg', 'Active', 'No'),
('2000000005', '1000000004', 'SEO Optimized Article Writing', 'Writing & Translation', 'Article Writing', 'High-quality, SEO-optimized articles for your blog or website. Well-researched content that engages readers and ranks well in search engines.', 50.00, 3, 2, '/uploads/services/2000000005/image_01.jpg', 'Active', 'Yes'),
('2000000006', '1000000004', 'Professional Translation Services', 'Writing & Translation', 'Translation', 'Accurate translation between Arabic and English for documents, websites, and marketing materials. Native-level fluency in both languages.', 75.00, 5, 999, '/uploads/services/2000000006/image_01.jpg', 'Active', 'No');
