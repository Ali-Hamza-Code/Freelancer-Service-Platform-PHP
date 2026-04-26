<?php
/**
 * Application Configuration
 */

// Include core classes before session start
require_once __DIR__ . '/Service.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'Freelance Marketplace');
define('SITE_URL', '/' . basename(__DIR__) . '/');

// Upload paths
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('PROFILE_UPLOAD_PATH', UPLOAD_PATH . 'profiles/');
define('SERVICE_UPLOAD_PATH', UPLOAD_PATH . 'services/');
define('ORDER_UPLOAD_PATH', UPLOAD_PATH . 'orders/');

// Upload limits
define('MAX_PROFILE_PHOTO_SIZE', 2 * 1024 * 1024);      // 2MB
define('MAX_SERVICE_IMAGE_SIZE', 5 * 1024 * 1024);      // 5MB
define('MAX_ORDER_FILE_SIZE', 10 * 1024 * 1024);        // 10MB
define('MAX_DELIVERY_FILE_SIZE', 50 * 1024 * 1024);     // 50MB

// Image dimensions
define('MIN_PROFILE_PHOTO_SIZE', 300);   // 300x300 minimum
define('MIN_SERVICE_IMAGE_WIDTH', 800);  // 800px minimum width
define('MIN_SERVICE_IMAGE_HEIGHT', 600); // 600px minimum height

// Allowed file types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png']);
define('ALLOWED_ORDER_FILE_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/zip', 'image/jpeg', 'image/png']);

// Service categories and subcategories
$CATEGORIES = [
    'Web Development' => [
        'Frontend Development',
        'Backend Development',
        'Full Stack Development',
        'WordPress Development',
        'E-commerce Development',
        'Bug Fixes'
    ],
    'Graphic Design' => [
        'Logo Design',
        'Brand Identity',
        'Print Design',
        'Illustration',
        'UI/UX Design',
        'Web Design'
    ],
    'Writing & Translation' => [
        'Article Writing',
        'Copywriting',
        'Proofreading',
        'Translation',
        'Technical Writing',
        'Content Writing'
    ],
    'Digital Marketing' => [
        'SEO',
        'Social Media Marketing',
        'Email Marketing',
        'Content Marketing',
        'PPC Advertising'
    ],
    'Video & Animation' => [
        'Video Editing',
        'Animation',
        'Whiteboard Animation',
        'Video Production',
        'Motion Graphics'
    ],
    'Music & Audio' => [
        'Music Production',
        'Voice Over',
        'Audio Editing',
        'Sound Design',
        'Podcast Editing'
    ],
    'Business Consulting' => [
        'Business Planning',
        'Financial Consulting',
        'Legal Consulting',
        'HR Consulting',
        'Market Research'
    ],
    'Tutoring & Education' => [
        'Academic Tutoring',
        'Language Teaching',
        'Test Preparation',
        'Online Courses',
        'Career Coaching'
    ]
];

// Countries list
$COUNTRIES = [
    'Jordan',
    'United States',
    'United Kingdom',
    'Canada',
    'Australia',
    'Germany',
    'France',
    'Saudi Arabia',
    'UAE',
    'Egypt',
    'Palestine',
    'Lebanon',
    'Syria',
    'Iraq',
    'Kuwait',
    'Qatar',
    'Bahrain',
    'Oman'
];

// Cities (Jordan focused)
$CITIES = [
    'Amman',
    'Irbid',
    'Zarqa',
    'Aqaba',
    'Madaba',
    'Jerash',
    'Ajloun',
    'Salt',
    'Karak',
    'Mafraq',
    'Tafilah',
    'Maan'
];

// Order statuses
$ORDER_STATUSES = [
    'Pending' => 'status-pending',
    'In Progress' => 'status-in-progress',
    'Delivered' => 'status-completed',
    'Completed' => 'status-completed',
    'Revision Requested' => 'status-pending',
    'Cancelled' => 'status-cancelled'
];

// Session timeout (24 hours)
define('SESSION_TIMEOUT', 24 * 60 * 60);

// Login security
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 30 * 60); // 30 minutes
define('ATTEMPT_WINDOW', 15 * 60);   // 15 minutes

// Pagination
define('SERVICES_PER_PAGE', 12);
define('ORDERS_PER_PAGE', 10);

// Service fee percentage
define('SERVICE_FEE_PERCENT', 5);

/**
 * Helper Functions
 */

// Generate unique 10-digit ID
function generateUniqueId()
{
    return str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
}

// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check if user is a client
function isClient()
{
    return isLoggedIn() && $_SESSION['role'] === 'Client';
}

// Check if user is a freelancer
function isFreelancer()
{
    return isLoggedIn() && $_SESSION['role'] === 'Freelancer';
}

// Get current user ID
function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

// Get current user role
function getCurrentUserRole()
{
    return $_SESSION['role'] ?? null;
}

// Redirect helper
function redirect($url)
{
    header("Location: $url");
    exit();
}

// Display success message
function setSuccessMessage($message)
{
    $_SESSION['success_message'] = $message;
}

// Display error message
function setErrorMessage($message)
{
    $_SESSION['error_message'] = $message;
}

// Get and clear success message
function getSuccessMessage()
{
    $message = $_SESSION['success_message'] ?? null;
    unset($_SESSION['success_message']);
    return $message;
}

// Get and clear error message
function getErrorMessage()
{
    $message = $_SESSION['error_message'] ?? null;
    unset($_SESSION['error_message']);
    return $message;
}

// Sanitize input
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format price
function formatPrice($price)
{
    return '$' . number_format($price, 2);
}

// Format date
function formatDate($date)
{
    return date('M d, Y', strtotime($date));
}

// Format file size
function formatFileSize($bytes)
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 0) . ' KB';
}

// Get file extension
function getFileExtension($filename)
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Calculate service fee
function calculateServiceFee($price)
{
    return $price * (SERVICE_FEE_PERCENT / 100);
}

// Calculate total with fee
function calculateTotalWithFee($price)
{
    return $price + calculateServiceFee($price);
}

// Get valid asset URL handling legacy paths
function getImageUrl($path)
{
    if (empty($path))
        return '';
    if (strpos($path, SITE_URL) === 0)
        return $path;
    if (strpos($path, '/uploads/') === 0)
        return SITE_URL . ltrim($path, '/');
    return $path;
}

// Get profile photo URL with default fallback
function getProfilePhotoUrl($path)
{
    $url = getImageUrl($path);
    return $url ?: SITE_URL . 'uploads/profiles/default.png';
}

// Get service image URL with default fallback
function getServiceImageUrl($path)
{
    $url = getImageUrl($path);
    return $url ?: SITE_URL . 'uploads/services/default.jpg';
}
?>