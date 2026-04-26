<?php
/**
 * Edit Service (Use Case 5)
 * Allows freelancers to edit their existing service listings
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Require freelancer login
if (!isFreelancer()) {
    setErrorMessage('Only freelancers can edit services.');
    redirect(SITE_URL . 'services/browse-services.php');
}

$userId = getCurrentUserId();
$serviceId = sanitize($_GET['id'] ?? '');

if (empty($serviceId)) {
    setErrorMessage('Service not found.');
    redirect(SITE_URL . 'services/my-services.php');
}

// Fetch service and verify ownership
$stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = :service_id AND freelancer_id = :user_id");
$stmt->execute([':service_id' => $serviceId, ':user_id' => $userId]);
$service = $stmt->fetch();

if (!$service) {
    setErrorMessage('Service not found or you do not have permission to edit it.');
    redirect(SITE_URL . 'services/my-services.php');
}

$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $subcategory = sanitize($_POST['subcategory'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $deliveryTime = (int)($_POST['delivery_time'] ?? 0);
    $revisions = (int)($_POST['revisions'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'Active');
    $featured = isset($_POST['featured']) && $_POST['featured'] === 'Yes' ? 'Yes' : 'No';

    // Validation
    if (empty($title)) {
        $errors['title'] = 'Service title is required';
    } elseif (strlen($title) < 10 || strlen($title) > 100) {
        $errors['title'] = 'Title must be 10-100 characters';
    } else {
        // Check if title is unique (excluding current service)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :user_id AND title = :title AND service_id != :service_id");
        $stmt->execute([':user_id' => $userId, ':title' => $title, ':service_id' => $serviceId]);
        if ($stmt->fetchColumn() > 0) {
            $errors['title'] = 'You already have another service with this title';
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

    // Validate status
    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    // If setting to inactive, remove featured
    if ($status === 'Inactive') {
        $featured = 'No';
    }

    // Check featured limit
    if ($featured === 'Yes') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :user_id AND featured_status = 'Yes' AND service_id != :service_id");
        $stmt->execute([':user_id' => $userId, ':service_id' => $serviceId]);
        if ($stmt->fetchColumn() >= 3) {
            $errors['featured'] = 'You can only have 3 featured services at a time';
        }
    }

    // Handle image uploads
    $imagePaths = [
        $service['image_1'],
        $service['image_2'],
        $service['image_3']
    ];

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

            // Upload to permanent location
            $uploadDir = SERVICE_UPLOAD_PATH . $serviceId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = "image_0$i.$extension";
            $targetPath = $uploadDir . $filename;

            // Delete old image if exists
            if ($imagePaths[$i - 1]) {
                $oldPath = $imagePaths[$i - 1];
                $fsPath = '';
                if (strpos($oldPath, SITE_URL) === 0) {
                    $fsPath = __DIR__ . '/../' . substr($oldPath, strlen(SITE_URL));
                } else {
                    $fsPath = __DIR__ . '/..' . $oldPath;
                }
                
                if (file_exists($fsPath)) {
                    unlink($fsPath);
                }
            }

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $imagePaths[$i - 1] = SITE_URL . 'uploads/services/' . $serviceId . '/' . $filename;
            }
        }
    }

    // Check at least one image
    if (empty($imagePaths[0]) && empty($imagePaths[1]) && empty($imagePaths[2])) {
        $errors['images'] = 'At least one image is required';
    }

    // Update database if no errors
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE services SET 
                title = :title,
                category = :category,
                subcategory = :subcategory,
                description = :description,
                price = :price,
                delivery_time = :delivery_time,
                revisions_included = :revisions,
                image_1 = :image_1,
                image_2 = :image_2,
                image_3 = :image_3,
                status = :status,
                featured_status = :featured
            WHERE service_id = :service_id
        ");

        try {
            $stmt->execute([
                ':title' => $title,
                ':category' => $category,
                ':subcategory' => $subcategory,
                ':description' => $description,
                ':price' => $price,
                ':delivery_time' => $deliveryTime,
                ':revisions' => $revisions,
                ':image_1' => $imagePaths[0],
                ':image_2' => $imagePaths[1],
                ':image_3' => $imagePaths[2],
                ':status' => $status,
                ':featured' => $featured,
                ':service_id' => $serviceId
            ]);

            setSuccessMessage('Service updated successfully!');
            redirect(SITE_URL . 'services/my-services.php');
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to update service. Please try again.';
            error_log("Service update error: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Edit Service';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<h1 class="heading-primary">Edit Service</h1>

<?php if (isset($errors['general'])): ?>
    <div class="message message-error"><?php echo $errors['general']; ?></div>
<?php endif; ?>

<div class="form-container wide">
    <form method="POST" action="" enctype="multipart/form-data" novalidate>
        <!-- Basic Information -->
        <div class="form-section">
            <h2 class="form-section-title">Service Details</h2>

            <div class="form-group">
                <label class="form-label" for="title">Service Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-input <?php echo isset($errors['title']) ? 'error' : ''; ?>" 
                       value="<?php echo htmlspecialchars($service['title']); ?>">
                <?php if (isset($errors['title'])): ?>
                    <div class="form-error"><?php echo $errors['title']; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="category">Category <span class="required">*</span></label>
                    <select id="category" name="category" class="form-select">
                        <option value="">Select Category</option>
                        <?php foreach (array_keys($CATEGORIES) as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $service['category'] === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subcategory">Subcategory <span class="required">*</span></label>
                    <select id="subcategory" name="subcategory" class="form-select">
                        <option value="">Select Subcategory</option>
                        <?php foreach ($CATEGORIES as $cat => $subs): ?>
                            <optgroup label="<?php echo htmlspecialchars($cat); ?>">
                                <?php foreach ($subs as $sub): ?>
                                    <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo $service['subcategory'] === $sub ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sub); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-textarea" style="min-height: 200px;"><?php echo htmlspecialchars($service['description']); ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="form-error"><?php echo $errors['description']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pricing -->
        <div class="form-section">
            <h2 class="form-section-title">Pricing & Delivery</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="price">Price (USD) <span class="required">*</span></label>
                    <input type="number" id="price" name="price" class="form-input" 
                           value="<?php echo htmlspecialchars($service['price']); ?>" min="5" max="10000" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label" for="delivery_time">Delivery Time (days) <span class="required">*</span></label>
                    <input type="number" id="delivery_time" name="delivery_time" class="form-input" 
                           value="<?php echo htmlspecialchars($service['delivery_time']); ?>" min="1" max="90">
                </div>
                <div class="form-group">
                    <label class="form-label" for="revisions">Revisions <span class="required">*</span></label>
                    <input type="number" id="revisions" name="revisions" class="form-input" 
                           value="<?php echo htmlspecialchars($service['revisions_included']); ?>" min="0" max="999">
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="form-section">
            <h2 class="form-section-title">Images</h2>
            <?php if (isset($errors['images'])): ?>
                <div class="message message-error"><?php echo $errors['images']; ?></div>
            <?php endif; ?>

            <div class="form-row">
                <?php for ($i = 1; $i <= 3; $i++): 
                    $imgField = "image_$i";
                    $currentImg = $service[$imgField];
                ?>
                    <div class="form-group">
                        <label class="form-label">Image <?php echo $i; ?> <?php echo $i === 1 ? '<span class="required">*</span>' : '(optional)'; ?></label>
                        <?php if ($currentImg): ?>
                            <img src="<?php echo htmlspecialchars(getServiceImageUrl($currentImg)); ?>" alt="Current Image <?php echo $i; ?>" 
                                 style="width: 150px; height: 112px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;">
                        <?php endif; ?>
                        <input type="file" name="<?php echo $imgField; ?>" class="form-input" accept="image/jpeg,image/jpg,image/png">
                        <div class="form-hint">Leave empty to keep current image</div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Status -->
        <div class="form-section">
            <h2 class="form-section-title">Status</h2>
            
            <div class="form-group">
                <label class="form-label">Service Status</label>
                <div class="form-radio">
                    <input type="radio" name="status" value="Active" id="status_active" <?php echo $service['status'] === 'Active' ? 'checked' : ''; ?>>
                    <label for="status_active">Active - Visible and purchasable</label>
                </div>
                <div class="form-radio">
                    <input type="radio" name="status" value="Inactive" id="status_inactive" <?php echo $service['status'] === 'Inactive' ? 'checked' : ''; ?>>
                    <label for="status_inactive">Inactive - Hidden from browse</label>
                </div>
            </div>

            <div class="form-group">
                <div class="form-checkbox">
                    <input type="checkbox" name="featured" value="Yes" id="featured" 
                           <?php echo $service['featured_status'] === 'Yes' ? 'checked' : ''; ?>
                           <?php echo $service['status'] !== 'Active' ? 'disabled' : ''; ?>>
                    <label for="featured">⭐ Feature this service (max 3 featured services)</label>
                </div>
                <?php if (isset($errors['featured'])): ?>
                    <div class="form-error"><?php echo $errors['featured']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?php echo SITE_URL; ?>services/my-services.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Service</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
