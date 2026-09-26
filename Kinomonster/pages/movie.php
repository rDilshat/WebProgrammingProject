<?php

$movies = require __DIR__ . '/../data/movies.php';
$id = $_GET['id'] ?? '';
$movie = $movies[$id] ?? null;

if ($movie === null) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $movie ? htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') : 'Movie not found' ?> — KinoMonster</title>
  <link rel="stylesheet" href="../css/main.css">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/footer.css">
  <script src="../js/main.js" defer></script>
</head>
<body>
  <header><nav class="header-nav nav-desktop"><div class="header-nav-elems default-container"><ul><li><a href="../project.html" class="logo"><img src="../photos/logo_kinomonster.svg" alt="KinoMonster"></a></li></ul><ul class="second-menu"><li><a href="search.php">MOVIES</a></li><li><a href="search.php">SEARCH</a></li><li><a href="../login.html">LOG IN</a></li><li><a href="../register.html">REGISTER</a></li></ul></div></nav><nav class="nav-mobile"><div class="header-nav-elems-mobile"><ul><li class="mobile-btn"><img src="../photos/menuicon.svg" alt="Menu"></li><li><a href="../project.html" class="logo"><img src="../photos/logo_kinomonster.svg" alt="KinoMonster"></a></li></ul><ul class="second-menu-mobile"><li><a href="search.php">MOVIES</a></li><li><a href="search.php">SEARCH</a></li><li><a href="../login.html">LOG IN</a></li><li><a href="../register.html">REGISTER</a></li></ul></div></nav></header>
  <main class="flow-page default-container">
    <?php if ($movie === null): ?>
      <section class="empty-state"><h1>Movie not found</h1><a class="text-button" href="search.php">BACK TO SEARCH</a></section>
    <?php else: ?>
      <article class="movie-details">
        <img src="../photos/<?= htmlspecialchars($movie['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?> poster">
        <div><p class="lbl-p">Movie details</p><h1><?= htmlspecialchars($movie['title'], ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars($movie['description'], ENT_QUOTES, 'UTF-8') ?></p><p class="movie-meta"><?= htmlspecialchars($movie['genre'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($movie['duration'], ENT_QUOTES, 'UTF-8') ?></p></div>
      </article>
      <section class="booking-panel"><p class="lbl-p">Choose a date, session and seats</p><form action="booking.php" method="post"><input type="hidden" name="movie_id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"><label for="booking-date">Date</label><input id="booking-date" type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required><label for="session">Session</label><select id="session" name="session" required><?php foreach ($movie['sessions'] as $session): ?><option value="<?= htmlspecialchars($session, ENT_QUOTES, 'UTF-8') ?>" data-booked="<?= htmlspecialchars(implode(',', $movie['booked'][$session]), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($session, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><fieldset><legend>Seats: available, selected, occupied</legend><div class="seat-grid"><?php for ($seat = 1; $seat <= 24; $seat++): ?><label data-seat="<?= $seat ?>"><input type="checkbox" name="seats[]" value="<?= $seat ?>"><span><?= $seat ?></span></label><?php endfor; ?></div></fieldset><button type="submit">BOOK SEATS</button></form></section>
    <?php endif; ?>
  </main>
</body>
</html>