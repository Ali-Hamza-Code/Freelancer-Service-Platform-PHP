<?php
/**
 * User Registration (Use Case 1)
 * Allows new users to create accounts as Client or Freelancer
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . 'services/browse-services.php');
}

$errors = [];
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'country' => '',
    'city' => '',
    'role' => '',
    'bio' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $formData['first_name'] = sanitize($_POST['first_name'] ?? '');
    $formData['last_name'] = sanitize($_POST['last_name'] ?? '');
    $formData['email'] = sanitize($_POST['email'] ?? '');
    $formData['phone'] = sanitize($_POST['phone'] ?? '');
    $formData['country'] = sanitize($_POST['country'] ?? '');
    $formData['city'] = sanitize($_POST['city'] ?? '');
    $formData['role'] = sanitize($_POST['role'] ?? '');
    $formData['bio'] = sanitize($_POST['bio'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $ageVerification = isset($_POST['age_verification']);

    // Validate Full Name (First + Last)
    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'First name is required';
    } elseif (strlen($formData['first_name']) < 2 || strlen($formData['first_name']) > 50) {
        $errors['first_name'] = 'First name must be 2-50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $formData['first_name'])) {
        $errors['first_name'] = 'First name can only contain letters and spaces';
    }

    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    } elseif (strlen($formData['last_name']) < 2 || strlen($formData['last_name']) > 50) {
        $errors['last_name'] = 'Last name must be 2-50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $formData['last_name'])) {
        $errors['last_name'] = 'Last name can only contain letters and spaces';
    }

    // Validate Email
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $formData['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'This email is already registered';
        }
    }

    // Validate Password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one number';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors['password'] = 'Password must contain at least one special character';
    }

    // Validate Confirm Password
    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    // Validate Phone Number
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10}$/', $formData['phone'])) {
        $errors['phone'] = 'Phone number must be exactly 10 digits';
    }

    // Validate Country
    if (empty($formData['country'])) {
        $errors['country'] = 'Country is required';
    }

    // Validate City
    if (empty($formData['city'])) {
        $errors['city'] = 'City is required';
    }

    // Validate Account Type
    if (empty($formData['role']) || !in_array($formData['role'], ['Client', 'Freelancer'])) {
        $errors['role'] = 'Please select an account type';
    }

    // Validate Bio (Required for Freelancers)
    if ($formData['role'] === 'Freelancer') {
        if (empty($formData['bio'])) {
            $errors['bio'] = 'Bio is required for freelancers';
        } elseif (strlen($formData['bio']) > 500) {
            $errors['bio'] = 'Bio must not exceed 500 characters';
        }
    }

    // Validate Age Verification
    if (!$ageVerification) {
        $errors['age_verification'] = 'You must confirm that you are 18 years or older';
    }

    // If no errors, create account
    if (empty($errors)) {
        // Generate unique 10-digit user ID
        do {
            $userId = generateUniqueId();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
        } while ($stmt->fetchColumn() > 0);

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, first_name, last_name, email, password, phone, country, city, role, bio, status)
            VALUES (:user_id, :first_name, :last_name, :email, :password, :phone, :country, :city, :role, :bio, 'Active')
        ");

        try {
            $stmt->execute([
                ':user_id' => $userId,
                ':first_name' => $formData['first_name'],
                ':last_name' => $formData['last_name'],
                ':email' => $formData['email'],
                ':password' => $hashedPassword,
                ':phone' => $formData['phone'],
                ':country' => $formData['country'],
                ':city' => $formData['city'],
                ':role' => $formData['role'],
                ':bio' => $formData['bio']
            ]);

            // Success - redirect to login
            setSuccessMessage('Account created successfully! Please login.');
            redirect(SITE_URL . 'auth/login.php');
        } catch (PDOException $e) {
            $errors['general'] = 'An error occurred. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Create Your Account';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="form-container">
    <h1 class="heading-primary text-center">Create Your Account</h1>

    <?php if (isset($errors['general'])): ?>
        <div class="message message-error">
            <?php echo $errors['general']; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
        <!-- Personal Information Section -->
        <div class="form-section">
            <h2 class="form-section-title">Personal Information</h2>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name"
                        class="form-input <?php echo isset($errors['first_name']) ? 'error' : ''; ?>"
                        value="<?php echo htmlspecialchars($formData['first_name']); ?>" placeholder="Ali">
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
                        value="<?php echo htmlspecialchars($formData['last_name']); ?>" placeholder="Hamza">
                    <?php if (isset($errors['last_name'])): ?>
                        <div class="form-error">
                            <?php echo $errors['last_name']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email"
                    class="form-input <?php echo isset($errors['email']) ? 'error' : ''; ?>"
                    value="<?php echo htmlspecialchars($formData['email']); ?>"
                    placeholder="1220220@student.birzeit.edu">
                <?php if (isset($errors['email'])): ?>
                    <div class="form-error">
                        <?php echo $errors['email']; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone"
                    class="form-input <?php echo isset($errors['phone']) ? 'error' : ''; ?>"
                    value="<?php echo htmlspecialchars($formData['phone']); ?>" placeholder="0592208483">
                <?php if (isset($errors['phone'])): ?>
                    <div class="form-error">
                        <?php echo $errors['phone']; ?>
                    </div>
                <?php endif; ?>
                <div class="form-hint">Enter exactly 10 digits</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="country">Country <span class="required">*</span></label>
                    <select id="country" name="country"
                        class="form-select <?php echo isset($errors['country']) ? 'error' : ''; ?>">
                        <option value="">Select Country</option>
                        <?php foreach ($COUNTRIES as $country): ?>
                            <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $formData['country'] === $country ? 'selected' : ''; ?>>
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
                            <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $formData['city'] === $city ? 'selected' : ''; ?>>
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
        </div>

        <!-- Account Security Section -->
        <div class="form-section">
            <h2 class="form-section-title">Account Security</h2>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password"
                    class="form-input <?php echo isset($errors['password']) ? 'error' : ''; ?>"
                    placeholder="Create a strong password">
                <?php if (isset($errors['password'])): ?>
                    <div class="form-error">
                        <?php echo $errors['password']; ?>
                    </div>
                <?php endif; ?>
                <div class="form-hint">Min 8 characters: 1 uppercase, 1 lowercase, 1 number, 1 special character</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password <span
                        class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password"
                    class="form-input <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>"
                    placeholder="Confirm your password">
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="form-error">
                        <?php echo $errors['confirm_password']; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account Type Section -->
        <div class="form-section">
            <h2 class="form-section-title">Account Type</h2>

            <div class="form-group">
                <div class="form-radio">
                    <input type="radio" id="role_client" name="role" value="Client" <?php echo $formData['role'] === 'Client' ? 'checked' : ''; ?>>
                    <label for="role_client">Client - I want to hire freelancers</label>
                </div>
                <div class="form-radio">
                    <input type="radio" id="role_freelancer" name="role" value="Freelancer" <?php echo $formData['role'] === 'Freelancer' ? 'checked' : ''; ?>>
                    <label for="role_freelancer">Freelancer - I want to offer services</label>
                </div>
                <?php if (isset($errors['role'])): ?>
                    <div class="form-error">
                        <?php echo $errors['role']; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" id="bio_group">
                <label class="form-label" for="bio">Bio / About <span class="required"
                        id="bio_required">*</span></label>
                <textarea id="bio" name="bio" class="form-textarea <?php echo isset($errors['bio']) ? 'error' : ''; ?>"
                    placeholder="Tell us about yourself and your skills..."><?php echo htmlspecialchars($formData['bio']); ?></textarea>
                <?php if (isset($errors['bio'])): ?>
                    <div class="form-error">
                        <?php echo $errors['bio']; ?>
                    </div>
                <?php endif; ?>
                <div class="form-hint">Required for Freelancers (max 500 characters)</div>
            </div>
        </div>

        <!-- Age Verification -->
        <div class="form-section">
            <div class="form-group">
                <div class="form-checkbox">
                    <input type="checkbox" id="age_verification" name="age_verification" <?php echo isset($_POST['age_verification']) ? 'checked' : ''; ?>>
                    <label for="age_verification">I confirm that I am 18 years old or older <span
                            class="required">*</span></label>
                </div>
                <?php if (isset($errors['age_verification'])): ?>
                    <div class="form-error">
                        <?php echo $errors['age_verification']; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="<?php echo SITE_URL; ?>services/browse-services.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
    </form>

    <p class="text-center mt-lg">
        Already have an account? <a href="<?php echo SITE_URL; ?>auth/login.php">Login here</a>
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>