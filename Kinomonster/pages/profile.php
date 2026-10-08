<?php

declare(strict_types=1);

require __DIR__ . '/../config/session.php';

// Профиль доступен только вошедшему пользователю
if (!isset($_SESSION['user'])) {
    header('Location: ../login.html');
    exit;
}

$movies = require __DIR__ . '/../data/movies.php';
$userId = (int) $_SESSION['user']['id'];
$errors = [];
$user = null;
$bookings = [];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = require __DIR__ . '/../config/database.php';

    // Сохранение формы "Edit Profile"
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstName = trim((string) ($_POST['firstName'] ?? ''));
        $lastName = trim((string) ($_POST['lastName'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if ($firstName === '' || strlen($firstName) > 100) {
            $errors[] = 'Please enter your first name.';
        }
        if ($lastName === '' || strlen($lastName) > 100) {
            $errors[] = 'Please enter your last name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($errors === []) {
            try {
                $statement = $pdo->prepare(
                    'UPDATE users
                     SET first_name = :first_name, last_name = :last_name, email = :email
                     WHERE id = :id'
                );
                $statement->execute([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'id' => $userId,
                ]);

                // Обновляем данные в сессии, чтобы меню показывало новое имя
                $_SESSION['user']['firstName'] = $firstName;
                $_SESSION['user']['lastName'] = $lastName;
                $_SESSION['user']['email'] = $email;

                header('Location: profile.php?status=updated');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23505') {
                    throw $exception;
                }
                $errors[] = 'An account with this email already exists.';
            }
        }
    }

    $statement = $pdo->prepare(
        'SELECT id, first_name, last_name, email, role, created_at
         FROM users
         WHERE id = :id'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch() ?: null;

    $statement = $pdo->prepare(
        'SELECT id, movie_id, booking_date, session_time, seats, status
         FROM bookings
         WHERE user_id = :user_id
         ORDER BY created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);
    $bookings = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
}

// Пользователь удалён из базы — завершаем сессию
if ($user === null && http_response_code() !== 503) {
    header('Location: logout.php');
    exit;
}

// При ошибке показываем введённые значения, иначе — данные из базы
$form = $errors !== [] ? [
    'first_name' => $_POST['firstName'] ?? '',
    'last_name' => $_POST['lastName'] ?? '',
    'email' => $_POST['email'] ?? '',
] : $user;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — KinoMonster</title>
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
      <ul>
        <li>
          <a href="../project.html" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu">
        <li><a href="../project.html">HOME</a></li>
        <li><a href="search.php">MOVIES</a></li>
        <li><a href="../login.html">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>

  <nav class="nav-mobile" aria-label="Mobile navigation">
    <div class="header-nav-elems-mobile">
      <ul>
        <li class="mobile-btn">
          <img src="../photos/menuicon.svg" alt="Menu">
        </li>
        <li>
          <a href="../project.html" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="../project.html">HOME</a></li>
        <li><a href="search.php">MOVIES</a></li>
        <li><a href="../login.html">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header>

<main class="flow-page default-container">
  <?php if ($user === null): ?>
    <section class="empty-state">
      <h1>Profile is temporarily unavailable</h1>
      <p>The service is temporarily unavailable. Please try again later.</p>
    </section>
  <?php else: ?>
    <section class="account-panel profile-card">
      <p class="lbl-p">My profile</p>

      <?php if (($_GET['status'] ?? '') === 'updated'): ?>
        <p class="account-message success" role="status">Your profile has been updated.</p>
      <?php endif; ?>

      <div class="profile-header">
        <div class="profile-avatar" aria-hidden="true">
          <?= e(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?>
        </div>
        <div>
          <h1><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h1>
          <dl class="profile-info">
            <dt>Email</dt>
            <dd><?= e($user['email']) ?></dd>
            <dt>Member since</dt>
            <dd><?= e(date('d.m.Y', strtotime($user['created_at']))) ?></dd>
            <dt>Bookings</dt>
            <dd><?= count($bookings) ?></dd>
          </dl>
        </div>
      </div>

      <details class="profile-edit" <?= $errors !== [] ? 'open' : '' ?>>
        <summary class="text-button">EDIT PROFILE</summary>

        <?php foreach ($errors as $error): ?>
          <p class="account-message error" role="alert"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form id="profileForm" class="account-form" action="profile.php" method="post">
          <div>
            <label for="profileFirstName">First Name</label>
            <input id="profileFirstName" type="text" name="firstName" maxlength="100" autocomplete="given-name" value="<?= e((string) $form['first_name']) ?>" required>
          </div>
          <div>
            <label for="profileLastName">Last Name</label>
            <input id="profileLastName" type="text" name="lastName" maxlength="100" autocomplete="family-name" value="<?= e((string) $form['last_name']) ?>" required>
          </div>
          <div>
            <label for="profileEmail">Email</label>
            <input id="profileEmail" type="email" name="email" maxlength="255" autocomplete="email" value="<?= e((string) $form['email']) ?>" required>
          </div>
          <button type="submit" class="small-button">SAVE CHANGES</button>
        </form>
      </details>

      <a class="text-button secondary-button" href="logout.php">LOG OUT</a>
    </section>

    <section class="account-panel" id="bookings">
      <p class="lbl-p">My bookings</p>

      <?php if ($bookings === []): ?>
        <p class="account-note">You have no bookings yet.</p>
        <a class="text-button" href="search.php">FIND A MOVIE</a>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Booking ID</th>
                <th scope="col">Movie</th>
                <th scope="col">Date</th>
                <th scope="col">Time</th>
                <th scope="col">Seats</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $booking): ?>
                <tr>
                  <td>#<?= e((string) $booking['id']) ?></td>
                  <td>
                    <a href="movie.php?id=<?= urlencode($booking['movie_id']) ?>">
                      <?= e($movies[$booking['movie_id']]['title'] ?? $booking['movie_id']) ?>
                    </a>
                  </td>
                  <td><?= e(date('d.m.Y', strtotime($booking['booking_date']))) ?></td>
                  <td><?= e($booking['session_time']) ?></td>
                  <td><?= e($booking['seats']) ?></td>
                  <td><span class="status-badge status-<?= e($booking['status']) ?>"><?= e(ucfirst($booking['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>

<footer class="footer">
  <div class="footer-content">
    <div class="footer-section">
      <h4>Main links</h4>
      <a href="../project.html">Home</a>
      <a href="search.php">Movies</a>
    </div>
    <div class="footer-section">
      <h4>Account</h4>
      <a href="../login.html">Log In</a>
      <a href="../register.html">Register</a>
    </div>
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

  <div class="footer-bottom">
    <p>© KinoMonster.com All rights reserved.</p>
  </div>
</footer>
</body>
</html>
