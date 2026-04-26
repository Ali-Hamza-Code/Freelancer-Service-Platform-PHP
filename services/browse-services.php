<?php
/**
 * Browse Services (Use Case 6)
 * Display all active services with search, filter, and pagination
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Get search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'newest');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = SERVICES_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build query for active services
$whereConditions = ["s.status = 'Active'"];
$params = [];

// Search condition
if (!empty($search)) {
    $whereConditions[] = "(s.title LIKE :search OR s.description LIKE :search_desc)";
    $params[':search'] = '%' . $search . '%';
    $params[':search_desc'] = '%' . $search . '%';
}

// Category filter
if (!empty($category)) {
    $whereConditions[] = "s.category = :category";
    $params[':category'] = $category;
}

$whereClause = implode(' AND ', $whereConditions);

// Sort order
$orderBy = match ($sort) {
    'oldest' => 's.created_date ASC',
    'price_low' => 's.price ASC',
    'price_high' => 's.price DESC',
    default => 's.created_date DESC'
};

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM services s WHERE $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalServices = $stmt->fetchColumn();
$totalPages = ceil($totalServices / $perPage);

// Get featured services (separate query)
$featuredSql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo 
                FROM services s 
                JOIN users u ON s.freelancer_id = u.user_id 
                WHERE s.status = 'Active' AND s.featured_status = 'Yes'
                ORDER BY s.created_date DESC
                LIMIT 6";
$stmt = $pdo->query($featuredSql);
$featuredServices = $stmt->fetchAll();

// Get paginated services
$sql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo 
        FROM services s 
        JOIN users u ON s.freelancer_id = u.user_id 
        WHERE $whereClause 
        ORDER BY $orderBy 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$services = $stmt->fetchAll();

// Get categories for filter
$categoriesStmt = $pdo->query("SELECT DISTINCT category FROM services WHERE status = 'Active' ORDER BY category");
$availableCategories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Browse Services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<h1 class="heading-primary">Browse Services</h1>

<!-- Search and Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="" class="filter-bar">
        <input type="text" name="search" class="form-input" placeholder="Search services..."
            value="<?php echo htmlspecialchars($search); ?>">

        <select name="category" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($availableCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort" class="form-select">
            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low
            </option>
        </select>

        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if (!empty($search) || !empty($category)): ?>
            <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Search Results Info -->
<?php if (!empty($search) || !empty($category)): ?>
    <div class="search-results-info">
        <?php if (!empty($search)): ?>
            <span>Search results for "<strong>
                    <?php echo htmlspecialchars($search); ?>
                </strong>"</span>
        <?php endif; ?>
        <?php if (!empty($category)): ?>
            <span>Category: <strong>
                    <?php echo htmlspecialchars($category); ?>
                </strong></span>
        <?php endif; ?>
        <span> -
            <?php echo $totalServices; ?> service(s) found
        </span>
        <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="text-primary">Show All Services</a>
    </div>
<?php endif; ?>

<!-- Featured Services Section -->
<?php if (empty($search) && empty($category) && $page === 1 && count($featuredServices) > 0): ?>
    <section class="featured-section">
        <h2 class="heading-secondary">⭐ Featured Services</h2>
        <div class="services-grid">
            <?php foreach ($featuredServices as $service): ?>
                <div class="service-card service-card-featured">
                    <span class="badge-featured">Featured</span>
                    <a
                        href="<?php echo SITE_URL; ?>services/service-detail.php?id=<?php echo htmlspecialchars($service['service_id']); ?>">
                        <img src="<?php echo htmlspecialchars(getServiceImageUrl($service['image_1'])); ?>"
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
                            <img src="<?php echo htmlspecialchars(getProfilePhotoUrl($service['profile_photo'])); ?>"
                                alt="<?php echo htmlspecialchars($service['first_name']); ?>"
                                onerror="this.src='<?php echo SITE_URL; ?>uploads/profiles/default.png'">
                            <span>
                                <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                            </span>
                        </div>
                        <div class="service-card-category">
                            <?php echo htmlspecialchars($service['category']); ?>
                        </div>
                        <div class="service-card-price">Starting at
                            <?php echo formatPrice($service['price']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- All Services Grid -->
<section>
    <h2 class="heading-secondary">All Services</h2>

    <?php if (count($services) > 0): ?>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card <?php echo $service['featured_status'] === 'Yes' ? 'service-card-featured' : ''; ?>">
                    <?php if ($service['featured_status'] === 'Yes'): ?>
                        <span class="badge-featured">Featured</span>
                    <?php endif; ?>
                    <a
                        href="<?php echo SITE_URL; ?>services/service-detail.php?id=<?php echo htmlspecialchars($service['service_id']); ?>">
                        <img src="<?php echo htmlspecialchars(getServiceImageUrl($service['image_1'])); ?>"
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
                            <img src="<?php echo htmlspecialchars(getProfilePhotoUrl($service['profile_photo'])); ?>"
                                alt="<?php echo htmlspecialchars($service['first_name']); ?>"
                                onerror="this.src='<?php echo SITE_URL; ?>uploads/profiles/default.png'">
                            <span>
                                <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                            </span>
                        </div>
                        <div class="service-card-category">
                            <?php echo htmlspecialchars($service['category']); ?>
                        </div>
                        <div class="service-card-price">Starting at
                            <?php echo formatPrice($service['price']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                        class="pagination-btn">Previous</a>
                <?php else: ?>
                    <span class="pagination-btn pagination-disabled">Previous</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination-btn pagination-active">
                            <?php echo $i; ?>
                        </span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="pagination-btn">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                        class="pagination-btn">Next</a>
                <?php else: ?>
                    <span class="pagination-btn pagination-disabled">Next</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="cart-empty">
            <div class="cart-empty-icon">🔍</div>
            <p class="cart-empty-message">No services found</p>
            <?php if (!empty($search) || !empty($category)): ?>
                <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-primary">Show All Services</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>