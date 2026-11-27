<?php

declare(strict_types=1);

require __DIR__ . '/config/session.php';
require __DIR__ . '/config/movie_helpers.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.html');
    exit;
}

function movieAdminEscape(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function movieAdminPostString(string $key): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('The submitted form contains an invalid value.');
    }
    return trim($value);
}

function movieAdminSlug(string $title, PDO $pdo): string
{
    $base = strtolower(trim($title));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
    $base = trim(substr($base, 0, 100), '-');
    if ($base === '') {
        $base = 'movie';
    }

    $slug = $base;
    $suffix = 2;
    $statement = $pdo->prepare('SELECT 1 FROM movies WHERE slug = :slug');
    while (true) {
        $statement->execute(['slug' => $slug]);
        if ($statement->fetchColumn() === false) {
            return $slug;
        }
        $suffixText = '-' . $suffix++;
        $slug = rtrim(substr($base, 0, 100 - strlen($suffixText)), '-') . $suffixText;
    }
}

function storeMoviePoster(array $file, string $slug): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !isset($file['tmp_name'], $file['size'])
        || !is_string($file['tmp_name'])
        || (int) $file['size'] < 1
        || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Choose an image smaller than 5 MB.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Poster must be a JPEG, PNG, or WebP image.');
    }

    $filename = $slug . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    $target = __DIR__ . '/photos/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('The poster could not be saved. Please try again.');
    }
    return $filename;
}

$isAdmin = false;
$databaseError = false;
$errorMessage = '';
$message = '';
$genres = [];
$halls = [];
$movies = [];
$genreNames = [];
$movieShowtimes = [];
$editingMovie = null;
$postEditingMovieId = null;
$selectedGenreIds = [];
$formValues = [
    'title' => '',
    'description' => '',
    'duration_min' => '',
    'release_year' => '',
    'trailer_url' => '',
];
$uploadedPoster = null;

try {
    $pdo = require __DIR__ . '/config/database.php';
    $roleStatement = $pdo->prepare('SELECT role FROM users WHERE id = :id');
    $roleStatement->execute(['id' => (int) $_SESSION['user']['id']]);
    $isAdmin = $roleStatement->fetchColumn() === 'admin';
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $databaseError = true;
    http_response_code(503);
}

if (!$databaseError && !$isAdmin) {
    http_response_code(403);
}

