<?php

declare(strict_types=1);

require __DIR__ . '/../config/session.php';

// Админ-панель доступна только вошедшему пользователю
if (!isset($_SESSION['user'])) {
    header('Location: ../login.html');
    exit;
}

$movies = require __DIR__ . '/../data/movies.php';
$statuses = ['pending', 'confirmed', 'cancelled'];
$isAdmin = false;
$databaseError = false;
$users = [];
$bookings = [];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = require __DIR__ . '/../config/database.php';

    // Роль проверяем по базе, а не по сессии: так снятие прав срабатывает сразу
    $statement = $pdo->prepare('SELECT role FROM users WHERE id = :id');
    $statement->execute(['id' => (int) $_SESSION['user']['id']]);
    $isAdmin = $statement->fetchColumn() === 'admin';

    if ($isAdmin) {
        // Изменение статуса бронирования
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $bookingId = (int) ($_POST['booking_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');

            if ($bookingId > 0 && in_array($status, $statuses, true)) {
                $statement = $pdo->prepare('UPDATE bookings SET status = :status WHERE id = :id');
                $statement->execute(['status' => $status, 'id' => $bookingId]);
                header('Location: admin.php?status=updated#bookings');
            } else {
                header('Location: admin.php?status=invalid#bookings');
            }
            exit;
        }

        $users = $pdo->query(
            'SELECT id, first_name, last_name, email, role, created_at
             FROM users
             ORDER BY id'
        )->fetchAll();

        $bookings = $pdo->query(
            'SELECT b.id, b.movie_id, b.booking_date, b.session_time, b.seats, b.status,
                    u.first_name, u.last_name, u.email
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             ORDER BY b.created_at DESC'
        )->fetchAll();
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $databaseError = true;
}

if ($databaseError) {
    http_response_code(503);
} elseif (!$isAdmin) {
    http_response_code(403);
}

$messages = [
    'updated' => ['success', 'Booking status has been updated.'],
    'invalid' => ['error', 'Please choose a valid booking and status.'],
];
$message = $messages[$_GET['status'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel — KinoMonster</title>
  <link rel="stylesheet" href="../css/main.css">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/footer.css">
  <script src="../js/main.js" defer></script>
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
  <?php if ($databaseError): ?>
    <section class="empty-state">
      <h1>Admin panel is temporarily unavailable</h1>
      <p>The service is temporarily unavailable. Please try again later.</p>
    </section>
  <?php elseif (!$isAdmin): ?>
    <section class="empty-state">
      <h1>Access denied</h1>
      <p>This page is available to administrators only.</p>
      <a class="text-button" href="../project.html">BACK TO HOME</a>
    </section>
  <?php else: ?>
    <div class="admin-layout">
      <aside class="admin-sidebar">
        <p class="admin-title">ADMIN PANEL</p>
        <nav aria-label="Admin navigation">
          <ul>
            <li><a href="#dashboard">Dashboard</a></li>
            <li><a href="#users">Users</a></li>
            <li><a href="#bookings">Bookings</a></li>
            <li><a href="logout.php">Logout</a></li>
          </ul>
        </nav>
      </aside>

      <div class="admin-content">
        <section class="account-panel" id="dashboard">
          <p class="lbl-p">Dashboard</p>
          <ul class="stat-cards">
            <li><span><?= count($users) ?></span>Users</li>
            <li><span><?= count($bookings) ?></span>Bookings</li>
            <li><span><?= count($movies) ?></span>Movies</li>
          </ul>
        </section>

        <section class="account-panel" id="users">
          <p class="lbl-p">Users</p>
          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">ID</th>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Role</th>
                  <th scope="col">Registered</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td><?= e((string) $user['id']) ?></td>
                    <td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e(ucfirst($user['role'])) ?></td>
                    <td><?= e(date('d.m.Y', strtotime($user['created_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="account-panel" id="bookings">
          <p class="lbl-p">Bookings</p>

          <?php if ($message !== null): ?>
            <p class="account-message <?= $message[0] ?>" role="status"><?= e($message[1]) ?></p>
          <?php endif; ?>

          <?php if ($bookings === []): ?>
            <p class="account-note">There are no bookings yet.</p>
          <?php else: ?>
            <div class="table-wrapper">
              <table class="data-table">
                <thead>
                  <tr>
                    <th scope="col">ID</th>
                    <th scope="col">User</th>
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
                      <td><?= e($booking['first_name'] . ' ' . $booking['last_name']) ?><br><small><?= e($booking['email']) ?></small></td>
                      <td><?= e($movies[$booking['movie_id']]['title'] ?? $booking['movie_id']) ?></td>
                      <td><?= e(date('d.m.Y', strtotime($booking['booking_date']))) ?></td>
                      <td><?= e($booking['session_time']) ?></td>
                      <td><?= e($booking['seats']) ?></td>
                      <td>
                        <form class="status-form" action="admin.php" method="post">
                          <input type="hidden" name="booking_id" value="<?= e((string) $booking['id']) ?>">
                          <label class="visually-hidden" for="status-<?= e((string) $booking['id']) ?>">Status of booking #<?= e((string) $booking['id']) ?></label>
                          <select id="status-<?= e((string) $booking['id']) ?>" name="status">
                            <?php foreach ($statuses as $status): ?>
                              <option value="<?= $status ?>" <?= $status === $booking['status'] ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="small-button">SAVE</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>
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
