<?php

declare(strict_types=1);

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$tokenIsValid = strlen($token) === 64 && ctype_xdigit($token);
$canReset = false;
$errorMessage = '';

if (!$tokenIsValid) {
    $errorMessage = 'This reset link is invalid.';
} else {
    try {
        $pdo = require __DIR__ . '/../config/database.php';
        $tokenHash = hash('sha256', $token);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string) ($_POST['password'] ?? '');
            $repeatPassword = (string) ($_POST['repeatPassword'] ?? '');

            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'SELECT id, user_id
                 FROM password_reset_tokens
                 WHERE token_hash = :token_hash
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute(['token_hash' => $tokenHash]);
            $resetToken = $statement->fetch();

            if (!$resetToken) {
                $pdo->rollBack();
                $errorMessage = 'This reset link is invalid, expired, or has already been used.';
            } elseif (strlen($password) < 8 || $password !== $repeatPassword) {
                $pdo->rollBack();
                $canReset = true;
                $errorMessage = 'Use at least 8 characters and enter the same password twice.';
            } else {
                $updateUser = $pdo->prepare(
                    'UPDATE users SET password_hash = :password_hash WHERE id = :user_id'
                );
                $updateUser->execute([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'user_id' => (int) $resetToken['user_id'],
                ]);

                $useTokens = $pdo->prepare(
                    'UPDATE password_reset_tokens
                     SET used_at = NOW()
                     WHERE user_id = :user_id AND used_at IS NULL'
                );
                $useTokens->execute(['user_id' => (int) $resetToken['user_id']]);
                $pdo->commit();

                header('Location: ../login.html?status=password_reset');
                exit;
            }
        } else {
            $statement = $pdo->prepare(
                'SELECT 1
                 FROM password_reset_tokens
                 WHERE token_hash = :token_hash
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1'
            );
            $statement->execute(['token_hash' => $tokenHash]);
            $canReset = $statement->fetchColumn() !== false;

            if (!$canReset) {
                $errorMessage = 'This reset link is invalid, expired, or has already been used.';
            }
        }
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($exception->getMessage());
        $canReset = false;
        $errorMessage = 'The service is temporarily unavailable. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Choose New Password — KinoMonster</title>
  <link rel="stylesheet" href="../css/main.css">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/footer.css">
  <script src="../js/main.js" defer></script>
  <script src="../js/formValidation.js" defer></script>
</head>
<body>
<header>
  <nav class="header-nav nav-desktop" aria-label="Main navigation">
    <div class="header-nav-elems default-container">
      <ul><li><a href="../project.html" class="logo"><img src="../photos/logo_kinomonster.svg" alt="KinoMonster"></a></li></ul>
      <ul class="second-menu">
        <li><a href="../project.html">HOME</a></li>
        <li><a href="search.php">MOVIES</a></li>
        <li><a href="../login.html" class="active" aria-current="page">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
  <nav class="nav-mobile" aria-label="Mobile navigation">
    <div class="header-nav-elems-mobile">
      <ul>
        <li class="mobile-btn"><img src="../photos/menuicon.svg" alt="Menu"></li>
        <li><a href="../project.html" class="logo"><img src="../photos/logo_kinomonster.svg" alt="KinoMonster"></a></li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="../project.html">HOME</a></li>
        <li><a href="search.php">MOVIES</a></li>
        <li><a href="../login.html" class="active" aria-current="page">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header>

<main>
  <section class="login">
    <div class="form-container">
      <p>Choose New Password</p>

      <?php if ($canReset): ?>
        <form id="resetPasswordForm" method="post">
          <?php if ($errorMessage !== ''): ?>
            <p class="form-status" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

          <label for="resetPassword">New Password</label>
          <input id="resetPassword" type="password" name="password" autocomplete="new-password" minlength="8" required>

          <label for="repeatPassword">Repeat Password</label>
          <input id="repeatPassword" type="password" name="repeatPassword" autocomplete="new-password" minlength="8" required>

          <input type="submit" value="Reset Password">
        </form>
      <?php else: ?>
        <p class="form-status" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <p><a href="forgot-password.php">Request another link</a> · <a href="../login.html">Log In</a></p>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="footer-content">
    <div class="footer-section"><h4>Main links</h4><a href="../project.html">Home</a><a href="search.php">Movies</a></div>
    <div class="footer-section"><h4>Account</h4><a href="../login.html">Log In</a><a href="../register.html">Register</a></div>
  </div>
  <div class="social-media">
    <span>Follow us via social media:</span>
    <div class="icons">
      <a href="https://www.youtube.com" target="_blank" rel="noopener"><img src="../photos/youtube.svg" alt="YouTube"></a>
      <a href="https://www.facebook.com" target="_blank" rel="noopener"><img src="../photos/facebook.svg" alt="Facebook"></a>
      <a href="https://www.instagram.com" target="_blank" rel="noopener"><img src="../photos/instagramm.svg" alt="Instagram"></a>
      <a href="https://vk.com" target="_blank" rel="noopener"><img src="../photos/vk.svg" alt="VK"></a>
      <a href="https://telegram.org" target="_blank" rel="noopener"><img src="../photos/telegramm.svg" alt="Telegram"></a>
    </div>
  </div>
  <div class="footer-bottom"><p>© KinoMonster.com All rights reserved.</p></div>
</footer>
</body>
</html>