if (!$databaseError && $isAdmin) {
    if (empty($_SESSION['movie_admin_csrf'])) {
        $_SESSION['movie_admin_csrf'] = bin2hex(random_bytes(32));
    }
    $csrfToken = (string) $_SESSION['movie_admin_csrf'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = '';
        try {
            $postedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
            if (!hash_equals($csrfToken, $postedToken)) {
                http_response_code(400);
                throw new InvalidArgumentException('The form expired. Reload the page and try again.');
            }

            $action = movieAdminPostString('action');
            if ($action === 'create_showtime') {
                $movieId = filter_var(movieAdminPostString('movie_id'), FILTER_VALIDATE_INT);
                $postEditingMovieId = $movieId !== false && $movieId > 0 ? $movieId : null;
                $hallId = filter_var(movieAdminPostString('hall_id'), FILTER_VALIDATE_INT);
                $startsAtInput = movieAdminPostString('starts_at');
                $priceInput = movieAdminPostString('price');

                if ($movieId === false || $movieId < 1 || $hallId === false || $hallId < 1) {
                    throw new InvalidArgumentException('Choose a valid movie and hall.');
                }
                $movieStatement = $pdo->prepare(
                    'SELECT id FROM movies WHERE id = :id AND is_active = TRUE'
                );
                $movieStatement->execute(['id' => $movieId]);
                if ($movieStatement->fetchColumn() === false) {
                    throw new InvalidArgumentException('Showtimes can only be added to an active movie.');
                }

                $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startsAtInput);
                $dateErrors = DateTimeImmutable::getLastErrors();
                if ($dateTime === false
                    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                    || $dateTime->format('Y-m-d\TH:i') !== $startsAtInput
                    || $dateTime <= new DateTimeImmutable()) {
                    throw new InvalidArgumentException('Choose a valid future date and time.');
                }

                if (preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $priceInput) !== 1 || (float) $priceInput < 0) {
                    throw new InvalidArgumentException('Enter a price from 0 to 999999.99.');
                }
                $hallStatement = $pdo->prepare('SELECT 1 FROM halls WHERE id = :id');
                $hallStatement->execute(['id' => $hallId]);
                if ($hallStatement->fetchColumn() === false) {
                    throw new InvalidArgumentException('Choose a hall that exists.');
                }

                $statement = $pdo->prepare(
                    'INSERT INTO showtimes (movie_id, hall_id, starts_at, price)
                     VALUES (:movie_id, :hall_id, :starts_at, :price)'
                );
                $statement->execute([
                    'movie_id' => $movieId,
                    'hall_id' => $hallId,
                    'starts_at' => $dateTime->format('Y-m-d H:i:s'),
                    'price' => $priceInput,
                ]);
                header('Location: admin-movies.php?edit=' . $movieId . '&status=showtime_created#showtime-form');
                exit;
            }

            if ($action === 'delete_showtime') {
                $showtimeId = filter_var(movieAdminPostString('showtime_id'), FILTER_VALIDATE_INT);
                $movieId = filter_var(movieAdminPostString('movie_id'), FILTER_VALIDATE_INT);
                $postEditingMovieId = $movieId !== false && $movieId > 0 ? $movieId : null;
                if ($showtimeId === false || $showtimeId < 1 || $movieId === false || $movieId < 1) {
                    throw new InvalidArgumentException('Select a valid upcoming showtime.');
                }

                $pdo->beginTransaction();
                $showtimeStatement = $pdo->prepare(
                    'SELECT id FROM showtimes
                     WHERE id = :showtime_id AND movie_id = :movie_id AND starts_at >= NOW()
                     FOR UPDATE'
                );
                $showtimeStatement->execute(['showtime_id' => $showtimeId, 'movie_id' => $movieId]);
                if ($showtimeStatement->fetchColumn() === false) {
                    $pdo->rollBack();
                    throw new InvalidArgumentException('That upcoming showtime no longer exists.');
                }
                $bookingStatement = $pdo->prepare(
                    'SELECT EXISTS (SELECT 1 FROM bookings WHERE showtime_id = :showtime_id)'
                );
                $bookingStatement->execute(['showtime_id' => $showtimeId]);
                if ($bookingStatement->fetchColumn()) {
                    $pdo->rollBack();
                    throw new InvalidArgumentException('This showtime has bookings and cannot be removed.');
                }
                $deleteStatement = $pdo->prepare('DELETE FROM showtimes WHERE id = :showtime_id');
                $deleteStatement->execute(['showtime_id' => $showtimeId]);
                $pdo->commit();
                header('Location: admin-movies.php?edit=' . $movieId . '&status=showtime_deleted#showtime-form');
                exit;
            }

            if ($action === 'delete_movie' || $action === 'restore_movie') {
                $movieId = filter_var(movieAdminPostString('movie_id'), FILTER_VALIDATE_INT);
                if ($movieId === false || $movieId < 1) {
                    throw new InvalidArgumentException('Select a valid movie.');
                }

                $active = $action === 'restore_movie';
                $statement = $pdo->prepare(
                    'UPDATE movies SET is_active = :is_active WHERE id = :id'
                );
                $statement->bindValue(':is_active', $active, PDO::PARAM_BOOL);
                $statement->bindValue(':id', $movieId, PDO::PARAM_INT);
                $statement->execute();
                if ($statement->rowCount() !== 1) {
                    throw new InvalidArgumentException('That movie no longer exists.');
                }
                header('Location: admin-movies.php?status=' . ($active ? 'restored' : 'deleted') . '#movie-list');
                exit;
            }

            if ($action !== 'save_movie') {
                throw new InvalidArgumentException('Choose a valid movie action.');
            }

            $movieIdInput = movieAdminPostString('movie_id');
            $movieId = $movieIdInput === '' ? false : filter_var($movieIdInput, FILTER_VALIDATE_INT);
            if ($movieIdInput !== '' && ($movieId === false || $movieId < 1)) {
                throw new InvalidArgumentException('Select a valid movie to edit.');
            }
            $isUpdate = $movieId !== false;
            $formValues = [
                'title' => movieAdminPostString('title'),
                'description' => movieAdminPostString('description'),
                'duration_min' => movieAdminPostString('duration_min'),
                'release_year' => movieAdminPostString('release_year'),
                'trailer_url' => movieAdminPostString('trailer_url'),
            ];
            $selectedGenreIds = [];
            $submittedGenreIds = $_POST['genre_ids'] ?? [];
            if (is_array($submittedGenreIds)) {
                foreach ($submittedGenreIds as $genreIdInput) {
                    if (is_string($genreIdInput) && ctype_digit($genreIdInput)) {
                        $selectedGenreIds[] = (int) $genreIdInput;
                    }
                }
                $selectedGenreIds = array_values(array_unique($selectedGenreIds));
            }
            $currentMovie = null;
            if ($isUpdate) {
                $movieStatement = $pdo->prepare(
                    'SELECT id, slug, poster, title, description, duration_min, release_year, trailer_url
                     FROM movies WHERE id = :id'
                );
                $movieStatement->execute(['id' => $movieId]);
                $currentMovie = $movieStatement->fetch();
                if ($currentMovie === false) {
                    throw new InvalidArgumentException('That movie no longer exists.');
                }
                $editingMovie = $currentMovie;
            }
            $titleLength = preg_match_all('/./us', $formValues['title'], $titleCharacters);
            if ($formValues['title'] === '' || $titleLength === false || $titleLength > 200) {
                throw new InvalidArgumentException('Enter a movie title of at most 200 characters.');
            }

            $duration = filter_var($formValues['duration_min'], FILTER_VALIDATE_INT);
            if ($duration === false || $duration < 1) {
                throw new InvalidArgumentException('Duration must be a positive number of minutes.');
            }

            $releaseYear = null;
            if ($formValues['release_year'] !== '') {
                $releaseYear = filter_var($formValues['release_year'], FILTER_VALIDATE_INT);
                if ($releaseYear === false || $releaseYear < 1888 || $releaseYear > 2100) {
                    throw new InvalidArgumentException('Release year must be between 1888 and 2100.');
                }
            }

            $trailerUrl = $formValues['trailer_url'];
            if ($trailerUrl !== '') {
                $trailerParts = parse_url($trailerUrl);
                $trailerHost = is_array($trailerParts) ? strtolower($trailerParts['host'] ?? '') : '';
                if (!is_array($trailerParts)
                    || ($trailerParts['scheme'] ?? '') !== 'https'
                    || !in_array($trailerHost, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)
                    || movieYouTubeEmbedUrl($trailerUrl) === null) {
                    throw new InvalidArgumentException('Enter a valid HTTPS YouTube video URL, or leave the field empty.');
                }
            }

            if (!is_array($submittedGenreIds)) {
                throw new InvalidArgumentException('Choose at least one valid genre.');
            }
            $validatedGenreIds = [];
            foreach ($submittedGenreIds as $genreIdInput) {
                if (!is_string($genreIdInput) || !ctype_digit($genreIdInput)) {
                    throw new InvalidArgumentException('Choose only valid genres.');
                }
                $genreId = filter_var($genreIdInput, FILTER_VALIDATE_INT);
                if ($genreId === false || $genreId < 1) {
                    throw new InvalidArgumentException('Choose only valid genres.');
                }
                $validatedGenreIds[] = $genreId;
            }
            $selectedGenreIds = array_values(array_unique($validatedGenreIds));
            if ($selectedGenreIds === []) {
                throw new InvalidArgumentException('Choose at least one genre.');
            }
            $genrePlaceholders = implode(',', array_fill(0, count($selectedGenreIds), '?'));
            $genreCheck = $pdo->prepare("SELECT COUNT(*) FROM genres WHERE id IN ($genrePlaceholders)");
            $genreCheck->execute($selectedGenreIds);
            if ((int) $genreCheck->fetchColumn() !== count($selectedGenreIds)) {
                throw new InvalidArgumentException('Choose only genres that exist in the database.');
            }

            $slug = $currentMovie['slug'] ?? movieAdminSlug($formValues['title'], $pdo);
            $poster = $currentMovie['poster'] ?? '';
            if (isset($_FILES['poster']) && ($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadedPoster = storeMoviePoster($_FILES['poster'], $slug);
                $poster = $uploadedPoster;
            }
            if ($poster === '') {
                throw new InvalidArgumentException('Choose a poster image for this movie.');
            }

            $pdo->beginTransaction();
            if ($isUpdate) {
                $statement = $pdo->prepare(
                    'UPDATE movies
                     SET title = :title, description = :description,
                         duration_min = :duration_min, release_year = :release_year,
                         poster = :poster, trailer_url = :trailer_url
                     WHERE id = :id'
                );
                $statement->execute([
                    'title' => $formValues['title'],
                    'description' => $formValues['description'],
                    'duration_min' => $duration,
                    'release_year' => $releaseYear,
                    'poster' => $poster,
                    'trailer_url' => $trailerUrl === '' ? null : $trailerUrl,
                    'id' => $movieId,
                ]);
                $movieId = (int) $currentMovie['id'];
                $statement = $pdo->prepare('DELETE FROM movie_genres WHERE movie_id = :movie_id');
                $statement->execute(['movie_id' => $movieId]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO movies
                     (slug, title, description, duration_min, release_year, poster, trailer_url)
                     VALUES (:slug, :title, :description, :duration_min, :release_year, :poster, :trailer_url)
                     RETURNING id'
                );
                $statement->execute([
                    'slug' => $slug,
                    'title' => $formValues['title'],
                    'description' => $formValues['description'],
                    'duration_min' => $duration,
                    'release_year' => $releaseYear,
                    'poster' => $poster,
                    'trailer_url' => $trailerUrl === '' ? null : $trailerUrl,
                ]);
                $movieId = (int) $statement->fetchColumn();
            }

            $genreInsert = $pdo->prepare(
                'INSERT INTO movie_genres (movie_id, genre_id) VALUES (:movie_id, :genre_id)'
            );
            foreach ($selectedGenreIds as $genreId) {
                $genreInsert->execute(['movie_id' => $movieId, 'genre_id' => $genreId]);
            }
            $pdo->commit();
            if ($isUpdate) {
                header('Location: admin-movies.php?edit=' . $movieId . '&status=updated#movie-list');
            } else {
                header('Location: admin-movies.php?edit=' . $movieId . '&status=created#showtime-form');
            }
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($uploadedPoster !== null && is_file(__DIR__ . '/photos/' . $uploadedPoster)) {
                unlink(__DIR__ . '/photos/' . $uploadedPoster);
            }
            error_log($exception->getMessage());
            if ($exception->getCode() === '23505' && $action === 'create_showtime') {
                $errorMessage = 'This movie already has a showtime at that date and time.';
            } else {
                $errorMessage = 'The movie or showtime could not be saved because of a database error. Please try again.';
            }
        } catch (RuntimeException|InvalidArgumentException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($uploadedPoster !== null && is_file(__DIR__ . '/photos/' . $uploadedPoster)) {
                unlink(__DIR__ . '/photos/' . $uploadedPoster);
            }
            $errorMessage = $exception->getMessage();
        }
    } elseif (is_string($_GET['status'] ?? null) && $_GET['status'] !== '') {
        $messages = [
            'created' => 'Movie created successfully.',
            'updated' => 'Movie updated successfully.',
            'deleted' => 'Movie deactivated. Its bookings and history were preserved.',
            'restored' => 'Movie restored to the active catalog.',
            'showtime_created' => 'Showtime added successfully.',
            'showtime_deleted' => 'Showtime removed.',
        ];
        $message = $messages[(string) $_GET['status']] ?? '';
    }

    try {
        $genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();
        $halls = $pdo->query('SELECT id, name, capacity FROM halls ORDER BY name')->fetchAll();
        $movies = $pdo->query(
            'SELECT id, slug, title, duration_min, release_year, poster,
                    CASE WHEN is_active THEN 1 ELSE 0 END AS is_active
             FROM movies
             ORDER BY is_active DESC, title'
        )->fetchAll();
        if ($movies !== []) {
            $movieIds = array_column($movies, 'id');
            $placeholders = implode(',', array_fill(0, count($movieIds), '?'));
            $genreStatement = $pdo->prepare(
                "SELECT mg.movie_id, g.name
                 FROM movie_genres mg
                 JOIN genres g ON g.id = mg.genre_id
                 WHERE mg.movie_id IN ($placeholders)
                 ORDER BY g.name"
            );
            $genreStatement->execute($movieIds);
            foreach ($genreStatement->fetchAll() as $row) {
                $genreNames[(string) $row['movie_id']][] = $row['name'];
            }
        }

        $editingMovieInput = is_string($_GET['edit'] ?? null)
            ? $_GET['edit']
            : ($postEditingMovieId === null ? null : (string) $postEditingMovieId);
        $editingMovieId = filter_var($editingMovieInput, FILTER_VALIDATE_INT);
        if ($editingMovieId !== false && $editingMovieId > 0) {
            $statement = $pdo->prepare(
                'SELECT id, title, description, duration_min, release_year, poster, trailer_url, is_active
                 FROM movies WHERE id = :id'
            );
            $statement->execute(['id' => $editingMovieId]);
            $editingMovie = $statement->fetch() ?: null;
            if ($editingMovie !== null) {
                $formValues = [
                    'title' => (string) $editingMovie['title'],
                    'description' => (string) $editingMovie['description'],
                    'duration_min' => (string) $editingMovie['duration_min'],
                    'release_year' => (string) ($editingMovie['release_year'] ?? ''),
                    'trailer_url' => (string) ($editingMovie['trailer_url'] ?? ''),
                ];
                $statement = $pdo->prepare(
                    'SELECT genre_id FROM movie_genres WHERE movie_id = :movie_id'
                );
                $statement->execute(['movie_id' => $editingMovieId]);
                $selectedGenreIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
                $statement = $pdo->prepare(
                    'SELECT s.id, s.starts_at, s.price, h.name AS hall_name,
                            EXISTS (
                                SELECT 1 FROM bookings b WHERE b.showtime_id = s.id
                            ) AS has_bookings
                     FROM showtimes s
                     JOIN halls h ON h.id = s.hall_id
                     WHERE s.movie_id = :movie_id AND s.starts_at >= NOW()
                     ORDER BY s.starts_at'
                );
                $statement->execute(['movie_id' => $editingMovieId]);
                $movieShowtimes = $statement->fetchAll();
            } else {
                $errorMessage = 'That movie could not be found.';
            }
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $databaseError = true;
        http_response_code(503);
    }
}

