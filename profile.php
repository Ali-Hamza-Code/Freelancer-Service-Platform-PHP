<?php
/**
 * Profile Management (Use Case 3)
 * View and update personal information, profile picture, and professional info
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php.inc';

// Require login
if (!isLoggedIn()) {
    setErrorMessage('Please login to access your profile.');
    redirect(SITE_URL . 'auth/login.php');
}

$userId = getCurrentUserId();
$errors = [];
$success = false;

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    setErrorMessage('User not found.');
    redirect(SITE_URL . 'auth/logout.php');
}

// Get freelancer statistics if applicable
$stats = null;
if (isFreelancer()) {
    // Total services
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE freelancer_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $stats['total_services'] = $stmt->fetch()['total'];

    // Active services
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM services WHERE freelancer_id = :user_id AND status = 'Active'");
    $stmt->execute([':user_id' => $userId]);
    $stats['active_services'] = $stmt->fetch()['active'];

    // Featured services
    $stmt = $pdo->prepare("SELECT COUNT(*) as featured FROM services WHERE freelancer_id = :user_id AND featured_status = 'Yes'");
    $stmt->execute([':user_id' => $userId]);
    $stats['featured_services'] = $stmt->fetch()['featured'];

    // Completed orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM orders WHERE freelancer_id = :user_id AND status = 'Completed'");
    $stmt->execute([':user_id' => $userId]);
    $stats['completed_orders'] = $stmt->fetch()['completed'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Freelancer-specific fields
    $professionalTitle = sanitize($_POST['professional_title'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    $skills = sanitize($_POST['skills'] ?? '');
    $yearsExperience = (int) ($_POST['years_experience'] ?? 0);

    // Validate First Name
    if (empty($firstName)) {
        $errors['first_name'] = 'First name is required';
    } elseif (strlen($firstName) < 2 || strlen($firstName) > 50) {
        $errors['first_name'] = 'First name must be 2-50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $firstName)) {
        $errors['first_name'] = 'First name can only contain letters and spaces';
    }

    // Validate Last Name
    if (empty($lastName)) {
        $errors['last_name'] = 'Last name is required';
    } elseif (strlen($lastName) < 2 || strlen($lastName) > 50) {
        $errors['last_name'] = 'Last name must be 2-50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $lastName)) {
        $errors['last_name'] = 'Last name can only contain letters and spaces';
    }

    // Validate Email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } elseif ($email !== $user['email']) {
        // Check if new email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
        $stmt->execute([':email' => $email, ':user_id' => $userId]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'This email is already in use';
        }
    }

    // Validate Phone
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = 'Phone number must be exactly 10 digits';
    }

    // Validate Country and City
    if (empty($country)) {
        $errors['country'] = 'Country is required';
    }
    if (empty($city)) {
        $errors['city'] = 'City is required';
    }

    // Validate Password Change (if attempting)
    if (!empty($newPassword) || !empty($confirmPassword)) {
        if (empty($currentPassword)) {
            $errors['current_password'] = 'Current password is required to change password';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect';
        }

        if (empty($newPassword)) {
            $errors['new_password'] = 'New password is required';
        } elseif (strlen($newPassword) < 8) {
            $errors['new_password'] = 'Password must be at least 8 characters';
        } elseif (
            !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)
        ) {
            $errors['new_password'] = 'Password must contain uppercase, lowercase, number, and special character';
        }

        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
    }

    // Validate Freelancer fields
    if (isFreelancer()) {
        if (empty($professionalTitle)) {
            $errors['professional_title'] = 'Professional title is required';
        } elseif (strlen($professionalTitle) < 10 || strlen($professionalTitle) > 100) {
            $errors['professional_title'] = 'Professional title must be 10-100 characters';
        }

        if (empty($bio)) {
            $errors['bio'] = 'Bio is required for freelancers';
        } elseif (strlen($bio) < 50 || strlen($bio) > 500) {
            $errors['bio'] = 'Bio must be 50-500 characters';
        }

        if (strlen($skills) > 200) {
            $errors['skills'] = 'Skills must not exceed 200 characters';
        }

        if ($yearsExperience < 0 || $yearsExperience > 50) {
            $errors['years_experience'] = 'Years of experience must be 0-50';
        }
    }

    // Handle Profile Photo Upload
    $profilePhotoPath = $user['profile_photo'];
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, ALLOWED_IMAGE_TYPES)) {
            $errors['profile_photo'] = 'Only JPG, JPEG, and PNG files are allowed';
        } elseif ($file['size'] > MAX_PROFILE_PHOTO_SIZE) {
            $errors['profile_photo'] = 'File size must not exceed 2MB';
        } else {
            // Check minimum dimensions
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo[0] < MIN_PROFILE_PHOTO_SIZE || $imageInfo[1] < MIN_PROFILE_PHOTO_SIZE) {
                $errors['profile_photo'] = 'Image must be at least 300x300 pixels';
            }
        }

        if (!isset($errors['profile_photo'])) {
            // Create upload directory
            $uploadDir = PROFILE_UPLOAD_PATH . $userId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_photo.' . $extension;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $profilePhotoPath = SITE_URL . 'uploads/profiles/' . $userId . '/' . $filename;
            } else {
                $errors['profile_photo'] = 'Failed to upload file. Please try again.';
            }
        }
    }

    // Update database if no errors
    if (empty($errors)) {
        $sql = "UPDATE users SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                country = :country,
                city = :city,
                profile_photo = :profile_photo";

        $params = [
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':email' => $email,
            ':phone' => $phone,
            ':country' => $country,
            ':city' => $city,
            ':profile_photo' => $profilePhotoPath,
            ':user_id' => $userId
        ];

        // Add freelancer fields
        if (isFreelancer()) {
            $sql .= ", professional_title = :professional_title,
                      bio = :bio,
                      skills = :skills,
                      years_experience = :years_experience";
            $params[':professional_title'] = $professionalTitle;
            $params[':bio'] = $bio;
            $params[':skills'] = $skills;
            $params[':years_experience'] = $yearsExperience;
        }

        // Add password if changing
        if (!empty($newPassword)) {
            $sql .= ", password = :password";
            $params[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE user_id = :user_id";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Update session
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['email'] = $email;
            $_SESSION['profile_photo'] = $profilePhotoPath;

            $success = true;

            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $errors['general'] = 'An error occurred. Please try again.';
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<h1 class="heading-primary">My Profile</h1>

<?php if ($success): ?>
    <div class="message message-success">Profile updated successfully!</div>
<?php endif; ?>

<?php if (isset($errors['general'])): ?>
    <div class="message message-error">
        <?php echo $errors['general']; ?>
    </div>
<?php endif; ?>

<div class="two-column-layout">
    <!-- Left Column: Profile Card and Stats -->
    <div class="column-30">
        <!-- Profile Card -->
        <div class="profile-card">
            <img src="<?php echo htmlspecialchars(getProfilePhotoUrl($user['profile_photo'])); ?>" alt="Profile Photo"
                class="profile-card-photo" onerror="this.src='<?php echo SITE_URL; ?>uploads/profiles/default.png'">
            <h2 class="profile-card-name">
                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            </h2>
            <p class="profile-card-email">
                <?php echo htmlspecialchars($user['email']); ?>
            </p>
            <span
                class="role-badge <?php echo $user['role'] === 'Client' ? 'role-badge-client' : 'role-badge-freelancer'; ?>">
                <?php echo htmlspecialchars($user['role']); ?>
            </span>
            <p class="text-muted mt-md">Member since
                <?php echo formatDate($user['registration_date']); ?>
            </p>
        </div>

        <?php if (isFreelancer() && $stats): ?>
            <!-- Statistics Card (Freelancers Only) -->
            <div class="statistics-card">
                <h3 class="heading-tertiary">Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo $stats['total_services']; ?>
                        </div>
                        <div class="stat-label">Total Services</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value active">
                            <?php echo $stats['active_services']; ?>
                        </div>
                        <div class="stat-label">Active Services</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value featured">
                            <?php echo $stats['featured_services']; ?>/3
                        </div>
                        <div class="stat-label">Featured Services</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo $stats['completed_orders']; ?>
                        </div>
                        <div class="stat-label">Completed Orders</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Edit Form -->
    <div class="column-70">
        <div class="card">
            <form method="POST" action="" enctype="multipart/form-data" novalidate>
                <!-- Account Information -->
                <div class="form-section">
                    <h2 class="form-section-title">Account Information</h2>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email"
                            class="form-input <?php echo isset($errors['email']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($user['email']); ?>">
                        <?php if (isset($errors['email'])): ?>
                            <div class="form-error">
                                <?php echo $errors['email']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password"
                            class="form-input <?php echo isset($errors['current_password']) ? 'error' : ''; ?>"
                            placeholder="Required only if changing password">
                        <?php if (isset($errors['current_password'])): ?>
                            <div class="form-error">
                                <?php echo $errors['current_password']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password"
                                class="form-input <?php echo isset($errors['new_password']) ? 'error' : ''; ?>"
                                placeholder="Leave blank to keep current">
                            <?php if (isset($errors['new_password'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['new_password']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                class="form-input <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['confirm_password']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="form-section">
                    <h2 class="form-section-title">Personal Information</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name <span
                                    class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name"
                                class="form-input <?php echo isset($errors['first_name']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            <?php if (isset($errors['first_name'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['first_name']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name"
                                class="form-input <?php echo isset($errors['last_name']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            <?php if (isset($errors['last_name'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['last_name']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone"
                            class="form-input <?php echo isset($errors['phone']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($user['phone']); ?>">
                        <?php if (isset($errors['phone'])): ?>
                            <div class="form-error">
                                <?php echo $errors['phone']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="country">Country <span class="required">*</span></label>
                            <select id="country" name="country"
                                class="form-select <?php echo isset($errors['country']) ? 'error' : ''; ?>">
                                <option value="">Select Country</option>
                                <?php foreach ($COUNTRIES as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $user['country'] === $country ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['country'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['country']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="city">City <span class="required">*</span></label>
                            <select id="city" name="city"
                                class="form-select <?php echo isset($errors['city']) ? 'error' : ''; ?>">
                                <option value="">Select City</option>
                                <?php foreach ($CITIES as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $user['city'] === $city ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['city'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['city']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="profile_photo">Profile Photo (optional)</label>
                        <input type="file" id="profile_photo" name="profile_photo" class="form-input"
                            accept="image/jpeg,image/jpg,image/png">
                        <?php if (isset($errors['profile_photo'])): ?>
                            <div class="form-error">
                                <?php echo $errors['profile_photo']; ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-hint">JPG, JPEG, PNG only. Max 2MB. Minimum 300x300 pixels.</div>
                    </div>
                </div>

                <?php if (isFreelancer()): ?>
                    <!-- Professional Information (Freelancers Only) -->
                    <div class="form-section">
                        <h2 class="form-section-title">Professional Information</h2>

                        <div class="form-group">
                            <label class="form-label" for="professional_title">Professional Title <span
                                    class="required">*</span></label>
                            <input type="text" id="professional_title" name="professional_title"
                                class="form-input <?php echo isset($errors['professional_title']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($user['professional_title'] ?? ''); ?>"
                                placeholder="e.g., Senior Web Developer">
                            <?php if (isset($errors['professional_title'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['professional_title']; ?>
                                </div>
                            <?php endif; ?>
                            <div class="form-hint">10-100 characters</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="bio">Bio/Description <span class="required">*</span></label>
                            <textarea id="bio" name="bio"
                                class="form-textarea <?php echo isset($errors['bio']) ? 'error' : ''; ?>"
                                placeholder="Tell clients about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <?php if (isset($errors['bio'])): ?>
                                <div class="form-error">
                                    <?php echo $errors['bio']; ?>
                                </div>
                            <?php endif; ?>
                            <div class="form-hint">50-500 characters</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="skills">Skills (optional)</label>
                                <input type="text" id="skills" name="skills"
                                    class="form-input <?php echo isset($errors['skills']) ? 'error' : ''; ?>"
                                    value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>"
                                    placeholder="PHP, MySQL, JavaScript...">
                                <?php if (isset($errors['skills'])): ?>
                                    <div class="form-error">
                                        <?php echo $errors['skills']; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="form-hint">Comma-separated, max 200 characters</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="years_experience">Years of Experience (optional)</label>
                                <input type="number" id="years_experience" name="years_experience"
                                    class="form-input <?php echo isset($errors['years_experience']) ? 'error' : ''; ?>"
                                    value="<?php echo htmlspecialchars($user['years_experience'] ?? 0); ?>" min="0"
                                    max="50">
                                <?php if (isset($errors['years_experience'])): ?>
                                    <div class="form-error">
                                        <?php echo $errors['years_experience']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>