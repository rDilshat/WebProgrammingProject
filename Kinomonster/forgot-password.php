<?php

declare(strict_types=1);

require __DIR__ . '/config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        header('Location: forgot-password.php?status=invalid_data');
        exit;
    }

    try {
        $pdo = require __DIR__ . '/config/database.php';
        $pdo->beginTransaction();

        $pdo->exec(
            'DELETE FROM password_reset_tokens
             WHERE expires_at <= NOW() OR used_at IS NOT NULL'
        );

        $statement = $pdo->prepare(
            'SELECT id FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $userId = $statement->fetchColumn();

        unset($_SESSION['password_reset_link']);

        if ($userId !== false) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $deleteOldTokens = $pdo->prepare(
                'DELETE FROM password_reset_tokens WHERE user_id = :user_id'
            );
            $deleteOldTokens->execute(['user_id' => (int) $userId]);

            $insertToken = $pdo->prepare(
                "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, NOW() + INTERVAL '30 minutes')"
            );
            $insertToken->execute([
                'user_id' => (int) $userId,
                'token_hash' => $tokenHash,
            ]);

            $_SESSION['password_reset_link'] = 'pages/reset-password.php?token=' . rawurlencode($token);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($exception->getMessage());
        header('Location: forgot-password.php?status=database_error');
        exit;
    }

    header('Location: forgot-password.php?status=reset_link_sent');
    exit;
}

$status = (string) ($_GET['status'] ?? '');
$statusMessage = match ($status) {
    'invalid_data' => 'Please enter a valid email address.',
    'database_error' => 'The service is temporarily unavailable. Please try again later.',
    'reset_link_sent' => 'If an account with this email exists, a reset link has been created.',
    default => '',
};
$resetLink = $_SESSION['password_reset_link'] ?? null;
unset($_SESSION['password_reset_link']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — KinoMonster</title>
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/header.css">
  <link rel="stylesheet" href="css/footer.css">
  <script src="js/main.js" defer></script>
  <script src="js/formValidation.js" defer></script>
</head>
<body>
<header>
  <nav class="header-nav nav-desktop" aria-label="Main navigation">
    <div class="header-nav-elems default-container">
      <ul><li><a href="project.html" class="logo"><img src="photos/logo_kinomonster.svg" alt="KinoMonster"></a></li></ul>
      <ul class="second-menu">
        <li><a href="project.html">HOME</a></li>
        <li><a href="pages/search.php">MOVIES</a></li>
        <li><a href="login.html" class="active" aria-current="page">LOG IN</a></li>
        <li><a href="register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
  <nav class="nav-mobile" aria-label="Mobile navigation">
    <div class="header-nav-elems-mobile">
      <ul>
        <li class="mobile-btn"><img src="photos/menuicon.svg" alt="Menu"></li>
        <li><a href="project.html" class="logo"><img src="photos/logo_kinomonster.svg" alt="KinoMonster"></a></li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="project.html">HOME</a></li>
        <li><a href="pages/search.php">MOVIES</a></li>
        <li><a href="login.html" class="active" aria-current="page">LOG IN</a></li>
        <li><a href="register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header>

<main>
  <section class="login">
    <div class="form-container">
      <p>Reset Password</p>
      <form id="forgotPasswordForm" action="forgot-password.php" method="post">
        <?php if ($statusMessage !== ''): ?>
          <p class="form-status" role="status"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <label for="resetEmail">Email</label>
        <input id="resetEmail" type="email" name="email" autocomplete="email" required>
        <input type="submit" value="Send Reset Link">
      </form>

      <?php if (is_string($resetLink)): ?>
        <div class="dev-reset-link">
          Local development link:<br>
          <a href="<?= htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') ?>">Open password reset form</a>
          <br><small>This single-use link expires in 30 minutes.</small>
        </div>
      <?php endif; ?>

      <p>Remembered your password? <a href="login.html">Log In</a></p>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="footer-content">
    <div class="footer-section"><h4>Main links</h4><a href="project.html">Home</a><a href="pages/search.php">Movies</a></div>
    <div class="footer-section"><h4>Account</h4><a href="login.html">Log In</a><a href="register.html">Register</a></div>
  </div>
  <div class="social-media">
    <span>Follow us via social media:</span>
    <div class="icons">
      <a href="https://www.youtube.com" target="_blank" rel="noopener"><img src="photos/youtube.svg" alt="YouTube"></a>
      <a href="https://www.facebook.com" target="_blank" rel="noopener"><img src="photos/facebook.svg" alt="Facebook"></a>
      <a href="https://www.instagram.com" target="_blank" rel="noopener"><img src="photos/instagramm.svg" alt="Instagram"></a>
      <a href="https://vk.com" target="_blank" rel="noopener"><img src="photos/vk.svg" alt="VK"></a>
      <a href="https://telegram.org" target="_blank" rel="noopener"><img src="photos/telegramm.svg" alt="Telegram"></a>
    </div>
  </div>
  <div class="footer-bottom"><p>© KinoMonster.com All rights reserved.</p></div>
</footer>
</body>
</html>