$csrfToken = (string) ($_SESSION['movie_admin_csrf'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Movie Management — KinoMonster</title>
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/header.css">
  <link rel="stylesheet" href="css/footer.css">
  <script src="js/main.js" defer></script>
  <script src="js/admin-movies.js" defer></script>
</head>
<body>
<header>
  <nav class="header-nav nav-desktop" aria-label="Main navigation">
    <div class="header-nav-elems default-container">
      <ul><li><a href="project.php" class="logo"><img src="photos/logo_kinomonster.svg" alt="KinoMonster"></a></li></ul>
      <ul class="second-menu">
        <li><a href="project.php">HOME</a></li>
        <li><a href="pages/search.php">MOVIES</a></li>
        <li><a href="login.html">LOG IN</a></li>
        <li><a href="register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
  <nav class="nav-mobile" aria-label="Mobile navigation">
    <div class="header-nav-elems-mobile">
      <ul>
        <li class="mobile-btn"><img src="photos/menuicon.svg" alt="Menu"></li>
        <li><a href="project.php" class="logo"><img src="photos/logo_kinomonster.svg" alt="KinoMonster"></a></li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="project.php">HOME</a></li>
        <li><a href="pages/search.php">MOVIES</a></li>
        <li><a href="login.html">LOG IN</a></li>
        <li><a href="register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header>

<main class="flow-page default-container">
  <?php if ($databaseError): ?>
    <section class="empty-state">
      <h1>Movie management is temporarily unavailable</h1>
      <p>The service is temporarily unavailable. Please try again later.</p>
    </section>
  <?php elseif (!$isAdmin): ?>
    <section class="empty-state">
      <h1>Access denied</h1>
      <p>This page is available to administrators only.</p>
      <a class="text-button" href="project.php">BACK TO HOME</a>
    </section>
  <?php else: ?>
    <div class="admin-layout">
      <aside class="admin-sidebar">
        <p class="admin-title">MOVIE MANAGEMENT</p>
        <nav aria-label="Movie management navigation">
          <ul>
            <li><a href="admin-movies.php#movie-form">Add movie</a></li>
            <?php if ($editingMovie !== null): ?>
              <li><a href="#movie-form">Edit movie</a></li>
            <?php endif; ?>
            <?php if ($editingMovie !== null): ?>
              <li><a href="#showtime-form">Manage showtimes</a></li>
            <?php endif; ?>
            <li><a href="#movie-list">All movies</a></li>
            <li><a href="pages/admin.php">Main admin panel</a></li>
          </ul>
        </nav>
      </aside>
      <div class="admin-content">
        <h1 class="admin-title">MOVIE MANAGEMENT</h1>
        <?php if ($message !== ''): ?>
          <p class="account-message success" role="status"><?= movieAdminEscape($message) ?></p>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
          <p class="account-message error" role="alert"><?= movieAdminEscape($errorMessage) ?></p>
        <?php endif; ?>

        <section id="movie-form" class="admin-movie-form">
          <h2><?= $editingMovie === null ? 'Add a movie' : 'Edit movie' ?></h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= movieAdminEscape($csrfToken) ?>">
            <input type="hidden" name="action" value="save_movie">
            <input type="hidden" name="movie_id" value="<?= movieAdminEscape($editingMovie['id'] ?? '') ?>">
            <label for="movie-title">Title</label>
            <input id="movie-title" name="title" type="text" maxlength="200" required value="<?= movieAdminEscape($formValues['title']) ?>">

            <label for="movie-description">Description</label>
            <textarea id="movie-description" name="description" rows="4"><?= movieAdminEscape($formValues['description']) ?></textarea>

            <div class="movie-form-row">
              <div>
                <label for="movie-duration">Duration (minutes)</label>
                <input id="movie-duration" name="duration_min" type="number" min="1" required value="<?= movieAdminEscape($formValues['duration_min']) ?>">
              </div>
              <div>
                <label for="movie-year">Release year</label>
                <input id="movie-year" name="release_year" type="number" min="1888" max="2100" value="<?= movieAdminEscape($formValues['release_year']) ?>">
              </div>
            </div>

            <label for="movie-poster">Poster <?= $editingMovie === null ? '(required)' : '(optional; leave empty to keep the current poster)' ?></label>
            <input id="movie-poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp" <?= $editingMovie === null ? 'required' : '' ?>>
            <?php if ($editingMovie !== null): ?>
              <p class="account-note">Current poster: <?= movieAdminEscape($editingMovie['poster']) ?></p>
            <?php endif; ?>

            <label for="movie-trailer">YouTube trailer URL (optional)</label>
            <input id="movie-trailer" name="trailer_url" type="url" maxlength="255" value="<?= movieAdminEscape($formValues['trailer_url']) ?>" placeholder="https://www.youtube.com/watch?v=...">

            <fieldset class="movie-genre-options">
              <legend>Genres (choose at least one)</legend>
              <?php foreach ($genres as $genre): ?>
                <label>
                  <input type="checkbox" name="genre_ids[]" value="<?= (int) $genre['id'] ?>" <?= in_array((int) $genre['id'], $selectedGenreIds, true) ? 'checked' : '' ?>>
                  <?= movieAdminEscape($genre['name']) ?>
                </label>
              <?php endforeach; ?>
            </fieldset>
            <div class="admin-movie-actions">
              <button type="submit"><?= $editingMovie === null ? 'CREATE MOVIE' : 'SAVE CHANGES' ?></button>
              <?php if ($editingMovie !== null): ?>
                <a class="text-button secondary-button" href="admin-movies.php">CANCEL</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <?php if ($editingMovie !== null && $editingMovie['is_active']): ?>
          <section id="showtime-form" class="admin-movie-form">
            <h2>Showtimes for <?= movieAdminEscape($editingMovie['title']) ?></h2>
            <?php if ($halls === []): ?>
              <p class="account-note">No halls are configured in the database, so a showtime cannot be added yet.</p>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= movieAdminEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="create_showtime">
                <input type="hidden" name="movie_id" value="<?= (int) $editingMovie['id'] ?>">
                <label for="showtime-hall">Hall</label>
                <select id="showtime-hall" name="hall_id" required>
                  <?php foreach ($halls as $hall): ?>
                    <option value="<?= (int) $hall['id'] ?>"><?= movieAdminEscape($hall['name']) ?> (<?= (int) $hall['capacity'] ?> seats)</option>
                  <?php endforeach; ?>
                </select>

                <label for="showtime-starts-at">Date and time</label>
                <input id="showtime-starts-at" name="starts_at" type="datetime-local" min="<?= movieAdminEscape(date('Y-m-d\TH:i', time() + 60)) ?>" required>

                <label for="showtime-price">Price (KZT)</label>
                <input id="showtime-price" name="price" type="number" min="0" max="999999.99" step="0.01" required>
                <button type="submit">ADD SHOWTIME</button>
              </form>
            <?php endif; ?>

            <h3>Upcoming sessions</h3>
            <?php if ($movieShowtimes === []): ?>
              <p class="account-note">No upcoming showtimes yet. Add one above to enable booking.</p>
            <?php else: ?>
              <div class="table-wrapper">
                <table class="data-table">
                  <thead><tr><th>Date and time</th><th>Hall</th><th>Price</th><th>Actions</th></tr></thead>
                  <tbody>
                  <?php foreach ($movieShowtimes as $showtime): ?>
                    <tr>
                      <td><?= movieAdminEscape(date('d.m.Y H:i', strtotime($showtime['starts_at']))) ?></td>
                      <td><?= movieAdminEscape($showtime['hall_name']) ?></td>
                      <td><?= movieAdminEscape(number_format((float) $showtime['price'], 2, '.', ' ')) ?> KZT</td>
                      <td>
                        <?php if (!$showtime['has_bookings']): ?>
                          <form method="post" data-showtime-action="delete">
                            <input type="hidden" name="csrf_token" value="<?= movieAdminEscape($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete_showtime">
                            <input type="hidden" name="movie_id" value="<?= (int) $editingMovie['id'] ?>">
                            <input type="hidden" name="showtime_id" value="<?= (int) $showtime['id'] ?>">
                            <button type="submit" class="danger-button">REMOVE</button>
                          </form>
                        <?php else: ?>
                          <span>Has bookings</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <section id="movie-list" class="admin-movie-list">
          <h2>All movies</h2>
          <div class="table-wrapper">
            <table class="data-table">
              <thead><tr><th>Title</th><th>Genres</th><th>Duration</th><th>Year</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach ($movies as $movie): ?>
                <tr>
                  <td><?= movieAdminEscape($movie['title']) ?></td>
                  <td><?= movieAdminEscape(implode(', ', $genreNames[(string) $movie['id']] ?? [])) ?></td>
                  <td><?= (int) $movie['duration_min'] ?> min</td>
                  <td><?= movieAdminEscape($movie['release_year'] ?? '—') ?></td>
                  <td><?= $movie['is_active'] ? 'Active' : 'Inactive' ?></td>
                  <td class="admin-movie-actions">
                    <a class="text-button" href="admin-movies.php?edit=<?= (int) $movie['id'] ?>#movie-form">EDIT</a>
                    <form method="post" data-movie-action="<?= $movie['is_active'] ? 'deactivate' : 'restore' ?>">
                      <input type="hidden" name="csrf_token" value="<?= movieAdminEscape($csrfToken) ?>">
                      <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                      <input type="hidden" name="action" value="<?= $movie['is_active'] ? 'delete_movie' : 'restore_movie' ?>">
                      <button type="submit" class="<?= $movie['is_active'] ? 'danger-button' : 'secondary-button' ?>">
                        <?= $movie['is_active'] ? 'DEACTIVATE' : 'RESTORE' ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </div>
  <?php endif; ?>
</main>

<footer class="footer">
  <div class="footer-content">
    <div class="footer-section">
      <h4>Main links</h4>
      <a href="project.php">Home</a>
      <a href="pages/search.php">Movies</a>
    </div>
    <div class="footer-section">
      <h4>Account</h4>
      <a href="pages/profile.php">Profile</a>
      <a href="pages/admin.php">Admin</a>
    </div>
  </div>
  <div class="footer-bottom"><p>© KinoMonster.com All rights reserved.</p></div>
</footer>
</body>
</html>
