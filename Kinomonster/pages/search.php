<?php

$movies = require __DIR__ . '/../data/movies.php';
$query = trim($_GET['q'] ?? '');
$results = array_filter($movies, static function (array $movie) use ($query): bool {
    return $query === '' || stripos($movie['title'], $query) !== false;
});
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
    <nav class="header-nav nav-desktop"><div class="header-nav-elems default-container">
      <ul><li><a href="../project.html" class="logo"><img src="../photos/logo_kinomonster.svg" alt="KinoMonster"></a></li></ul>
      <ul class="second-menu"><li><a href="search.php">MOVIES</a></li><li><a href="search.php">SEARCH</a></li><li><a href="../login.html">LOG IN</a></li><li><a href="../register.html">REGISTER</a></li></ul>
    </div></nav>
    <nav class="nav-mobile"><div class="header-nav-elems-mobile">
      <ul><li class="mobile-btn"><img src="../photos/menuicon.svg" alt="Menu"></li><li><a href="../project.html" class="logo"><img src="../photos/logo_kinomonster.svg" alt="KinoMonster"></a></li></ul>
      <ul class="second-menu-mobile"><li><a href="search.php">MOVIES</a></li><li><a href="search.php">SEARCH</a></li><li><a href="../login.html">LOG IN</a></li><li><a href="../register.html">REGISTER</a></li></ul>
    </div></nav>
  </header>
  <main class="flow-page default-container">
    <section class="movie-search">
      <p class="lbl-p">Search movies</p>
      <form action="search.php" method="get">
        <label for="search-query">Movie title</label>
        <input id="search-query" type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by title" required>
        <button type="submit">SEARCH</button>
      </form>
    </section>
    <section>
      <p class="lbl-p">Results<?= $query !== '' ? ' for “' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '”' : '' ?></p>
      <?php if ($results === []): ?>
        <p class="empty-state">No movies found. Try another title.</p>
      <?php else: ?>
        <div class="movie-results">
          <?php foreach ($results as $id => $movie): ?>
            <article class="movie-card">
              <a href="movie.php?id=<?= urlencode($id) ?>"><img src="../photos/<?= htmlspecialchars($movie['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> poster"></a>
              <h2><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></h2>
              <p><?= htmlspecialchars($movie['genre'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($movie['duration'], ENT_QUOTES, 'UTF-8') ?></p>
              <a class="text-button" href="movie.php?id=<?= urlencode($id) ?>">VIEW DETAILS</a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>