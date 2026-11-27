<?php

declare(strict_types=1);

require __DIR__ . '/config/movie_helpers.php';

$movies = [];
$databaseError = false;

try {
    $pdo = require __DIR__ . '/config/database.php';
    $statement = $pdo->query(
        'SELECT id, slug, title, description, duration_min, release_year, poster, trailer_url
         FROM movies
         WHERE is_active = TRUE
         ORDER BY created_at DESC
         LIMIT 14'
    );
    $movies = $statement->fetchAll();

    $genreNames = [];
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
            $genreNames[(int) $row['movie_id']][] = $row['name'];
        }
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $databaseError = true;
}

$heroOrder = ['fight-club', 'joker', 'no-country-for-old-men', 'blade-runner'];
$moviesBySlug = [];
foreach ($movies as $movie) {
    $moviesBySlug[$movie['slug']] = $movie;
}
$heroMovies = [];
foreach ($heroOrder as $slug) {
    if (isset($moviesBySlug[$slug])) {
        $heroMovies[] = $moviesBySlug[$slug];
    }
}
$catalogMovies = $movies;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kinomonster</title>

    <link rel = "stylesheet" href = "css/main.css">
    <link rel = "stylesheet" href = "css/header.css">
    <link rel = "stylesheet" href = "css/footer.css">

    <script src="js/main.js" defer></script>
</head>
<body>
<header>
  <nav class="header-nav nav-desktop" aria-label="Main navigation">
    <div class="header-nav-elems default-container">
      <ul>
        <li>
          <a href="project.php" class="logo">
            <img src="photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu">
        <li><a href="project.php" class="active" aria-current="page">HOME</a></li>
        <li><a href="pages/search.php">MOVIES</a></li>
        <li><a href="login.html">LOG IN</a></li>
        <li><a href="register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>

  <nav class="nav-mobile" aria-label="Mobile navigation">
    <div class="header-nav-elems-mobile">
      <ul>
        <li class="mobile-btn">
          <img src="photos/menuicon.svg" alt="Menu">
        </li>
        <li>
          <a href="project.php" class="logo">
            <img src="photos/logo_kinomonster.svg" alt="KinoMonster">
          </a>
        </li>
      </ul>
      <ul class="second-menu-mobile">
        <li><a href="project.php" class="active" aria-current="page">HOME</a></li>
        <li><a href="pages/search.php">MOVIES</a></li>
        <li><a href="login.html">LOG IN</a></li>
        <li><a href="register.html">REGISTER</a></li>
      </ul>
    </div>
  </nav>
</header>
<main id = "heroSection">
  <section>
    <?php if ($databaseError): ?>
      <div class="default-container empty-state">
        <h1>Movies are temporarily unavailable</h1>
        <p>The database has not been initialized or is temporarily unavailable.</p>
      </div>
    <?php elseif ($heroMovies !== []): ?>
      <ul class="hero">
        <?php foreach ($heroMovies as $index => $movie): ?>
          <?php
            $heroImages = [
                'fight-club' => 'fight-club-hero.jpg',
                'joker' => 'joker.jpg',
                'no-country-for-old-men' => 'no-country-for-old-men.jpg',
                'blade-runner' => 'bladerunner.jpg',
            ];
            $heroImage = $heroImages[$movie['slug']] ?? $movie['poster'];
          ?>
          <li class="<?= $index === 0 ? 'active' : '' ?>">
            <img class="main-slider-img"
                 src="photos/<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> poster">
            <div class="default-container">
              <div class="hero-movie">
                <p><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= htmlspecialchars($movie['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="hero-actions">
                  <a class="hero-button" href="pages/movie.php?id=<?= urlencode($movie['slug']) ?>">WATCH NOW</a>
                  <?php if (movieYouTubeEmbedUrl($movie['trailer_url']) !== null): ?>
                    <a class="hero-button hero-trailer-button"
                       href="pages/movie.php?id=<?= urlencode($movie['slug']) ?>#trailer">WATCH TRAILER</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="default-container">
        <div class="next-main-slider-dots">
          <?php foreach ($heroMovies as $index => $movie): ?>
            <span class="<?= $index === 0 ? 'active' : '' ?>"></span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="fader-bg">
      <div class="fader-gradient-0"></div>
      <div class="fader-gradient-1"></div>
    </div>
  </section>

  <section class="default-container movie-search">
    <p class="lbl-p">Find a movie</p>
    <form action="pages/search.php" method="get">
      <label for="home-search">Movie title</label>
      <input id="home-search" type="search" name="q" placeholder="Search by title" required>
      <button type="submit">SEARCH</button>
    </form>
  </section>

  <?php if (!$databaseError): ?>
    <section class="default-container">
      <p class="lbl-p">Movies</p>
      <?php if ($catalogMovies === []): ?>
        <p class="empty-state">Nothing found</p>
      <?php else: ?>
        <div class="posters">
          <?php foreach ($catalogMovies as $movie): ?>
            <?php $embedUrl = movieYouTubeEmbedUrl($movie['trailer_url']); ?>
            <article class="poster database-poster">
              <a href="pages/movie.php?id=<?= urlencode($movie['slug']) ?>">
                <img src="photos/<?= htmlspecialchars($movie['poster'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> poster">
              </a>
              <div class="database-poster-info">
                <h2><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars(isset($genreNames[(int) $movie['id']]) ? implode(', ', $genreNames[(int) $movie['id']]) : '', ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($embedUrl !== null): ?>
                  <a class="text-button" href="pages/movie.php?id=<?= urlencode($movie['slug']) ?>#trailer">Watch trailer</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
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
        <a href="login.html">Log In</a>
        <a href="register.html">Register</a>
      </div>
    </div>
  
    <div class="social-media">
      <span>Follow us via social media:</span>
      <div class="icons">
        <a href="https://www.youtube.com" target="_blank" rel="noopener"><img src="photos/youtube.svg" alt="YouTube"></a>
        <a href="https://www.facebook.com" target="_blank" rel="noopener"><img src="photos/facebook.svg" alt="Facebook"></a>
        <a href="https://www.instagram.com" target="_blank" rel="noopener"><img src="photos/instagramm.svg" alt="Instagram"></a>
        <a href="https://vk.com" target="_blank" rel="noopener"><img src="photos/vk.svg" alt="VK"></a>
        <a href="https://telegram.org" target="_blank" rel="noopener"><img src="photos/telegramm.svg" alt="Telegram"></a> <!--Убрал assets. Все фото перекинул в public-->
      </div>
    </div>
  
    <div class="footer-bottom">
      <p>© KinoMonster.com All rights reserved.</p>
    </div>
</footer>
</body>
</html>
