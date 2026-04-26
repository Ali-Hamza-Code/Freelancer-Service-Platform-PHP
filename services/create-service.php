<?php
/**
 * Create Service (Use Case 4)
 * 3-step wizard for freelancers to create service listings
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Require freelancer login
if (!isFreelancer()) {
    setErrorMessage('Only freelancers can create services.');
    redirect(SITE_URL . 'services/browse-services.php');
}

$userId = getCurrentUserId();
$errors = [];
$step = (int) ($_GET['step'] ?? 1);
$step = max(1, min(3, $step));

// Initialize session data for service creation
if (!isset($_SESSION['create_service'])) {
    $_SESSION['create_service'] = [
        'step1' => [],
        'step2' => [],
        'temp_images' => []
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1: Basic Information
    if ($step === 1) {
        $title = sanitize($_POST['title'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $subcategory = sanitize($_POST['subcategory'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $deliveryTime = (int) ($_POST['delivery_time'] ?? 0);
        $revisions = (int) ($_POST['revisions'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);

        // Validation
        if (empty($title)) {
            $errors['title'] = 'Service title is required';
        } elseif (strlen($title) < 10 || strlen($title) > 100) {
            $errors['title'] = 'Title must be 10-100 characters';
        } else {
            // Check if title is unique for this freelancer
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :user_id AND title = :title");
            $stmt->execute([':user_id' => $userId, ':title' => $title]);
            if ($stmt->fetchColumn() > 0) {
                $errors['title'] = 'You already have a service with this title';
            }
        }

        if (empty($category)) {
            $errors['category'] = 'Category is required';
        }

        if (empty($subcategory)) {
            $errors['subcategory'] = 'Subcategory is required';
        }

        if (empty($description)) {
            $errors['description'] = 'Description is required';
        } elseif (strlen($description) < 100 || strlen($description) > 2000) {
            $errors['description'] = 'Description must be 100-2000 characters';
        }

        if ($deliveryTime < 1 || $deliveryTime > 90) {
            $errors['delivery_time'] = 'Delivery time must be 1-90 days';
        }

        if ($revisions < 0 || $revisions > 999) {
            $errors['revisions'] = 'Revisions must be 0-999';
        }

        if ($price < 5 || $price > 10000) {
            $errors['price'] = 'Price must be between $5 and $10,000';
        }

        if (empty($errors)) {
            $_SESSION['create_service']['step1'] = [
                'title' => $title,
                'category' => $category,
                'subcategory' => $subcategory,
                'description' => $description,
                'delivery_time' => $deliveryTime,
                'revisions' => $revisions,
                'price' => $price
            ];
            redirect(SITE_URL . 'services/create-service.php?step=2');
        }
    }

    // Step 2: Image Upload
    if ($step === 2) {
        $uploadedImages = [];
        $mainImage = (int) ($_POST['main_image'] ?? 0);

        for ($i = 1; $i <= 3; $i++) {
            $fileKey = "image_$i";
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileKey];
                $fileType = mime_content_type($file['tmp_name']);

                if (!in_array($fileType, ALLOWED_IMAGE_TYPES)) {
                    $errors[$fileKey] = "Image $i: Only JPG, JPEG, and PNG files are allowed";
                    continue;
                }

                if ($file['size'] > MAX_SERVICE_IMAGE_SIZE) {
                    $errors[$fileKey] = "Image $i: File size must not exceed 5MB";
                    continue;
                }

                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo[0] < MIN_SERVICE_IMAGE_WIDTH || $imageInfo[1] < MIN_SERVICE_IMAGE_HEIGHT) {
                    $errors[$fileKey] = "Image $i: Image must be at least 800x600 pixels";
                    continue;
                }

                // Store temporarily
                $tempDir = sys_get_temp_dir() . '/service_temp_' . $userId . '/';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $tempPath = $tempDir . "image_$i.$extension";
                move_uploaded_file($file['tmp_name'], $tempPath);

                $uploadedImages[$i] = $tempPath;
            }
        }

        // Check if at least one image
        if (empty($uploadedImages) && empty($_SESSION['create_service']['temp_images'])) {
            $errors['images'] = 'At least one image is required';
        }

        if (empty($errors)) {
            if (!empty($uploadedImages)) {
                $_SESSION['create_service']['temp_images'] = $uploadedImages;
            }
            $_SESSION['create_service']['step2'] = ['main_image' => $mainImage];
            redirect(SITE_URL . 'services/create-service.php?step=3');
        }
    }

    // Step 3: Review and Confirm
    if ($step === 3 && isset($_POST['confirm'])) {
        $step1 = $_SESSION['create_service']['step1'];
        $step2 = $_SESSION['create_service']['step2'];
        $tempImages = $_SESSION['create_service']['temp_images'];

        // Generate unique service ID
        do {
            $serviceId = generateUniqueId();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_id = :service_id");
            $stmt->execute([':service_id' => $serviceId]);
        } while ($stmt->fetchColumn() > 0);

        // Create permanent upload directory
        $uploadDir = SERVICE_UPLOAD_PATH . $serviceId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move images to permanent location
        $imagePaths = [null, null, null];
        $imageIndex = 1;
        foreach ($tempImages as $key => $tempPath) {
            if (file_exists($tempPath)) {
                $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
                $filename = "image_0$imageIndex.$extension";
                $permanentPath = $uploadDir . $filename;

                if (rename($tempPath, $permanentPath)) {
                    $imagePaths[$imageIndex - 1] = SITE_URL . 'uploads/services/' . $serviceId . '/' . $filename;
                }
                $imageIndex++;
            }
        }

        // Insert service
        $stmt = $pdo->prepare("
            INSERT INTO services (service_id, freelancer_id, title, category, subcategory, description, 
                                  price, delivery_time, revisions_included, image_1, image_2, image_3, status, featured_status)
            VALUES (:service_id, :freelancer_id, :title, :category, :subcategory, :description,
                    :price, :delivery_time, :revisions, :image_1, :image_2, :image_3, 'Active', 'No')
        ");

        try {
            $stmt->execute([
                ':service_id' => $serviceId,
                ':freelancer_id' => $userId,
                ':title' => $step1['title'],
                ':category' => $step1['category'],
                ':subcategory' => $step1['subcategory'],
                ':description' => $step1['description'],
                ':price' => $step1['price'],
                ':delivery_time' => $step1['delivery_time'],
                ':revisions' => $step1['revisions'],
                ':image_1' => $imagePaths[0],
                ':image_2' => $imagePaths[1],
                ':image_3' => $imagePaths[2]
            ]);

            // Clear session data
            unset($_SESSION['create_service']);

            // Clean up temp directory
            $tempDir = sys_get_temp_dir() . '/service_temp_' . $userId . '/';
            if (is_dir($tempDir)) {
                array_map('unlink', glob("$tempDir*"));
                rmdir($tempDir);
            }

            setSuccessMessage("Service created successfully! Service ID: $serviceId");
            redirect(SITE_URL . 'services/my-services.php');
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to create service. Please try again.';
            error_log("Service creation error: " . $e->getMessage());
        }
    }
}

// Get data for current step
$step1Data = $_SESSION['create_service']['step1'] ?? [];
$step2Data = $_SESSION['create_service']['step2'] ?? [];
$tempImages = $_SESSION['create_service']['temp_images'] ?? [];

$pageTitle = 'Create New Service';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<h1 class="heading-primary">Create New Service</h1>

<!-- Progress Indicator -->
<div class="step-indicator">
    <div class="step-item <?php echo $step >= 1 ? ($step > 1 ? 'step-completed' : 'step-active') : 'step-inactive'; ?>">
        <?php if ($step > 1): ?>
            <a href="<?php echo SITE_URL; ?>services/create-service.php?step=1" class="step-icon">✓</a>
        <?php else: ?>
            <span class="step-icon">1</span>
        <?php endif; ?>
        <span class="step-label">Basic Information</span>
    </div>
    <div class="step-connector"></div>
    <div class="step-item <?php echo $step >= 2 ? ($step > 2 ? 'step-completed' : 'step-active') : 'step-inactive'; ?>">
        <?php if ($step > 2): ?>
            <a href="<?php echo SITE_URL; ?>services/create-service.php?step=2" class="step-icon">✓</a>
        <?php else: ?>
            <span class="step-icon">2</span>
        <?php endif; ?>
        <span class="step-label">Upload Images</span>
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

<div class="form-container wide">
    <?php if ($step === 1): ?>
        <!-- Step 1: Basic Information -->
        <h2 class="heading-secondary">Step 1: Service Details</h2>
        <form method="POST" action="" novalidate>
            <div class="form-section">
                <div class="form-group">
                    <label class="form-label" for="title">Service Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title"
                        class="form-input <?php echo isset($errors['title']) ? 'error' : ''; ?>"
                        value="<?php echo htmlspecialchars($step1Data['title'] ?? ''); ?>"
                        placeholder="I will create a professional...">
                    <?php if (isset($errors['title'])): ?>
                        <div class="form-error">
                            <?php echo $errors['title']; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-hint">10-100 characters</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="category">Category <span class="required">*</span></label>
                        <select id="category" name="category"
                            class="form-select <?php echo isset($errors['category']) ? 'error' : ''; ?>">
                            <option value="">Select Category</option>
                            <?php foreach (array_keys($CATEGORIES) as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($step1Data['category'] ?? '') === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['category'])): ?>
                            <div class="form-error">
                                <?php echo $errors['category']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="subcategory">Subcategory <span class="required">*</span></label>
                        <select id="subcategory" name="subcategory"
                            class="form-select <?php echo isset($errors['subcategory']) ? 'error' : ''; ?>">
                            <option value="">Select Subcategory</option>
                            <?php foreach ($CATEGORIES as $cat => $subs): ?>
                                <optgroup label="<?php echo htmlspecialchars($cat); ?>">
                                    <?php foreach ($subs as $sub): ?>
                                        <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo ($step1Data['subcategory'] ?? '') === $sub ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sub); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['subcategory'])): ?>
                            <div class="form-error">
                                <?php echo $errors['subcategory']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description"
                        class="form-textarea <?php echo isset($errors['description']) ? 'error' : ''; ?>"
                        style="min-height: 200px;"><?php echo htmlspecialchars($step1Data['description'] ?? ''); ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <div class="form-error">
                            <?php echo $errors['description']; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-hint">100-2000 characters. Describe your service in detail.</div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Pricing & Delivery</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price">Price (USD) <span class="required">*</span></label>
                        <input type="number" id="price" name="price"
                            class="form-input <?php echo isset($errors['price']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($step1Data['price'] ?? ''); ?>" min="5" max="10000"
                            step="0.01">
                        <?php if (isset($errors['price'])): ?>
                            <div class="form-error">
                                <?php echo $errors['price']; ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-hint">$5 - $10,000</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="delivery_time">Delivery Time (days) <span
                                class="required">*</span></label>
                        <input type="number" id="delivery_time" name="delivery_time"
                            class="form-input <?php echo isset($errors['delivery_time']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($step1Data['delivery_time'] ?? ''); ?>" min="1" max="90">
                        <?php if (isset($errors['delivery_time'])): ?>
                            <div class="form-error">
                                <?php echo $errors['delivery_time']; ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-hint">1-90 days</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="revisions">Revisions Included <span class="required">*</span></label>
                        <input type="number" id="revisions" name="revisions"
                            class="form-input <?php echo isset($errors['revisions']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($step1Data['revisions'] ?? ''); ?>" min="0" max="999">
                        <?php if (isset($errors['revisions'])): ?>
                            <div class="form-error">
                                <?php echo $errors['revisions']; ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-hint">0-999 (use 999 for unlimited)</div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?php echo SITE_URL; ?>services/my-services.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Continue to Images</button>
            </div>
        </form>

    <?php elseif ($step === 2): ?>
        <!-- Step 2: Image Upload -->
        <h2 class="heading-secondary">Step 2: Upload Images</h2>
        <form method="POST" action="" enctype="multipart/form-data" novalidate>
            <?php if (isset($errors['images'])): ?>
                <div class="message message-error">
                    <?php echo $errors['images']; ?>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <div class="upload-area">
                    <p class="text-muted mb-md">Upload 1-3 images for your service (JPG, JPEG, or PNG, minimum 800x600
                        pixels, max 5MB each)</p>

                    <div class="form-group">
                        <label class="form-label" for="image_1">Service Image 1 <span class="required">*</span></label>
                        <input type="file" id="image_1" name="image_1" class="form-input"
                            accept="image/jpeg,image/jpg,image/png">
                        <?php if (isset($errors['image_1'])): ?>
                            <div class="form-error">
                                <?php echo $errors['image_1']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="image_2">Service Image 2 (optional)</label>
                        <input type="file" id="image_2" name="image_2" class="form-input"
                            accept="image/jpeg,image/jpg,image/png">
                        <?php if (isset($errors['image_2'])): ?>
                            <div class="form-error">
                                <?php echo $errors['image_2']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="image_3">Service Image 3 (optional)</label>
                        <input type="file" id="image_3" name="image_3" class="form-input"
                            accept="image/jpeg,image/jpg,image/png">
                        <?php if (isset($errors['image_3'])): ?>
                            <div class="form-error">
                                <?php echo $errors['image_3']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group mt-lg">
                    <label class="form-label">Select Main Image</label>
                    <div class="form-radio">
                        <input type="radio" name="main_image" value="0" checked id="main_0">
                        <label for="main_0">Image 1 as main</label>
                    </div>
                    <div class="form-radio">
                        <input type="radio" name="main_image" value="1" id="main_1">
                        <label for="main_1">Image 2 as main (if uploaded)</label>
                    </div>
                    <div class="form-radio">
                        <input type="radio" name="main_image" value="2" id="main_2">
                        <label for="main_2">Image 3 as main (if uploaded)</label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?php echo SITE_URL; ?>services/create-service.php?step=1" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Continue to Review</button>
            </div>
        </form>

    <?php elseif ($step === 3): ?>
        <!-- Step 3: Review and Confirm -->
        <h2 class="heading-secondary">Step 3: Review Your Service</h2>

        <?php if (empty($step1Data)): ?>
            <div class="message message-error">Please complete all steps first.</div>
            <a href="<?php echo SITE_URL; ?>services/create-service.php?step=1" class="btn btn-primary">Start Over</a>
        <?php else: ?>
            <div class="card mb-lg">
                <h3 class="heading-tertiary">Service Details</h3>
                <table class="table">
                    <tr>
                        <th style="width:30%">Title</th>
                        <td>
                            <?php echo htmlspecialchars($step1Data['title']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td>
                            <?php echo htmlspecialchars($step1Data['category']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Subcategory</th>
                        <td>
                            <?php echo htmlspecialchars($step1Data['subcategory']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Price</th>
                        <td>
                            <?php echo formatPrice($step1Data['price']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Delivery Time</th>
                        <td>
                            <?php echo $step1Data['delivery_time']; ?> days
                        </td>
                    </tr>
                    <tr>
                        <th>Revisions</th>
                        <td>
                            <?php echo $step1Data['revisions'] >= 999 ? 'Unlimited' : $step1Data['revisions']; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card mb-lg">
                <h3 class="heading-tertiary">Description</h3>
                <p>
                    <?php echo nl2br(htmlspecialchars($step1Data['description'])); ?>
                </p>
            </div>

            <div class="card mb-lg">
                <h3 class="heading-tertiary">Images</h3>
                <p class="text-muted">
                    <?php echo count($tempImages); ?> image(s) uploaded
                </p>
            </div>

            <form method="POST" action="">
                <div class="form-actions">
                    <a href="<?php echo SITE_URL; ?>services/create-service.php?step=2" class="btn btn-secondary">Back to
                        Images</a>
                    <button type="submit" name="confirm" value="1" class="btn btn-success">Publish Service</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>