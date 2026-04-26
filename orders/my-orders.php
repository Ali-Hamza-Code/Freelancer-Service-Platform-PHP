<?php
/**
 * My Orders (Use Case 11)
 * Lists all orders for the current user (Client or Freelancer)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Require login
if (!isLoggedIn()) {
    setErrorMessage('Please login to view your orders.');
    redirect(SITE_URL . 'auth/login.php');
}

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$statusFilter = sanitize($_GET['status'] ?? '');

// Build query based on role
if ($userRole === 'Client') {
    $whereClause = "o.client_id = :user_id";
} else {
    $whereClause = "o.freelancer_id = :user_id";
}

$params = [':user_id' => $userId];

// Add status filter
if (!empty($statusFilter)) {
    $whereClause .= " AND o.status = :status";
    $params[':status'] = $statusFilter;
}

// Get order counts by status
$countSql = "SELECT status, COUNT(*) as count FROM orders WHERE " .
    ($userRole === 'Client' ? "client_id" : "freelancer_id") . " = :user_id GROUP BY status";
$stmt = $pdo->prepare($countSql);
$stmt->execute([':user_id' => $userId]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}
$totalOrders = array_sum($statusCounts);

// Get orders
$sql = "SELECT o.*, s.image_1, 
               c.first_name as client_first, c.last_name as client_last,
               f.first_name as freelancer_first, f.last_name as freelancer_last
        FROM orders o
        JOIN services s ON o.service_id = s.service_id
        JOIN users c ON o.client_id = c.user_id
        JOIN users f ON o.freelancer_id = f.user_id
        WHERE $whereClause
        ORDER BY o.order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'My Orders';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<h1 class="heading-primary">My Orders</h1>

<!-- Status Filter Tabs -->
<div class="filter-bar" style="flex-wrap: wrap;">
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php"
        class="btn <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-secondary'; ?>">
        All (
        <?php echo $totalOrders; ?>)
    </a>
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php?status=Pending"
        class="btn <?php echo $statusFilter === 'Pending' ? 'btn-primary' : 'btn-secondary'; ?>">
        Pending (
        <?php echo $statusCounts['Pending'] ?? 0; ?>)
    </a>
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php?status=In Progress"
        class="btn <?php echo $statusFilter === 'In Progress' ? 'btn-primary' : 'btn-secondary'; ?>">
        In Progress (
        <?php echo $statusCounts['In Progress'] ?? 0; ?>)
    </a>
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php?status=Delivered"
        class="btn <?php echo $statusFilter === 'Delivered' ? 'btn-primary' : 'btn-secondary'; ?>">
        Delivered (
        <?php echo $statusCounts['Delivered'] ?? 0; ?>)
    </a>
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php?status=Completed"
        class="btn <?php echo $statusFilter === 'Completed' ? 'btn-primary' : 'btn-secondary'; ?>">
        Completed (
        <?php echo $statusCounts['Completed'] ?? 0; ?>)
    </a>
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php?status=Revision Requested"
        class="btn <?php echo $statusFilter === 'Revision Requested' ? 'btn-primary' : 'btn-secondary'; ?>">
        Revisions (
        <?php echo $statusCounts['Revision Requested'] ?? 0; ?>)
    </a>
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php?status=Cancelled"
        class="btn <?php echo $statusFilter === 'Cancelled' ? 'btn-primary' : 'btn-secondary'; ?>">
        Cancelled (
        <?php echo $statusCounts['Cancelled'] ?? 0; ?>)
    </a>
</div>

<?php if (count($orders) > 0): ?>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Service</th>
                    <th>
                        <?php echo $userRole === 'Client' ? 'Freelancer' : 'Client'; ?>
                    </th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Order Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order):
                    $statusClass = match ($order['status']) {
                        'Pending' => 'status-pending',
                        'In Progress' => 'status-in-progress',
                        'Delivered' => 'status-completed',
                        'Completed' => 'status-completed',
                        'Revision Requested' => 'status-pending',
                        'Cancelled' => 'status-cancelled',
                        default => 'status-inactive'
                    };
                    ?>
                    <tr>
                        <td>
                            <a
                                href="<?php echo SITE_URL; ?>orders/order-details.php?id=<?php echo htmlspecialchars($order['order_id']); ?>">
                                #
                                <?php echo htmlspecialchars($order['order_id']); ?>
                            </a>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo htmlspecialchars($order['image_1'] ?: '/uploads/services/default.jpg'); ?>"
                                    alt="" class="table-thumbnail" style="width: 60px; height: 45px;"
                                    onerror="this.src='<?php echo SITE_URL; ?>uploads/services/default.jpg'">
                                <span>
                                    <?php echo htmlspecialchars(substr($order['service_title'], 0, 30)); ?>...
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if ($userRole === 'Client'): ?>
                                <?php echo htmlspecialchars($order['freelancer_first'] . ' ' . $order['freelancer_last']); ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($order['client_first'] . ' ' . $order['client_last']); ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-bold">
                            <?php echo formatPrice($order['total_amount']); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo formatDate($order['expected_delivery']); ?>
                        </td>
                        <td>
                            <?php echo formatDate($order['order_date']); ?>
                        </td>
                        <td>
                            <a href="<?php echo SITE_URL; ?>orders/order-details.php?id=<?php echo htmlspecialchars($order['order_id']); ?>"
                                class="btn btn-primary btn-sm">
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="cart-empty">
        <div class="cart-empty-icon">📋</div>
        <p class="cart-empty-message">No orders found</p>
        <?php if (!empty($statusFilter)): ?>
            <a href="<?php echo SITE_URL; ?>orders/my-orders.php" class="btn btn-secondary">Show All Orders</a>
        <?php else: ?>
            <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-primary">Browse Services</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>