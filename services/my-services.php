<?php
/**
 * My Services (Use Case 5)
 * Display all services owned by the logged-in freelancer
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Require freelancer login
if (!isFreelancer()) {
    setErrorMessage('Only freelancers can access My Services.');
    redirect(SITE_URL . 'services/browse-services.php');
}

$userId = getCurrentUserId();

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $serviceId = sanitize($_POST['service_id'] ?? '');
    $newStatus = sanitize($_POST['new_status'] ?? '');

    if (in_array($newStatus, ['Active', 'Inactive'])) {
        // Verify ownership
        $stmt = $pdo->prepare("SELECT service_id, featured_status FROM services WHERE service_id = :service_id AND freelancer_id = :user_id");
        $stmt->execute([':service_id' => $serviceId, ':user_id' => $userId]);
        $service = $stmt->fetch();

        if ($service) {
            // If deactivating, also remove featured status
            $featuredStatus = $newStatus === 'Inactive' ? 'No' : $service['featured_status'];

            $stmt = $pdo->prepare("UPDATE services SET status = :status, featured_status = :featured WHERE service_id = :service_id");
            $stmt->execute([':status' => $newStatus, ':featured' => $featuredStatus, ':service_id' => $serviceId]);

            setSuccessMessage('Service updated successfully!');
        }
    }
    redirect(SITE_URL . 'services/my-services.php');
}

// Handle featured toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured'])) {
    $serviceId = sanitize($_POST['service_id'] ?? '');
    $newFeatured = sanitize($_POST['new_featured'] ?? '');

    if (in_array($newFeatured, ['Yes', 'No'])) {
        // Check current featured count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :user_id AND featured_status = 'Yes'");
        $stmt->execute([':user_id' => $userId]);
        $featuredCount = $stmt->fetchColumn();

        // Verify service is active and owned
        $stmt = $pdo->prepare("SELECT status FROM services WHERE service_id = :service_id AND freelancer_id = :user_id");
        $stmt->execute([':service_id' => $serviceId, ':user_id' => $userId]);
        $service = $stmt->fetch();

        if ($service) {
            if ($newFeatured === 'Yes' && $featuredCount >= 3) {
                setErrorMessage('You can only have 3 featured services at a time.');
            } elseif ($newFeatured === 'Yes' && $service['status'] !== 'Active') {
                setErrorMessage('Only active services can be featured.');
            } else {
                $stmt = $pdo->prepare("UPDATE services SET featured_status = :featured WHERE service_id = :service_id");
                $stmt->execute([':featured' => $newFeatured, ':service_id' => $serviceId]);
                setSuccessMessage('Featured status updated!');
            }
        }
    }
    redirect(SITE_URL . 'services/my-services.php');
}

// Get freelancer statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE freelancer_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$stats['total'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as active FROM services WHERE freelancer_id = :user_id AND status = 'Active'");
$stmt->execute([':user_id' => $userId]);
$stats['active'] = $stmt->fetch()['active'];

$stmt = $pdo->prepare("SELECT COUNT(*) as featured FROM services WHERE freelancer_id = :user_id AND featured_status = 'Yes'");
$stmt->execute([':user_id' => $userId]);
$stats['featured'] = $stmt->fetch()['featured'];

$stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM orders WHERE freelancer_id = :user_id AND status = 'Completed'");
$stmt->execute([':user_id' => $userId]);
$stats['completed'] = $stmt->fetch()['completed'];

// Get all services
$stmt = $pdo->prepare("SELECT * FROM services WHERE freelancer_id = :user_id ORDER BY created_date DESC");
$stmt->execute([':user_id' => $userId]);
$services = $stmt->fetchAll();

$pageTitle = 'My Services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="two-column-layout">
    <!-- Left Column: Stats and Actions -->
    <div class="column-30">
        <!-- Statistics Card -->
        <div class="statistics-card">
            <h3 class="heading-tertiary">Service Statistics</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">
                        <?php echo $stats['total']; ?>
                    </div>
                    <div class="stat-label">Total Services</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value active">
                        <?php echo $stats['active']; ?>
                    </div>
                    <div class="stat-label">Active Services</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value featured">
                        <?php echo $stats['featured']; ?>/3
                    </div>
                    <div class="stat-label">Featured Services</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php echo $stats['completed']; ?>
                    </div>
                    <div class="stat-label">Completed Orders</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mt-lg">
            <h3 class="heading-tertiary">Quick Actions</h3>
            <a href="<?php echo SITE_URL; ?>services/create-service.php" class="btn btn-success btn-full mt-md">
                + Create New Service
            </a>
            <a href="<?php echo SITE_URL; ?>orders/my-orders.php" class="btn btn-primary btn-full mt-md">
                View My Orders
            </a>
        </div>
    </div>

    <!-- Right Column: Services Table -->
    <div class="column-70">
        <h1 class="heading-primary">My Services</h1>

        <?php if (count($services) > 0): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Service Title</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars(getServiceImageUrl($service['image_1'])); ?>"
                                        alt="<?php echo htmlspecialchars($service['title']); ?>" class="table-thumbnail"
                                        onerror="this.src='<?php echo SITE_URL; ?>uploads/services/default.jpg'">
                                </td>
                                <td>
                                    <a
                                        href="<?php echo SITE_URL; ?>services/service-detail.php?id=<?php echo htmlspecialchars($service['service_id']); ?>">
                                        <?php echo htmlspecialchars($service['title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($service['category']); ?>
                                </td>
                                <td>
                                    <?php echo formatPrice($service['price']); ?>
                                </td>
                                <td>
                                    <span
                                        class="badge <?php echo $service['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $service['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($service['featured_status'] === 'Yes'): ?>
                                        <span class="featured-indicator">Featured</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($service['created_date']); ?>
                                </td>
                                <td>
                                    <div class="btn-group" style="flex-direction: column; gap: 5px;">
                                        <a href="<?php echo SITE_URL; ?>services/edit-service.php?id=<?php echo htmlspecialchars($service['service_id']); ?>"
                                            class="btn btn-primary btn-sm">
                                            Edit
                                        </a>

                                        <!-- Status Toggle -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <input type="hidden" name="service_id"
                                                value="<?php echo htmlspecialchars($service['service_id']); ?>">
                                            <?php if ($service['status'] === 'Active'): ?>
                                                <input type="hidden" name="new_status" value="Inactive">
                                                <button type="submit" class="btn btn-danger btn-sm">Deactivate</button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status" value="Active">
                                                <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Featured Toggle (only for active services) -->
                                        <?php if ($service['status'] === 'Active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="toggle_featured" value="1">
                                                <input type="hidden" name="service_id"
                                                    value="<?php echo htmlspecialchars($service['service_id']); ?>">
                                                <?php if ($service['featured_status'] === 'Yes'): ?>
                                                    <input type="hidden" name="new_featured" value="No">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Unfeature</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="new_featured" value="Yes">
                                                    <button type="submit" class="btn btn-sm"
                                                        style="background-color: var(--gold); color: black;">★ Feature</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="cart-empty">
                <div class="cart-empty-icon">📦</div>
                <p class="cart-empty-message">You haven't created any services yet</p>
                <a href="<?php echo SITE_URL; ?>services/create-service.php" class="btn btn-success mt-md">Create Your First
                    Service</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>