<?php
/**
 * Shopping Cart (Use Case 9)
 * View and manage cart contents, proceed to checkout
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';
require_once __DIR__ . '/../Service.php';

// Require login
if (!isLoggedIn()) {
    setErrorMessage('Please login to view your cart.');
    redirect(SITE_URL . 'auth/login.php');
}

// Block freelancers from accessing cart
if (isFreelancer()) {
    setErrorMessage('Freelancers cannot purchase services or access the shopping cart.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Handle remove from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove'])) {
    $removeId = sanitize($_POST['service_id'] ?? '');

    if (isset($_SESSION['cart']) && !empty($removeId)) {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item->getServiceId() === $removeId) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index
                setSuccessMessage('Service removed from cart.');
                break;
            }
        }
    }
    redirect(SITE_URL . 'cart/cart.php');
}

// Get cart items
$cartItems = $_SESSION['cart'] ?? [];
$cartCount = count($cartItems);

// Calculate totals
$subtotal = 0;
$totalFee = 0;
foreach ($cartItems as $item) {
    $subtotal += $item->getPrice();
    $totalFee += $item->calculateServiceFee();
}
$grandTotal = $subtotal + $totalFee;

// Get recently viewed services for empty cart state
$recentlyViewed = [];
if ($cartCount === 0 && isset($_COOKIE['recently_viewed'])) {
    $recentIds = explode(',', $_COOKIE['recently_viewed']);
    $recentIds = array_slice($recentIds, -4);

    if (!empty($recentIds)) {
        $placeholders = str_repeat('?,', count($recentIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name, u.last_name 
            FROM services s 
            JOIN users u ON s.freelancer_id = u.user_id 
            WHERE s.service_id IN ($placeholders) AND s.status = 'Active'
        ");
        $stmt->execute($recentIds);
        $recentlyViewed = $stmt->fetchAll();
    }
}

$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<h1 class="heading-primary">Shopping Cart</h1>

<?php if ($cartCount > 0): ?>
    <div class="two-column-layout">
        <!-- Left Column: Cart Items Table -->
        <div class="column-70">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Freelancer</th>
                            <th>Delivery</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <img src="<?php echo htmlspecialchars($item->getImage1() ?: '/uploads/services/default.jpg'); ?>"
                                            alt="<?php echo htmlspecialchars($item->getTitle()); ?>" class="table-thumbnail"
                                            onerror="this.src='<?php echo SITE_URL; ?>uploads/services/default.jpg'">
                                        <div>
                                            <a
                                                href="<?php echo SITE_URL; ?>services/service-detail.php?id=<?php echo htmlspecialchars($item->getServiceId()); ?>">
                                                <?php echo htmlspecialchars($item->getTitle()); ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 13px;">
                                                <?php echo htmlspecialchars($item->getCategory()); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($item->getFreelancerName()); ?>
                                </td>
                                <td>
                                    <?php echo $item->getFormattedDelivery(); ?>
                                </td>
                                <td class="text-bold">
                                    <?php echo $item->getFormattedPrice(); ?>
                                </td>
                                <td>
                                    <form method="POST" action="">
                                        <input type="hidden" name="service_id"
                                            value="<?php echo htmlspecialchars($item->getServiceId()); ?>">
                                        <button type="submit" name="remove" value="1"
                                            class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-lg">
                <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-secondary">Continue
                    Browsing</a>
            </div>
        </div>

        <!-- Right Column: Order Summary -->
        <div class="column-30">
            <div class="order-summary">
                <h3 class="order-summary-title">Order Summary</h3>

                <div class="order-summary-row">
                    <span>Services (
                        <?php echo $cartCount; ?>)
                    </span>
                    <span>
                        <?php echo formatPrice($subtotal); ?>
                    </span>
                </div>

                <div class="order-summary-row">
                    <span>Service Fee (5%)</span>
                    <span>
                        <?php echo formatPrice($totalFee); ?>
                    </span>
                </div>

                <div class="order-summary-row order-summary-total">
                    <span>Total</span>
                    <span class="amount">
                        <?php echo formatPrice($grandTotal); ?>
                    </span>
                </div>

                <a href="<?php echo SITE_URL; ?>checkout/checkout.php" class="btn btn-success btn-full mt-lg">
                    Proceed to Checkout
                </a>

                <div class="text-center text-muted mt-md" style="font-size: 13px;">
                    Secure checkout • Pay with credit card
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Empty Cart State -->
    <div class="cart-empty">
        <div class="cart-empty-icon">🛒</div>
        <p class="cart-empty-message">Your cart is empty</p>
        <p class="text-muted mb-lg">Looks like you haven't added any services yet</p>
        <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-primary">Browse Services</a>
    </div>

    <!-- Recently Viewed Section -->
    <?php if (count($recentlyViewed) > 0): ?>
        <div class="recently-viewed">
            <h2 class="heading-secondary recently-viewed-title">Recently Viewed Services</h2>
            <div class="services-grid">
                <?php foreach ($recentlyViewed as $service): ?>
                    <div class="service-card">
                        <a
                            href="<?php echo SITE_URL; ?>services/service-detail.php?id=<?php echo htmlspecialchars($service['service_id']); ?>">
                            <img src="<?php echo htmlspecialchars($service['image_1'] ?: '/uploads/services/default.jpg'); ?>"
                                alt="<?php echo htmlspecialchars($service['title']); ?>" class="service-card-image"
                                onerror="this.src='<?php echo SITE_URL; ?>uploads/services/default.jpg'">
                        </a>
                        <div class="service-card-content">
                            <h3 class="service-card-title">
                                <a
                                    href="<?php echo SITE_URL; ?>services/service-detail.php?id=<?php echo htmlspecialchars($service['service_id']); ?>">
                                    <?php echo htmlspecialchars($service['title']); ?>
                                </a>
                            </h3>
                            <div class="service-card-freelancer">
                                <span>
                                    <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                                </span>
                            </div>
                            <div class="service-card-price">
                                <?php echo formatPrice($service['price']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>