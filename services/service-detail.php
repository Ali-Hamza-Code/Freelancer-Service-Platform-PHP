<?php
/**
 * Service Detail Page (Use Case 7)
 * Display complete service details with image gallery and booking card
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Get service ID
$serviceId = sanitize($_GET['id'] ?? '');

if (empty($serviceId)) {
    setErrorMessage('Service not found.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Fetch service with freelancer info
$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.profile_photo, u.registration_date as freelancer_since,
           u.professional_title, u.bio as freelancer_bio
    FROM services s 
    JOIN users u ON s.freelancer_id = u.user_id 
    WHERE s.service_id = :service_id
");
$stmt->execute([':service_id' => $serviceId]);
$service = $stmt->fetch();

if (!$service) {
    setErrorMessage('Service not found.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Check if service is active or user is the owner
$isOwner = isLoggedIn() && getCurrentUserId() === $service['freelancer_id'];

if ($service['status'] !== 'Active' && !$isOwner) {
    setErrorMessage('This service is no longer available.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Update recently viewed cookie
$recentlyViewed = [];
if (isset($_COOKIE['recently_viewed'])) {
    $recentlyViewed = explode(',', $_COOKIE['recently_viewed']);
}

// Remove current service if it exists
$recentlyViewed = array_diff($recentlyViewed, [$serviceId]);
// Add to end
$recentlyViewed[] = $serviceId;
// Keep only last 4
$recentlyViewed = array_slice($recentlyViewed, -4);
// Update cookie (30 days)
setcookie('recently_viewed', implode(',', $recentlyViewed), time() + (30 * 24 * 60 * 60), '/');

// Get images array
$images = [$service['image_1']];
if ($service['image_2'])
    $images[] = $service['image_2'];
if ($service['image_3'])
    $images[] = $service['image_3'];

// Selected image (for gallery)
$selectedImage = $_GET['img'] ?? 0;
$selectedImage = max(0, min((int) $selectedImage, count($images) - 1));

$pageTitle = $service['title'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<!-- Inactive Service Notice -->
<?php if ($service['status'] !== 'Active' && $isOwner): ?>
    <div class="alert alert-warning">
        <strong>Notice:</strong> This service is currently inactive and not visible to clients.
        <a href="<?php echo SITE_URL; ?>services/edit-service.php?id=<?php echo htmlspecialchars($serviceId); ?>">Edit
            Service</a> to
        reactivate.
    </div>
<?php endif; ?>

<div class="two-column-layout">
    <!-- Left Column: Images and Info -->
    <div class="column-65">
        <!-- Category Breadcrumb -->
        <div class="category-breadcrumb">
            <a href="<?php echo SITE_URL; ?>services/browse-services.php">Home</a>
            <span>&gt;</span>
            <a
                href="<?php echo SITE_URL; ?>services/browse-services.php?category=<?php echo urlencode($service['category']); ?>">
                <?php echo htmlspecialchars($service['category']); ?>
            </a>
            <span>&gt;</span>
            <span>
                <?php echo htmlspecialchars($service['subcategory']); ?>
            </span>
        </div>

        <!-- Image Gallery -->
        <div class="service-gallery">
            <img src="<?php echo htmlspecialchars(getServiceImageUrl($images[$selectedImage])); ?>"
                alt="<?php echo htmlspecialchars($service['title']); ?>" class="gallery-main" id="main-image"
                onerror="this.src='<?php echo SITE_URL; ?>uploads/services/default.jpg'">

            <?php if (count($images) > 1): ?>
                <div class="gallery-thumbnails">
                    <?php foreach ($images as $index => $img): ?>
                        <a href="?id=<?php echo htmlspecialchars($serviceId); ?>&img=<?php echo $index; ?>">
                            <img src="<?php echo htmlspecialchars(getServiceImageUrl($img)); ?>"
                                alt="Thumbnail <?php echo $index + 1; ?>"
                                class="gallery-thumbnail <?php echo $index === $selectedImage ? 'active' : ''; ?>"
                                onerror="this.src='<?php echo SITE_URL; ?>uploads/services/default.jpg'">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Service Title -->
        <h1 class="heading-primary">
            <?php echo htmlspecialchars($service['title']); ?>
        </h1>

        <!-- Freelancer Info Card -->
        <div class="service-info">
            <div class="freelancer-info">
                <img src="<?php echo htmlspecialchars(getProfilePhotoUrl($service['profile_photo'])); ?>"
                    alt="<?php echo htmlspecialchars($service['first_name']); ?>"
                    onerror="this.src='<?php echo SITE_URL; ?>uploads/profiles/default.png'">
                <div class="freelancer-info-text">
                    <div class="freelancer-info-name">
                        <a
                            href="<?php echo SITE_URL; ?>profile.php?id=<?php echo htmlspecialchars($service['freelancer_id']); ?>">
                            <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                        </a>
                    </div>
                    <?php if ($service['professional_title']): ?>
                        <div class="text-muted">
                            <?php echo htmlspecialchars($service['professional_title']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="freelancer-info-since">
                        Member since
                        <?php echo formatDate($service['freelancer_since']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Description -->
        <div class="mt-lg">
            <h2 class="heading-secondary">About This Service</h2>
            <div style="line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($service['description'])); ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Booking Card -->
    <div class="column-35">
        <div class="booking-card">
            <div class="booking-card-price">
                <div class="booking-card-price-label">Starting at</div>
                <div class="booking-card-price-value">
                    <?php echo formatPrice($service['price']); ?>
                </div>
            </div>

            <div class="booking-card-info">
                <strong>⏱ Delivery Time:</strong>
                <?php echo $service['delivery_time']; ?> day(s)
            </div>
            <div class="booking-card-info">
                <strong>🔄 Revisions:</strong>
                <?php echo $service['revisions_included'] >= 999 ? 'Unlimited' : $service['revisions_included']; ?>
            </div>

            <?php if (!isLoggedIn()): ?>
                <!-- Guest: Show Login Button -->
                <a href="<?php echo SITE_URL; ?>auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                    class="btn btn-primary btn-full mt-lg">
                    Login to Order
                </a>
                <p class="text-center text-muted mt-md">
                    <a href="<?php echo SITE_URL; ?>auth/register.php">Create an account</a> to order services
                </p>

            <?php elseif ($isOwner): ?>
                <!-- Owner: Show Edit Button -->
                <a href="<?php echo SITE_URL; ?>services/edit-service.php?id=<?php echo htmlspecialchars($serviceId); ?>"
                    class="btn btn-primary btn-full mt-lg">
                    Edit Service
                </a>
                <a href="<?php echo SITE_URL; ?>services/my-services.php" class="btn btn-secondary btn-full mt-md">
                    My Services
                </a>

            <?php elseif (isFreelancer()): ?>
                <!-- Freelancer (Not Owner): Show Restriction Message -->
                <div class="alert alert-warning mt-lg">
                    <strong>Note:</strong> Freelancer accounts cannot purchase services. Please create a Client account to
                    place orders.
                </div>

            <?php else: ?>
                <!-- Client: Show Cart Buttons -->
                <form action="<?php echo SITE_URL; ?>cart/add-to-cart.php" method="POST">
                    <input type="hidden" name="service_id" value="<?php echo htmlspecialchars($serviceId); ?>">
                    <button type="submit" name="action" value="add" class="btn btn-primary btn-full mt-lg">
                        Add to Cart
                    </button>
                    <button type="submit" name="action" value="order_now" class="btn btn-success btn-full mt-md">
                        Order Now
                    </button>
                </form>
            <?php endif; ?>

            <!-- Service Details Summary -->
            <div class="mt-lg" style="border-top: 1px solid var(--border-gray); padding-top: 15px;">
                <div class="text-muted" style="font-size: 14px;">
                    <p><strong>Category:</strong>
                        <?php echo htmlspecialchars($service['category']); ?>
                    </p>
                    <p><strong>Subcategory:</strong>
                        <?php echo htmlspecialchars($service['subcategory']); ?>
                    </p>
                    <p><strong>Service ID:</strong>
                        <?php echo htmlspecialchars($service['service_id']); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>