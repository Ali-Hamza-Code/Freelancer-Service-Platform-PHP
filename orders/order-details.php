<?php
/**
 * Order Details (Use Case 11)
 * View order details and perform role-specific actions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Require login
if (!isLoggedIn()) {
    setErrorMessage('Please login to view order details.');
    redirect(SITE_URL . 'auth/login.php');
}

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$orderId = sanitize($_GET['id'] ?? '');

if (empty($orderId)) {
    setErrorMessage('Order not found.');
    redirect(SITE_URL . 'orders/my-orders.php');
}

// Fetch order with all related info
$stmt = $pdo->prepare("
    SELECT o.*, s.image_1, s.category, s.subcategory,
           c.first_name as client_first, c.last_name as client_last, c.email as client_email,
           f.first_name as freelancer_first, f.last_name as freelancer_last, f.email as freelancer_email
    FROM orders o
    JOIN services s ON o.service_id = s.service_id
    JOIN users c ON o.client_id = c.user_id
    JOIN users f ON o.freelancer_id = f.user_id
    WHERE o.order_id = :order_id
");
$stmt->execute([':order_id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    setErrorMessage('Order not found.');
    redirect(SITE_URL . 'orders/my-orders.php');
}

// Check authorization
$isClient = $userId === $order['client_id'];
$isFreelancer = $userId === $order['freelancer_id'];

if (!$isClient && !$isFreelancer) {
    setErrorMessage('You do not have permission to view this order.');
    redirect(SITE_URL . 'orders/my-orders.php');
}

// Get file attachments
$stmt = $pdo->prepare("SELECT * FROM file_attachments WHERE order_id = :order_id ORDER BY upload_timestamp DESC");
$stmt->execute([':order_id' => $orderId]);
$files = $stmt->fetchAll();

// Fix paths for display (handle legacy paths missing SITE_URL)
foreach ($files as &$file) {
    $file['file_path'] = getImageUrl($file['file_path']);
}
unset($file);

// Separate files by type
$requirementFiles = array_filter($files, fn($f) => $f['file_type'] === 'requirement');
$deliverableFiles = array_filter($files, fn($f) => $f['file_type'] === 'deliverable');
$revisionFiles = array_filter($files, fn($f) => $f['file_type'] === 'revision');

// Get revision history
$stmt = $pdo->prepare("SELECT * FROM revision_requests WHERE order_id = :order_id ORDER BY request_date DESC");
$stmt->execute([':order_id' => $orderId]);
$revisions = $stmt->fetchAll();

// Count revision stats
$revisionStats = [
    'total' => count($revisions),
    'accepted' => count(array_filter($revisions, fn($r) => $r['request_status'] === 'Accepted')),
    'rejected' => count(array_filter($revisions, fn($r) => $r['request_status'] === 'Rejected')),
    'pending' => count(array_filter($revisions, fn($r) => $r['request_status'] === 'Pending'))
];

$errors = [];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    // Freelancer Actions
    if ($isFreelancer) {
        // Accept Order (Pending -> In Progress)
        if ($action === 'accept' && $order['status'] === 'Pending') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'In Progress' WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $orderId]);
            setSuccessMessage('Order accepted! You can now start working.');
            redirect(SITE_URL . 'orders/order-details.php?id=' . $orderId);
        }

        // Reject Order (Pending -> Cancelled)
        if ($action === 'reject' && $order['status'] === 'Pending') {
            $reason = sanitize($_POST['reason'] ?? 'Order rejected by freelancer');
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled', cancellation_reason = :reason WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $orderId, ':reason' => $reason]);
            setSuccessMessage('Order has been rejected.');
            redirect(SITE_URL . 'orders/order-details.php?id=' . $orderId);
        }

        // Upload Delivery
        if ($action === 'upload_delivery' && in_array($order['status'], ['In Progress', 'Revision Requested'])) {
            $deliveryNotes = sanitize($_POST['delivery_notes'] ?? '');

            if (empty($deliveryNotes)) {
                $errors['delivery_notes'] = 'Delivery notes are required';
            }

            // Handle file upload
            if (isset($_FILES['delivery_file']) && $_FILES['delivery_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['delivery_file'];

                if ($file['size'] > MAX_DELIVERY_FILE_SIZE) {
                    $errors['delivery_file'] = 'File size must not exceed 50MB';
                } else {
                    $uploadDir = ORDER_UPLOAD_PATH . $orderId . '/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $filename = 'delivery_' . time() . '_' . basename($file['name']);
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        // Insert file attachment
                        $stmt = $pdo->prepare("
                            INSERT INTO file_attachments (order_id, file_path, original_filename, file_size, file_type)
                            VALUES (:order_id, :file_path, :filename, :file_size, 'deliverable')
                        ");
                        $stmt->execute([
                            ':order_id' => $orderId,
                            ':file_path' => SITE_URL . '/uploads/orders/' . $orderId . '/' . $filename,
                            ':filename' => $file['name'],
                            ':file_size' => $file['size']
                        ]);
                    }
                }
            }

            if (empty($errors)) {
                // Update order status
                $stmt = $pdo->prepare("
                    UPDATE orders SET status = 'Delivered', deliverable_notes = :notes, actual_delivery_date = NOW()
                    WHERE order_id = :order_id
                ");
                $stmt->execute([':notes' => $deliveryNotes, ':order_id' => $orderId]);

                // If responding to revision, update revision status
                if ($order['status'] === 'Revision Requested') {
                    $stmt = $pdo->prepare("
                        UPDATE revision_requests SET request_status = 'Accepted', response_date = NOW() 
                        WHERE order_id = :order_id AND request_status = 'Pending'
                    ");
                    $stmt->execute([':order_id' => $orderId]);
                }

                setSuccessMessage('Delivery uploaded successfully!');
                redirect(SITE_URL . 'orders/order-details.php?id=' . $orderId);
            }
        }

        // Respond to Revision (Accept/Reject)
        if ($action === 'respond_revision' && $order['status'] === 'Revision Requested') {
            $revisionId = (int) ($_POST['revision_id'] ?? 0);
            $response = sanitize($_POST['response'] ?? '');
            $responseText = sanitize($_POST['response_text'] ?? '');

            if (!empty($revisionId) && in_array($response, ['Accepted', 'Rejected'])) {
                $stmt = $pdo->prepare("
                    UPDATE revision_requests 
                    SET request_status = :status, response_date = NOW(), freelancer_response = :response_text
                    WHERE revision_id = :revision_id
                ");
                $stmt->execute([':status' => $response, ':response_text' => $responseText, ':revision_id' => $revisionId]);

                if ($response === 'Rejected') {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'Delivered' WHERE order_id = :order_id");
                    $stmt->execute([':order_id' => $orderId]);
                }

                setSuccessMessage('Revision response submitted.');
                redirect(SITE_URL . 'orders/order-details.php?id=' . $orderId);
            }
        }
    }

    // Client Actions
    if ($isClient) {
        // Cancel Order (only if Pending)
        if ($action === 'cancel' && $order['status'] === 'Pending') {
            $reason = sanitize($_POST['reason'] ?? 'Cancelled by client');
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled', cancellation_reason = :reason WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $orderId, ':reason' => $reason]);
            setSuccessMessage('Order has been cancelled.');
            redirect(SITE_URL . 'orders/order-details.php?id=' . $orderId);
        }

        // Mark as Completed (only if Delivered)
        if ($action === 'complete' && $order['status'] === 'Delivered') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Completed', completion_date = NOW() WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $orderId]);
            setSuccessMessage('Order marked as completed!');
            redirect(SITE_URL . 'orders/order-details.php?id=' . $orderId);
        }

        // Request Revision (only if Delivered and revisions remaining)
        if ($action === 'request_revision' && $order['status'] === 'Delivered') {
            $revisionNotes = sanitize($_POST['revision_notes'] ?? '');

            if (empty($revisionNotes)) {
                $errors['revision_notes'] = 'Please describe the changes you need';
            }

            // Check revisions remaining
            $usedRevisions = count($revisions);
            if ($order['revisions_included'] < 999 && $usedRevisions >= $order['revisions_included']) {
                $errors['revision_notes'] = 'No revisions remaining for this order';
            }

            if (empty($errors)) {
                // Handle revision file upload
                $revisionFilePath = null;
                if (isset($_FILES['revision_file']) && $_FILES['revision_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['revision_file'];
                    $uploadDir = ORDER_UPLOAD_PATH . $orderId . '/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'revision_' . time() . '_' . basename($file['name']);
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $revisionFilePath = SITE_URL . 'uploads/orders/' . $orderId . '/' . $filename;

                        $stmt = $pdo->prepare("
                            INSERT INTO file_attachments (order_id, file_path, original_filename, file_size, file_type)
                            VALUES (:order_id, :file_path, :filename, :file_size, 'revision')
                        ");
                        $stmt->execute([
                            ':order_id' => $orderId,
                            ':file_path' => $revisionFilePath,
                            ':filename' => $file['name'],
                            ':file_size' => $file['size']
                        ]);
                    }
                }

                // Insert revision request
                $stmt = $pdo->prepare("
                    INSERT INTO revision_requests (order_id, revision_notes, revision_file)
                    VALUES (:order_id, :notes, :file)
                ");
                $stmt->execute([':order_id' => $orderId, ':notes' => $revisionNotes, ':file' => $revisionFilePath]);

                // Update order status
                $stmt = $pdo->prepare("UPDATE orders SET status = 'Revision Requested' WHERE order_id = :order_id");
                $stmt->execute([':order_id' => $orderId]);

                setSuccessMessage('Revision request submitted.');
                redirect(SITE_URL . 'orders/order-details.php?id=' . $orderId);
            }
        }
    }
}

// Refresh order data
$stmt = $pdo->prepare("
    SELECT o.*, s.image_1, s.category, s.subcategory,
           c.first_name as client_first, c.last_name as client_last, c.email as client_email,
           f.first_name as freelancer_first, f.last_name as freelancer_last, f.email as freelancer_email
    FROM orders o
    JOIN services s ON o.service_id = s.service_id
    JOIN users c ON o.client_id = c.user_id
    JOIN users f ON o.freelancer_id = f.user_id
    WHERE o.order_id = :order_id
");
$stmt->execute([':order_id' => $orderId]);
$order = $stmt->fetch();

// Status class
$statusClass = match ($order['status']) {
    'Pending' => 'status-pending',
    'In Progress' => 'status-in-progress',
    'Delivered' => 'status-completed',
    'Completed' => 'status-completed',
    'Revision Requested' => 'status-pending',
    'Cancelled' => 'status-cancelled',
    default => 'status-inactive'
};

$pageTitle = 'Order #' . $orderId;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="mb-md">
    <a href="<?php echo SITE_URL; ?>orders/my-orders.php" class="btn btn-secondary btn-sm">← Back to My Orders</a>
</div>

<div class="two-column-layout">
    <!-- Left Column: Order Details -->
    <div class="column-70">
        <!-- Order Header -->
        <div class="card mb-lg">
            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1 class="heading-primary mb-sm">Order #
                        <?php echo htmlspecialchars($orderId); ?>
                    </h1>
                    <p class="text-muted">Placed on
                        <?php echo formatDate($order['order_date']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="badge badge-status <?php echo $statusClass; ?>" style="font-size: 16px;">
                        <?php echo $order['status']; ?>
                    </span>
                    <p class="mt-sm"><strong>Due:</strong>
                        <?php echo formatDate($order['expected_delivery']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Service Info -->
        <div class="card mb-lg">
            <h2 class="heading-secondary">Service Details</h2>
            <div style="display: flex; gap: 20px; align-items: start;">
                <img src="<?php echo htmlspecialchars(getServiceImageUrl($order['image_1'])); ?>"
                    alt="<?php echo htmlspecialchars($order['service_title']); ?>"
                    style="width: 150px; height: 112px; object-fit: cover; border-radius: 8px;"
                    onerror="this.src='<?php echo SITE_URL; ?>uploads/services/default.jpg'">
                <div>
                    <h3>
                        <?php echo htmlspecialchars($order['service_title']); ?>
                    </h3>
                    <p class="text-muted">
                        <?php echo htmlspecialchars($order['category']); ?> >
                        <?php echo htmlspecialchars($order['subcategory']); ?>
                    </p>
                    <p><strong>Delivery Time:</strong>
                        <?php echo $order['delivery_time']; ?> days
                    </p>
                    <p><strong>Revisions:</strong>
                        <?php echo $order['revisions_included'] >= 999 ? 'Unlimited' : $order['revisions_included']; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Requirements -->
        <div class="card mb-lg">
            <h2 class="heading-secondary">Requirements</h2>
            <p>
                <?php echo nl2br(htmlspecialchars($order['requirements'])); ?>
            </p>

            <?php if ($order['special_instructions']): ?>
                <h3 class="heading-tertiary mt-lg">Special Instructions</h3>
                <p>
                    <?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?>
                </p>
            <?php endif; ?>

            <?php if (count($requirementFiles) > 0): ?>
                <h3 class="heading-tertiary mt-lg">Attached Files</h3>
                <?php foreach ($requirementFiles as $file): ?>
                    <div class="file-item">
                        <div class="file-icon file-icon-default"></div>
                        <div class="file-info">
                            <div class="file-name">
                                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($file['original_filename']); ?>
                                </a>
                            </div>
                            <div class="file-size">
                                <?php echo formatFileSize($file['file_size']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Deliverables (if delivered) -->
        <?php if (in_array($order['status'], ['Delivered', 'Completed', 'Revision Requested']) && ($order['deliverable_notes'] || count($deliverableFiles) > 0)): ?>
            <div class="card mb-lg">
                <h2 class="heading-secondary">Deliverables</h2>
                <?php if ($order['deliverable_notes']): ?>
                    <p>
                        <?php echo nl2br(htmlspecialchars($order['deliverable_notes'])); ?>
                    </p>
                <?php endif; ?>

                <?php if (count($deliverableFiles) > 0): ?>
                    <h3 class="heading-tertiary mt-lg">Delivered Files</h3>
                    <?php foreach ($deliverableFiles as $file): ?>
                        <div class="file-item">
                            <div class="file-icon file-icon-default"></div>
                            <div class="file-info">
                                <div class="file-name">
                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($file['original_filename']); ?>
                                    </a>
                                </div>
                                <div class="file-size">
                                    <?php echo formatFileSize($file['file_size']); ?>
                                </div>
                                <div class="file-date">
                                    <?php echo formatDate($file['upload_timestamp']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Revision History -->
        <?php if (count($revisions) > 0): ?>
            <div class="revision-history">
                <h2 class="heading-secondary">Revision History</h2>

                <div class="revision-summary">
                    <div class="revision-stat">
                        <div class="revision-stat-value">
                            <?php echo $revisionStats['total']; ?>
                        </div>
                        <div class="revision-stat-label">Total</div>
                    </div>
                    <div class="revision-stat">
                        <div class="revision-stat-value accepted">
                            <?php echo $revisionStats['accepted']; ?>
                        </div>
                        <div class="revision-stat-label">Accepted</div>
                    </div>
                    <div class="revision-stat">
                        <div class="revision-stat-value rejected">
                            <?php echo $revisionStats['rejected']; ?>
                        </div>
                        <div class="revision-stat-label">Rejected</div>
                    </div>
                    <div class="revision-stat">
                        <div class="revision-stat-value pending">
                            <?php echo $revisionStats['pending']; ?>
                        </div>
                        <div class="revision-stat-label">Pending</div>
                    </div>
                </div>

                <?php foreach ($revisions as $revision): ?>
                    <div class="card mb-md" style="background-color: var(--bg-light);">
                        <div style="display: flex; justify-content: space-between;">
                            <span class="text-muted">Requested:
                                <?php echo formatDate($revision['request_date']); ?>
                            </span>
                            <span class="badge <?php echo match ($revision['request_status']) {
                                'Accepted' => 'badge-success',
                                'Rejected' => 'status-cancelled',
                                default => 'badge-warning'
                            }; ?>">
                                <?php echo $revision['request_status']; ?>
                            </span>
                        </div>
                        <p class="mt-sm">
                            <?php echo nl2br(htmlspecialchars($revision['revision_notes'])); ?>
                        </p>

                        <?php if ($revision['freelancer_response']): ?>
                            <div class="mt-md" style="border-top: 1px solid var(--border-gray); padding-top: 10px;">
                                <strong>Freelancer Response:</strong>
                                <p>
                                    <?php echo nl2br(htmlspecialchars($revision['freelancer_response'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if ($isFreelancer && $revision['request_status'] === 'Pending'): ?>
                            <form method="POST" action="" class="mt-md">
                                <input type="hidden" name="action" value="respond_revision">
                                <input type="hidden" name="revision_id" value="<?php echo $revision['revision_id']; ?>">
                                <div class="form-group">
                                    <label class="form-label">Your Response</label>
                                    <textarea name="response_text" class="form-textarea"
                                        placeholder="Optional response message..."></textarea>
                                </div>
                                <div class="btn-group">
                                    <button type="submit" name="response" value="Accepted" class="btn btn-success btn-sm">Accept &
                                        Work on Revision</button>
                                    <button type="submit" name="response" value="Rejected" class="btn btn-danger btn-sm">Reject
                                        Request</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Summary and Actions -->
    <div class="column-30">
        <!-- Order Summary -->
        <div class="order-summary mb-lg">
            <h3 class="order-summary-title">Order Summary</h3>

            <div class="order-summary-row">
                <span>Service Price</span>
                <span>
                    <?php echo formatPrice($order['price']); ?>
                </span>
            </div>
            <div class="order-summary-row">
                <span>Service Fee</span>
                <span>
                    <?php echo formatPrice($order['service_fee']); ?>
                </span>
            </div>
            <div class="order-summary-row order-summary-total">
                <span>Total</span>
                <span class="amount">
                    <?php echo formatPrice($order['total_amount']); ?>
                </span>
            </div>

            <div class="mt-lg text-muted" style="font-size: 13px;">
                <p><strong>Payment:</strong>
                    <?php echo htmlspecialchars($order['payment_method']); ?>
                </p>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="card mb-lg">
            <h3 class="heading-tertiary">
                <?php echo $isClient ? 'Freelancer' : 'Client'; ?>
            </h3>
            <?php if ($isClient): ?>
                <p><strong>
                        <?php echo htmlspecialchars($order['freelancer_first'] . ' ' . $order['freelancer_last']); ?>
                    </strong></p>
                <p class="text-muted">
                    <?php echo htmlspecialchars($order['freelancer_email']); ?>
                </p>
            <?php else: ?>
                <p><strong>
                        <?php echo htmlspecialchars($order['client_first'] . ' ' . $order['client_last']); ?>
                    </strong></p>
                <p class="text-muted">
                    <?php echo htmlspecialchars($order['client_email']); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="card">
            <h3 class="heading-tertiary">Actions</h3>

            <?php if (isset($errors['delivery_notes']) || isset($errors['delivery_file']) || isset($errors['revision_notes'])): ?>
                <div class="message message-error mb-md">
                    <?php echo implode('<br>', array_filter([$errors['delivery_notes'] ?? '', $errors['delivery_file'] ?? '', $errors['revision_notes'] ?? ''])); ?>
                </div>
            <?php endif; ?>

            <?php if ($isFreelancer): ?>
                <!-- Freelancer Actions -->
                <?php if ($order['status'] === 'Pending'): ?>
                    <form method="POST" action="" class="mb-md">
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="btn btn-success btn-full">Accept Order</button>
                    </form>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="reject">
                        <div class="form-group">
                            <textarea name="reason" class="form-textarea"
                                placeholder="Reason for rejection (optional)"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-full">Reject Order</button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($order['status'], ['In Progress', 'Revision Requested'])): ?>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_delivery">
                        <div class="form-group">
                            <label class="form-label">Delivery Notes <span class="required">*</span></label>
                            <textarea name="delivery_notes" class="form-textarea" placeholder="Describe what's included..."
                                required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Delivery File (optional)</label>
                            <input type="file" name="delivery_file" class="form-input">
                            <div class="form-hint">Max 50MB</div>
                        </div>
                        <button type="submit" class="btn btn-success btn-full">Upload Delivery</button>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <!-- Client Actions -->
                <?php if ($order['status'] === 'Pending'): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="cancel">
                        <div class="form-group">
                            <textarea name="reason" class="form-textarea"
                                placeholder="Reason for cancellation (optional)"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-full">Cancel Order</button>
                    </form>
                <?php endif; ?>

                <?php if ($order['status'] === 'Delivered'): ?>
                    <form method="POST" action="" class="mb-lg">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn btn-success btn-full">Mark as Completed</button>
                    </form>

                    <?php
                    $revisionsUsed = count($revisions);
                    $revisionsRemaining = $order['revisions_included'] >= 999 ? 'Unlimited' : ($order['revisions_included'] - $revisionsUsed);
                    $canRequestRevision = $order['revisions_included'] >= 999 || $revisionsUsed < $order['revisions_included'];
                    ?>

                    <?php if ($canRequestRevision): ?>
                        <div class="mt-lg">
                            <p class="text-muted mb-sm">Revisions remaining:
                                <?php echo $revisionsRemaining; ?>
                            </p>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="request_revision">
                                <div class="form-group">
                                    <label class="form-label">Revision Request <span class="required">*</span></label>
                                    <textarea name="revision_notes" class="form-textarea"
                                        placeholder="Describe the changes you need..." required></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Attach Reference (optional)</label>
                                    <input type="file" name="revision_file" class="form-input">
                                </div>
                                <button type="submit" class="btn btn-primary btn-full">Request Revision</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($order['status'] === 'Completed'): ?>
                <div class="alert alert-success mt-md">
                    <strong>Order Completed!</strong><br>
                    Completed on
                    <?php echo formatDate($order['completion_date']); ?>
                </div>
            <?php endif; ?>

            <?php if ($order['status'] === 'Cancelled'): ?>
                <div class="alert alert-danger mt-md">
                    <strong>Order Cancelled</strong>
                    <?php if ($order['cancellation_reason']): ?>
                        <br>
                        <?php echo htmlspecialchars($order['cancellation_reason']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>