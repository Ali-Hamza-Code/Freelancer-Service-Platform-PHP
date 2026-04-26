<?php
/**
 * User Login (Use Case 2)
 * Authenticates users and creates sessions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php.inc';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . 'services/browse-services.php');
}

$errors = [];
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate Email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    // Validate Password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }

    // If basic validation passes, attempt login
    if (empty($errors)) {
        // Fetch user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if account is locked
            if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
                $remainingMinutes = ceil((strtotime($user['lockout_until']) - time()) / 60);
                $errors['general'] = "Account temporarily locked. Please try again in $remainingMinutes minutes.";
            }
            // Check if account is inactive
            elseif ($user['status'] !== 'Active') {
                $errors['general'] = 'Your account is inactive. Please contact support.';
            }
            // Verify password
            elseif (password_verify($password, $user['password'])) {
                // Successful login - reset failed attempts
                $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $user['user_id']]);

                // Create session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_photo'] = $user['profile_photo'];
                $_SESSION['login_time'] = time();

                // Initialize empty cart for clients
                if ($user['role'] === 'Client' && !isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }

                // Redirect based on role
                if ($user['role'] === 'Client') {
                    redirect(SITE_URL . 'services/browse-services.php');
                } else {
                    redirect(SITE_URL . 'services/my-services.php');
                }
            } else {
                // Invalid password - increment failed attempts
                $failedAttempts = $user['failed_login_attempts'] + 1;

                if ($failedAttempts >= MAX_LOGIN_ATTEMPTS) {
                    // Lock account
                    $lockoutTime = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts, lockout_until = :lockout WHERE user_id = :user_id");
                    $stmt->execute([
                        ':attempts' => $failedAttempts,
                        ':lockout' => $lockoutTime,
                        ':user_id' => $user['user_id']
                    ]);
                    $errors['general'] = 'Account temporarily locked due to too many failed attempts. Please try again in 30 minutes.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts WHERE user_id = :user_id");
                    $stmt->execute([
                        ':attempts' => $failedAttempts,
                        ':user_id' => $user['user_id']
                    ]);

                    $remaining = MAX_LOGIN_ATTEMPTS - $failedAttempts;
                    if ($remaining <= 2) {
                        $errors['general'] = "Invalid email or password. $remaining attempts remaining before account lockout.";
                    } else {
                        $errors['general'] = 'Invalid email or password';
                    }
                }
            }
        } else {
            // User not found
            $errors['general'] = 'Invalid email or password';
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="form-container" style="max-width: 400px;">
    <h1 class="heading-primary text-center">Login to Your Account</h1>

    <?php if (isset($errors['general'])): ?>
        <div class="message message-error">
            <?php echo $errors['general']; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
        <div class="form-group">
            <label class="form-label" for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email"
                class="form-input <?php echo isset($errors['email']) ? 'error' : ''; ?>"
                value="<?php echo htmlspecialchars($email); ?>" placeholder="your@email.com">
            <?php if (isset($errors['email'])): ?>
                <div class="form-error">
                    <?php echo $errors['email']; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password"
                class="form-input <?php echo isset($errors['password']) ? 'error' : ''; ?>"
                placeholder="Enter your password">
            <?php if (isset($errors['password'])): ?>
                <div class="form-error">
                    <?php echo $errors['password']; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <div class="form-checkbox">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Remember me</label>
            </div>
            <div class="form-hint">This feature is a placeholder for Phase 1</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-full">Login</button>
        </div>
    </form>

    <div class="mt-lg text-center">
        <a href="#">Forgot password?</a>
    </div>

    <p class="text-center mt-lg">
        Don't have an account? <a href="<?php echo SITE_URL; ?>auth/register.php">Sign up</a>
    </p>

    <!-- Test Account Information -->
    <div class="alert alert-info mt-lg">
        <strong>Test Accounts:</strong><br>
        <strong>Client:</strong> client@test.com / Test@123<br>
        <strong>Freelancer:</strong> freelancer@test.com / Test@123
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>