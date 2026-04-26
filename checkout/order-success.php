<?php
/**
 * Order Success Page
 * Displays confirmation after successful checkout
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Require login
if (!isLoggedIn()) {
    redirect(SITE_URL . 'auth/login.php');
}

// Check for completed orders
$completedOrders = $_SESSION['completed_orders'] ?? [];

if (empty($completedOrders)) {
    redirect(SITE_URL . 'orders/my-orders.php');
}

// Clear the completed orders from session after displaying
unset($_SESSION['completed_orders']);

// Calculate total
$grandTotal = 0;
foreach ($completedOrders as $order) {
    $grandTotal += $order['price'];
}

$pageTitle = 'Order Successful';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<!-- Success Banner -->
<div class="success-banner">
    <div class="success-icon">✓</div>
    <h1 class="success-heading">Order Placed Successfully!</h1>
    <p class="success-subtext">Thank you for your order. Your freelancers have been notified.</p>
</div>

<div class="order-cards">
    <h2 class="heading-secondary text-center">Your Orders</h2>

    <?php foreach ($completedOrders as $order): ?>
        <div class="order-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3><?php echo htmlspecialchars($order['title']); ?></h3>
                    <p class="text-muted">Order ID: <?php echo htmlspecialchars($order['order_id']); ?></p>
                </div>
                <div class="text-right">
                    <span class="badge status-pending">Pending</span>
                    <p class="text-bold mt-sm"><?php echo formatPrice($order['price']); ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card text-center">
        <p class="text-muted mb-md">Total Paid: <strong><?php echo formatPrice($grandTotal); ?></strong></p>
        <p class="mb-lg">The freelancers will review your requirements and start working on your orders.</p>

        <div class="btn-group" style="justify-content: center;">
            <a href="<?php echo SITE_URL; ?>orders/my-orders.php" class="btn btn-primary">View My Orders</a>
            <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-secondary">Continue
                Browsing</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>