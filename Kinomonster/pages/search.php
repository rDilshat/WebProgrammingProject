<?php

declare(strict_types=1);

$query = trim((string) ($_GET['q'] ?? ''));
$genreFilter = trim((string) ($_GET['genre'] ?? ''));
$results = [];
$genres = [];
$databaseError = false;

try {
    $pdo = require __DIR__ . '/../config/database.php';
    $genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();

    $sql = 'SELECT DISTINCT m.id, m.slug, m.title, m.description,
                   m.duration_min, m.release_year, m.poster, m.trailer_url
            FROM movies m';
    $conditions = ['m.is_active = TRUE'];
    $parameters = [];

    if ($genreFilter !== '') {
        $sql .= ' JOIN movie_genres mg_filter ON mg_filter.movie_id = m.id
                  JOIN genres g_filter ON g_filter.id = mg_filter.genre_id';
        $conditions[] = 'g_filter.name = :genre';
        $parameters['genre'] = $genreFilter;
    }

    if ($query !== '') {
        $conditions[] = 'm.title ILIKE :query';
        $parameters['query'] = '%' . $query . '%';
    }

    $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY m.title';
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $results = $statement->fetchAll();

    $genreNames = [];
    if ($results !== []) {
        $movieIds = array_column($results, 'id');
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
            $genreNames[(int) $row['movie_id']][] = $row['name'];
        }
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $databaseError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Search — KinoMonster</title>
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
        <li><a href="search.php" class="active" aria-current="page">MOVIES</a></li>
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
        <li><a href="search.php" class="active" aria-current="page">MOVIES</a></li>
        <li><a href="../login.html">LOG IN</a></li>
        <li><a href="../register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header>
  <main class="flow-page default-container">
    <section class="movie-search">
      <p class="lbl-p">Search movies</p>
      <form action="search.php" method="get">
        <label for="search-query">Movie title</label>
        <input id="search-query" type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by title">
        <label for="search-genre">Genre</label>
        <select id="search-genre" name="genre">
          <option value="">All genres</option>
          <?php foreach ($genres as $genre): ?>
            <option value="<?= htmlspecialchars($genre['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $genreFilter === $genre['name'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($genre['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit">SEARCH</button>
      </form>
    </section>
    <section>
      <p class="lbl-p">Results<?= $query !== '' ? ' for “' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '”' : '' ?></p>
      <?php if ($databaseError): ?>
        <p class="empty-state">The movie database is temporarily unavailable.</p>
      <?php elseif ($results === []): ?>
        <p class="empty-state">Nothing found</p>
      <?php else: ?>
        <div class="movie-results">
          <?php foreach ($results as $movie): ?>
            <article class="movie-card">
              <a href="movie.php?id=<?= urlencode($movie['slug']) ?>"><img src="../photos/<?= htmlspecialchars($movie['poster'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> poster"></a>
              <h2><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></h2>
              <p><?= htmlspecialchars(isset($genreNames[(int) $movie['id']]) ? implode(', ', $genreNames[(int) $movie['id']]) : '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $movie['duration_min'], ENT_QUOTES, 'UTF-8') ?> min<?= $movie['release_year'] ? ' · ' . htmlspecialchars((string) $movie['release_year'], ENT_QUOTES, 'UTF-8') : '' ?></p>
              <a class="text-button" href="movie.php?id=<?= urlencode($movie['slug']) ?>">VIEW DETAILS</a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
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