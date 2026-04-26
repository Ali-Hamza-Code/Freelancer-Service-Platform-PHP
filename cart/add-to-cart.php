<?php
/**
 * Add to Cart (Use Case 8)
 * Adds a service to the session-based shopping cart
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';
require_once __DIR__ . '/../Service.php';

// Require login
if (!isLoggedIn()) {
    setErrorMessage('Please login to add services to cart.');
    redirect(SITE_URL . 'auth/login.php');
}

// Block freelancers from purchasing
if (isFreelancer()) {
    setErrorMessage('Freelancers cannot purchase services. Please use a Client account.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . 'services/browse-services.php');
}

$serviceId = sanitize($_POST['service_id'] ?? '');
$action = sanitize($_POST['action'] ?? 'add');

if (empty($serviceId)) {
    setErrorMessage('Invalid service.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Fetch service with freelancer info
$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name 
    FROM services s 
    JOIN users u ON s.freelancer_id = u.user_id 
    WHERE s.service_id = :service_id AND s.status = 'Active'
");
$stmt->execute([':service_id' => $serviceId]);
$serviceData = $stmt->fetch();

if (!$serviceData) {
    setErrorMessage('Service not found or no longer available.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Check if user is the owner (freelancers can't order their own services)
if (getCurrentUserId() === $serviceData['freelancer_id']) {
    setErrorMessage('You cannot order your own service.');
    redirect(SITE_URL . 'services/service-detail.php?id=' . $serviceId);
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if service is already in cart
$alreadyInCart = false;
foreach ($_SESSION['cart'] as $item) {
    if ($item->getServiceId() === $serviceId) {
        $alreadyInCart = true;
        break;
    }
}

if ($alreadyInCart) {
    setErrorMessage('This service is already in your cart.');
} else {
    // Create Service object with price locked at current value
    $serviceData['freelancer_name'] = $serviceData['first_name'] . ' ' . $serviceData['last_name'];
    $serviceObject = new Service($serviceData);

    // Add to cart
    $_SESSION['cart'][] = $serviceObject;

    setSuccessMessage('Service added to cart successfully!');
}

// Redirect based on action
if ($action === 'order_now') {
    redirect(SITE_URL . 'cart/cart.php');
} else {
    redirect(SITE_URL . 'services/service-detail.php?id=' . $serviceId);
}
?>