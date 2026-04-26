<?php
/**
 * Checkout (Use Case 10)
 * 3-step checkout process: Requirements, Payment, Review
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';
require_once __DIR__ . '/../Service.php';

// Require login
if (!isLoggedIn()) {
    setErrorMessage('Please login to checkout.');
    redirect(SITE_URL . 'auth/login.php');
}

// Block freelancers from checkout
if (isFreelancer()) {
    setErrorMessage('Freelancers cannot purchase services.');
    redirect(SITE_URL . 'services/browse-services.php');
}

// Check if cart has items
$cartItems = $_SESSION['cart'] ?? [];
if (empty($cartItems)) {
    setErrorMessage('Your cart is empty.');
    redirect(SITE_URL . 'cart/cart.php');
}

$errors = [];
$step = (int) ($_GET['step'] ?? 1);
$step = max(1, min(3, $step));

// Initialize checkout session data
if (!isset($_SESSION['checkout'])) {
    $_SESSION['checkout'] = [
        'requirements' => [],
        'payment' => [],
        'files' => []
    ];
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1: Service Requirements
    if ($step === 1) {
        $requirements = [];
        $files = [];
        $valid = true;

        foreach ($cartItems as $index => $item) {
            $serviceId = $item->getServiceId();
            $reqText = sanitize($_POST["requirements_$serviceId"] ?? '');
            $instructions = sanitize($_POST["instructions_$serviceId"] ?? '');
            $deadline = sanitize($_POST["deadline_$serviceId"] ?? '');

            if (empty($reqText)) {
                $errors["requirements_$serviceId"] = 'Requirements are required for ' . $item->getTitle();
                $valid = false;
            } elseif (strlen($reqText) < 20) {
                $errors["requirements_$serviceId"] = 'Please provide more detail (at least 20 characters)';
                $valid = false;
            }

            $requirements[$serviceId] = [
                'text' => $reqText,
                'instructions' => $instructions,
                'deadline' => $deadline
            ];

            // Handle file upload
            $fileKey = "file_$serviceId";
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileKey];

                if ($file['size'] > MAX_ORDER_FILE_SIZE) {
                    $errors[$fileKey] = 'File size must not exceed 10MB';
                    $valid = false;
                } else {
                    // Store temporarily
                    $tempDir = sys_get_temp_dir() . '/checkout_temp_' . getCurrentUserId() . '/';
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }
                    $tempPath = $tempDir . basename($file['name']);
                    move_uploaded_file($file['tmp_name'], $tempPath);

                    $files[$serviceId] = [
                        'path' => $tempPath,
                        'name' => $file['name'],
                        'size' => $file['size']
                    ];
                }
            }
        }

        if ($valid) {
            $_SESSION['checkout']['requirements'] = $requirements;
            $_SESSION['checkout']['files'] = $files;
            redirect(SITE_URL . 'checkout/checkout.php?step=2');
        }
    }

    // Step 2: Payment Information
    if ($step === 2) {
        $paymentMethod = sanitize($_POST['payment_method'] ?? '');
        $cardNumber = sanitize($_POST['card_number'] ?? '');
        $expiryDate = sanitize($_POST['expiry_date'] ?? '');
        $cvv = sanitize($_POST['cvv'] ?? '');
        $cardName = sanitize($_POST['card_name'] ?? '');

        if (empty($paymentMethod)) {
            $errors['payment_method'] = 'Please select a payment method';
        }

        if ($paymentMethod === 'credit_card') {
            if (empty($cardNumber) || !preg_match('/^\d{16}$/', str_replace(' ', '', $cardNumber))) {
                $errors['card_number'] = 'Please enter a valid 16-digit card number';
            }
            if (empty($expiryDate) || !preg_match('/^\d{2}\/\d{2}$/', $expiryDate)) {
                $errors['expiry_date'] = 'Please enter a valid expiry date (MM/YY)';
            }
            if (empty($cvv) || !preg_match('/^\d{3,4}$/', $cvv)) {
                $errors['cvv'] = 'Please enter a valid CVV';
            }
            if (empty($cardName)) {
                $errors['card_name'] = 'Please enter the cardholder name';
            }
        }

        if (empty($errors)) {
            $_SESSION['checkout']['payment'] = [
                'method' => $paymentMethod,
                'card_last_four' => substr(str_replace(' ', '', $cardNumber), -4),
                'card_name' => $cardName
            ];
            redirect(SITE_URL . 'checkout/checkout.php?step=3');
        }
    }

    // Step 3: Place Order
    if ($step === 3 && isset($_POST['place_order'])) {
        $requirements = $_SESSION['checkout']['requirements'];
        $payment = $_SESSION['checkout']['payment'];
        $files = $_SESSION['checkout']['files'];

        $createdOrders = [];

        try {
            $pdo->beginTransaction();

            foreach ($cartItems as $item) {
                $serviceId = $item->getServiceId();

                // Generate order ID
                do {
                    $orderId = generateUniqueId();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_id = :order_id");
                    $stmt->execute([':order_id' => $orderId]);
                } while ($stmt->fetchColumn() > 0);

                // Calculate prices
                $price = $item->getPrice();
                $serviceFee = $item->calculateServiceFee();
                $totalAmount = $price + $serviceFee;
                $expectedDelivery = date('Y-m-d', strtotime('+' . $item->getDeliveryTime() . ' days'));

                // Get requirement data
                $reqData = $requirements[$serviceId] ?? [];
                $reqText = $reqData['text'] ?? '';
                $instructions = $reqData['instructions'] ?? '';
                $preferredDeadline = !empty($reqData['deadline']) ? $reqData['deadline'] : null;

                // Insert order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (order_id, client_id, freelancer_id, service_id, service_title, 
                                       price, service_fee, total_amount, delivery_time, revisions_included,
                                       requirements, special_instructions, preferred_deadline,
                                       status, payment_method, expected_delivery)
                    VALUES (:order_id, :client_id, :freelancer_id, :service_id, :service_title,
                            :price, :service_fee, :total_amount, :delivery_time, :revisions,
                            :requirements, :instructions, :deadline,
                            'Pending', :payment_method, :expected_delivery)
                ");

                $stmt->execute([
                    ':order_id' => $orderId,
                    ':client_id' => getCurrentUserId(),
                    ':freelancer_id' => $item->getFreelancerId(),
                    ':service_id' => $serviceId,
                    ':service_title' => $item->getTitle(),
                    ':price' => $price,
                    ':service_fee' => $serviceFee,
                    ':total_amount' => $totalAmount,
                    ':delivery_time' => $item->getDeliveryTime(),
                    ':revisions' => $item->getRevisionsIncluded(),
                    ':requirements' => $reqText,
                    ':instructions' => $instructions,
                    ':deadline' => $preferredDeadline,
                    ':payment_method' => $payment['method'] === 'credit_card' ? 'Credit Card' : 'PayPal',
                    ':expected_delivery' => $expectedDelivery
                ]);

                // Move and save uploaded file if exists
                if (isset($files[$serviceId])) {
                    $fileData = $files[$serviceId];
                    $uploadDir = ORDER_UPLOAD_PATH . $orderId . '/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $newPath = $uploadDir . $fileData['name'];
                    if (file_exists($fileData['path'])) {
                        rename($fileData['path'], $newPath);

                        // Insert file attachment
                        $stmt = $pdo->prepare("
                            INSERT INTO file_attachments (order_id, file_path, original_filename, file_size, file_type)
                            VALUES (:order_id, :file_path, :filename, :file_size, 'requirement')
                        ");
                        $stmt->execute([
                            ':order_id' => $orderId,
                            ':file_path' => SITE_URL . 'uploads/orders/' . $orderId . '/' . $fileData['name'],
                            ':filename' => $fileData['name'],
                            ':file_size' => $fileData['size']
                        ]);
                    }
                }

                $createdOrders[] = [
                    'order_id' => $orderId,
                    'title' => $item->getTitle(),
                    'price' => $totalAmount
                ];
            }

            $pdo->commit();

            // Clear cart and checkout session
            $_SESSION['cart'] = [];
            unset($_SESSION['checkout']);

            // Clean up temp directory
            $tempDir = sys_get_temp_dir() . '/checkout_temp_' . getCurrentUserId() . '/';
            if (is_dir($tempDir)) {
                array_map('unlink', glob("$tempDir*"));
                rmdir($tempDir);
            }

            // Store created orders for success page
            $_SESSION['completed_orders'] = $createdOrders;
            redirect(SITE_URL . 'checkout/order-success.php');

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Failed to place order. Please try again.';
            error_log("Order creation error: " . $e->getMessage());
        }
    }
}

// Calculate totals
$subtotal = 0;
$totalFee = 0;
foreach ($cartItems as $item) {
    $subtotal += $item->getPrice();
    $totalFee += $item->calculateServiceFee();
}
$grandTotal = $subtotal + $totalFee;

$pageTitle = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<h1 class="heading-primary">Checkout</h1>

<!-- Progress Indicator -->
<div class="step-indicator">
    <div class="step-item <?php echo $step >= 1 ? ($step > 1 ? 'step-completed' : 'step-active') : 'step-inactive'; ?>">
        <?php if ($step > 1): ?>
            <a href="<?php echo SITE_URL; ?>checkout/checkout.php?step=1" class="step-icon">✓</a>
        <?php else: ?>
            <span class="step-icon">1</span>
        <?php endif; ?>
        <span class="step-label">Requirements</span>
    </div>
    <div class="step-connector"></div>
    <div class="step-item <?php echo $step >= 2 ? ($step > 2 ? 'step-completed' : 'step-active') : 'step-inactive'; ?>">
        <?php if ($step > 2): ?>
            <a href="<?php echo SITE_URL; ?>checkout/checkout.php?step=2" class="step-icon">✓</a>
        <?php else: ?>
            <span class="step-icon">2</span>
        <?php endif; ?>
        <span class="step-label">Payment</span>
    </div>
    <div class="step-connector"></div>
    <div class="step-item <?php echo $step === 3 ? 'step-active' : 'step-inactive'; ?>">
        <span class="step-icon">3</span>
        <span class="step-label">Review & Confirm</span>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="message message-error">
        <?php echo $errors['general']; ?>
    </div>
<?php endif; ?>

<div class="two-column-layout">
    <div class="column-70">
        <?php if ($step === 1): ?>
            <!-- Step 1: Service Requirements -->
            <div class="card">
                <h2 class="heading-secondary">Step 1: Service Requirements</h2>
                <p class="text-muted mb-lg">Provide the necessary information for each service</p>

                <form method="POST" action="" enctype="multipart/form-data" novalidate>
                    <?php foreach ($cartItems as $index => $item):
                        $serviceId = $item->getServiceId();
                        $savedReq = $_SESSION['checkout']['requirements'][$serviceId] ?? [];
                        ?>
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <?php echo htmlspecialchars($item->getTitle()); ?>
                            </h3>
                            <p class="text-muted">by
                                <?php echo htmlspecialchars($item->getFreelancerName()); ?>
                            </p>

                            <div class="form-group">
                                <label class="form-label" for="requirements_<?php echo $serviceId; ?>">
                                    Project Requirements <span class="required">*</span>
                                </label>
                                <textarea id="requirements_<?php echo $serviceId; ?>"
                                    name="requirements_<?php echo $serviceId; ?>"
                                    class="form-textarea <?php echo isset($errors["requirements_$serviceId"]) ? 'error' : ''; ?>"
                                    placeholder="Describe what you need..."><?php echo htmlspecialchars($savedReq['text'] ?? ''); ?></textarea>
                                <?php if (isset($errors["requirements_$serviceId"])): ?>
                                    <div class="form-error">
                                        <?php echo $errors["requirements_$serviceId"]; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="instructions_<?php echo $serviceId; ?>">
                                    Special Instructions (optional)
                                </label>
                                <textarea id="instructions_<?php echo $serviceId; ?>"
                                    name="instructions_<?php echo $serviceId; ?>" class="form-textarea"
                                    style="min-height: 80px;"
                                    placeholder="Any specific instructions..."><?php echo htmlspecialchars($savedReq['instructions'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="deadline_<?php echo $serviceId; ?>">
                                        Preferred Deadline (optional)
                                    </label>
                                    <input type="date" id="deadline_<?php echo $serviceId; ?>"
                                        name="deadline_<?php echo $serviceId; ?>" class="form-input"
                                        min="<?php echo date('Y-m-d'); ?>"
                                        value="<?php echo htmlspecialchars($savedReq['deadline'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="file_<?php echo $serviceId; ?>">
                                        Attach File (optional)
                                    </label>
                                    <input type="file" id="file_<?php echo $serviceId; ?>" name="file_<?php echo $serviceId; ?>"
                                        class="form-input">
                                    <div class="form-hint">Max 10MB</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-actions">
                        <a href="<?php echo SITE_URL; ?>cart/cart.php" class="btn btn-secondary">Back to Cart</a>
                        <button type="submit" class="btn btn-primary">Continue to Payment</button>
                    </div>
                </form>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- Step 2: Payment Information -->
            <div class="card">
                <h2 class="heading-secondary">Step 2: Payment Information</h2>
                <p class="text-muted mb-lg">This is a simulated payment - no actual charges will be made</p>

                <form method="POST" action="" novalidate>
                    <div class="form-section">
                        <h3 class="form-section-title">Select Payment Method</h3>

                        <div class="form-group">
                            <div class="form-radio">
                                <input type="radio" name="payment_method" value="credit_card" id="pm_cc" checked>
                                <label for="pm_cc">💳 Credit / Debit Card</label>
                            </div>
                            <div class="form-radio">
                                <input type="radio" name="payment_method" value="paypal" id="pm_pp">
                                <label for="pm_pp">🅿️ PayPal</label>
                            </div>
                            <?php if (isset($errors['payment_method'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['payment_method']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-section" id="credit_card_fields">
                        <h3 class="form-section-title">Card Details</h3>

                        <div class="form-group">
                            <label class="form-label" for="card_name">Cardholder Name <span
                                    class="required">*</span></label>
                            <input type="text" id="card_name" name="card_name"
                                class="form-input <?php echo isset($errors['card_name']) ? 'error' : ''; ?>"
                                placeholder="John Doe">
                            <?php if (isset($errors['card_name'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['card_name']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="card_number">Card Number <span class="required">*</span></label>
                            <input type="text" id="card_number" name="card_number"
                                class="form-input <?php echo isset($errors['card_number']) ? 'error' : ''; ?>"
                                placeholder="1234 5678 9012 3456" maxlength="19">
                            <?php if (isset($errors['card_number'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['card_number']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="expiry_date">Expiry Date <span
                                        class="required">*</span></label>
                                <input type="text" id="expiry_date" name="expiry_date"
                                    class="form-input <?php echo isset($errors['expiry_date']) ? 'error' : ''; ?>"
                                    placeholder="MM/YY" maxlength="5">
                                <?php if (isset($errors['expiry_date'])): ?>
                                    <div class="form-error">
                                        <?php echo $errors['expiry_date']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="cvv">CVV <span class="required">*</span></label>
                                <input type="text" id="cvv" name="cvv"
                                    class="form-input <?php echo isset($errors['cvv']) ? 'error' : ''; ?>" placeholder="123"
                                    maxlength="4">
                                <?php if (isset($errors['cvv'])): ?>
                                    <div class="form-error">
                                        <?php echo $errors['cvv']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> This is a simulated payment for testing purposes. No actual payment will be
                        processed.
                    </div>

                    <div class="form-actions">
                        <a href="<?php echo SITE_URL; ?>checkout/checkout.php?step=1" class="btn btn-secondary">Back</a>
                        <button type="submit" class="btn btn-primary">Continue to Review</button>
                    </div>
                </form>
            </div>

        <?php elseif ($step === 3): ?>
            <!-- Step 3: Review and Confirm -->
            <div class="card">
                <h2 class="heading-secondary">Step 3: Review Your Order</h2>

                <!-- Order Summary -->
                <div class="form-section">
                    <h3 class="form-section-title">Orders to be Created</h3>
                    <?php foreach ($cartItems as $item):
                        $serviceId = $item->getServiceId();
                        $reqData = $_SESSION['checkout']['requirements'][$serviceId] ?? [];
                        ?>
                        <div class="card mb-md" style="background-color: var(--bg-light);">
                            <h4>
                                <?php echo htmlspecialchars($item->getTitle()); ?>
                            </h4>
                            <p class="text-muted">Freelancer:
                                <?php echo htmlspecialchars($item->getFreelancerName()); ?>
                            </p>
                            <p><strong>Price:</strong>
                                <?php echo $item->getFormattedPrice(); ?> +
                                <?php echo $item->getFormattedServiceFee(); ?> fee
                            </p>
                            <p><strong>Delivery:</strong>
                                <?php echo $item->getFormattedDelivery(); ?>
                            </p>
                            <p><strong>Requirements:</strong>
                                <?php echo htmlspecialchars(substr($reqData['text'] ?? '', 0, 100)) . '...'; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Payment Summary -->
                <div class="form-section">
                    <h3 class="form-section-title">Payment Method</h3>
                    <?php $payment = $_SESSION['checkout']['payment'] ?? []; ?>
                    <?php if ($payment['method'] === 'credit_card'): ?>
                        <p>💳 Credit Card ending in
                            <?php echo $payment['card_last_four'] ?? '****'; ?>
                        </p>
                    <?php else: ?>
                        <p>🅿️ PayPal</p>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <div class="form-actions">
                        <a href="<?php echo SITE_URL; ?>checkout/checkout.php?step=2" class="btn btn-secondary">Back</a>
                        <button type="submit" name="place_order" value="1" class="btn btn-success btn-lg">
                            Place Order -
                            <?php echo formatPrice($grandTotal); ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Order Summary -->
    <div class="column-30">
        <div class="order-summary">
            <h3 class="order-summary-title">Order Summary</h3>

            <?php foreach ($cartItems as $item): ?>
                <div class="order-summary-row" style="margin-bottom: 10px; font-size: 14px;">
                    <span style="flex: 1;">
                        <?php echo htmlspecialchars(substr($item->getTitle(), 0, 25)); ?>...
                    </span>
                    <span>
                        <?php echo $item->getFormattedPrice(); ?>
                    </span>
                </div>
            <?php endforeach; ?>

            <div class="order-summary-row mt-md">
                <span>Subtotal</span>
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
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>