<?php

declare(strict_types=1);

require __DIR__ . '/../config/session.php';

// Админ-панель доступна только вошедшему пользователю
if (!isset($_SESSION['user'])) {
    header('Location: ../login.html');
    exit;
}

require __DIR__ . '/../data/movie_store.php';
$movies = loadMovies();
$statuses = ['pending', 'confirmed', 'cancelled'];
$isAdmin = false;
$databaseError = false;
$users = [];
$bookings = [];
$editingMovieId = (string) ($_GET['edit_movie'] ?? '');
$editingMovie = $movies[$editingMovieId] ?? null;

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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? '');

            if (in_array($action, ['create_movie', 'update_movie', 'delete_movie'], true)) {
                $movieId = (string) ($_POST['movie_id'] ?? '');

                if ($action === 'delete_movie') {
                    if (!isset($movies[$movieId])) {
                        header('Location: admin.php?status=invalid_movie#movies');
                        exit;
                    }

                    unset($movies[$movieId]);
                    saveMovies($movies);
                    header('Location: admin.php?status=movie_deleted#movies');
                    exit;
                }

                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $genre = trim((string) ($_POST['genre'] ?? ''));
                $duration = trim((string) ($_POST['duration'] ?? ''));
                $sessionsInput = trim((string) ($_POST['sessions'] ?? ''));
                $sessions = array_values(array_unique(array_filter(
                    array_map('trim', explode(',', $sessionsInput)),
                    static fn (string $session): bool => preg_match('/^\d{2}:\d{2}$/', $session) === 1
                )));

                if ($title === '' || strlen($title) > 255
                    || $description === '' || $genre === '' || $duration === ''
                    || $sessions === []) {
                    header('Location: admin.php?status=invalid_movie#movies');
                    exit;
                }

                if ($action === 'create_movie') {
                    $movieId = movieSlug($title, $movies);
                    $booked = array_fill_keys($sessions, []);
                } elseif (!isset($movies[$movieId])) {
                    header('Location: admin.php?status=invalid_movie#movies');
                    exit;
                } else {
                    $booked = $movies[$movieId]['booked'] ?? [];
                    foreach ($sessions as $session) {
                        $booked[$session] = $booked[$session] ?? [];
                    }
                }

                $image = $movies[$movieId]['image'] ?? '';
                if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['poster']['error'] !== UPLOAD_ERR_OK
                        || $_FILES['poster']['size'] > 5 * 1024 * 1024) {
                        header('Location: admin.php?status=invalid_poster#movies');
                        exit;
                    }

                    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $fileInfo->file($_FILES['poster']['tmp_name']);
                    $extensions = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (!isset($extensions[$mime])) {
                        header('Location: admin.php?status=invalid_poster#movies');
                        exit;
                    }

                    $filename = $movieId . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
                    $targetPath = __DIR__ . '/../photos/' . $filename;
                    if (!move_uploaded_file($_FILES['poster']['tmp_name'], $targetPath)) {
                        throw new RuntimeException('Unable to save movie poster.');
                    }
                    $image = $filename;
                }

                if ($image === '') {
                    header('Location: admin.php?status=invalid_poster#movies');
                    exit;
                }

                $movies[$movieId] = [
                    'title' => $title,
                    'image' => $image,
                    'description' => $description,
                    'genre' => $genre,
                    'duration' => $duration,
                    'sessions' => $sessions,
                    'booked' => $booked,
                    'managed' => true,
                ];
                saveMovies($movies);
                header('Location: admin.php?status=' . ($action === 'create_movie' ? 'movie_created' : 'movie_updated') . '#movies');
                exit;
            }

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
    'movie_created' => ['success', 'Movie has been created.'],
    'movie_updated' => ['success', 'Movie has been updated.'],
    'movie_deleted' => ['success', 'Movie has been deleted.'],
    'invalid_movie' => ['error', 'Please fill in all movie fields correctly.'],
    'invalid_poster' => ['error', 'Poster must be a JPEG, PNG or WebP image up to 5 MB.'],
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
          <a href="../project.php" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu">
        <li><a href="../project.php">HOME</a></li>
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
          <a href="../project.php" class="logo">
            <img src="../photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="../project.php">HOME</a></li>
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
      <a class="text-button" href="../project.php">BACK TO HOME</a>
    </section>
  <?php else: ?>
    <div class="admin-layout">
      <aside class="admin-sidebar">
        <p class="admin-title">ADMIN PANEL</p>
        <nav aria-label="Admin navigation">
          <ul>
            <li><a href="#dashboard">Dashboard</a></li>
            <li><a href="#users">Users</a></li>
            <li><a href="#movies">Movies</a></li>
            <li><a href="#bookings">Bookings</a></li>
            <li><a href="logout.php">Logout</a></li>
          </ul>
        </nav>
      </aside>

      <div class="admin-content">
        <?php if ($message !== null): ?>
          <p class="account-message <?= $message[0] ?>" role="status"><?= e($message[1]) ?></p>
        <?php endif; ?>

        <section class="account-panel" id="dashboard">
          <p class="lbl-p">Dashboard</p>
          <ul class="stat-cards">
            <li><span><?= count($users) ?></span>Users</li>
            <li><span><?= count($bookings) ?></span>Bookings</li>
            <li><span><?= count($movies) ?></span>Movies</li>
          </ul>
        </section>

        <section class="account-panel" id="movies">
          <p class="lbl-p"><?= $editingMovie === null ? 'Create movie' : 'Edit movie' ?></p>
          <form class="account-form movie-form" action="admin.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $editingMovie === null ? 'create_movie' : 'update_movie' ?>">
            <?php if ($editingMovie !== null): ?>
              <input type="hidden" name="movie_id" value="<?= e($editingMovieId) ?>">
            <?php endif; ?>
            <div>
              <label for="movie-title">Title</label>
              <input id="movie-title" type="text" name="title" maxlength="255" required value="<?= e((string) ($editingMovie['title'] ?? '')) ?>">
            </div>
            <div>
              <label for="movie-genre">Genre</label>
              <input id="movie-genre" type="text" name="genre" required value="<?= e((string) ($editingMovie['genre'] ?? '')) ?>">
            </div>
            <div>
              <label for="movie-duration">Duration</label>
              <input id="movie-duration" type="text" name="duration" placeholder="120 min" required value="<?= e((string) ($editingMovie['duration'] ?? '')) ?>">
            </div>
            <div class="movie-form-wide">
              <label for="movie-description">Description</label>
              <textarea id="movie-description" name="description" rows="4" required><?= e((string) ($editingMovie['description'] ?? '')) ?></textarea>
            </div>
            <div>
              <label for="movie-sessions">Sessions</label>
              <input id="movie-sessions" type="text" name="sessions" placeholder="18:30, 21:15" required value="<?= e(implode(', ', $editingMovie['sessions'] ?? [])) ?>">
            </div>
            <div>
              <label for="movie-poster">Poster<?= $editingMovie !== null ? ' (optional)' : '' ?></label>
              <input id="movie-poster" type="file" name="poster" accept="image/jpeg,image/png,image/webp" <?= $editingMovie === null ? 'required' : '' ?>>
            </div>
            <button type="submit"><?= $editingMovie === null ? 'CREATE MOVIE' : 'SAVE CHANGES' ?></button>
            <?php if ($editingMovie !== null): ?>
              <a class="text-button secondary-button" href="admin.php#movies">CANCEL</a>
            <?php endif; ?>
          </form>

          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">Poster</th>
                  <th scope="col">Title</th>
                  <th scope="col">Sessions</th>
                  <th scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($movies as $movieId => $movie): ?>
                  <tr>
                    <td><img class="admin-movie-poster" src="../photos/<?= e($movie['image']) ?>" alt=""></td>
                    <td><?= e($movie['title']) ?></td>
                    <td><?= e(implode(', ', $movie['sessions'])) ?></td>
                    <td>
                      <a class="small-button text-button" href="admin.php?edit_movie=<?= urlencode($movieId) ?>#movies">EDIT</a>
                      <form class="inline-form" action="admin.php" method="post">
                        <input type="hidden" name="action" value="delete_movie">
                        <input type="hidden" name="movie_id" value="<?= e($movieId) ?>">
                        <button class="small-button secondary-button" type="submit">DELETE</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
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
      <a href="../project.php">Home</a>
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
