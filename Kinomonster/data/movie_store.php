<?php

declare(strict_types=1);

function loadMovies(): array
{
    $movies = require __DIR__ . '/movies.php';

    foreach ($movies as &$movie) {
        $movie['booked'] = $movie['booked'] ?? [];
        foreach ($movie['sessions'] ?? [] as $session) {
            $movie['booked'][$session] = $movie['booked'][$session] ?? [];
        }
    }
    unset($movie);

    return $movies;
}

function saveMovies(array $movies): void
{
    $temporaryPath = __DIR__ . '/movies.php.tmp';
    $contents = "<?php\n\nreturn " . var_export($movies, true) . ";\n";

    if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false
        || !rename($temporaryPath, __DIR__ . '/movies.php')) {
        @unlink($temporaryPath);
        throw new RuntimeException('Unable to save movie catalogue.');
    }
}

function movieSlug(string $title, array $movies, ?string $currentId = null): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    $slug = $slug !== '' ? $slug : 'movie';
    $baseSlug = $slug;
    $suffix = 2;

    while (isset($movies[$slug]) && $slug !== $currentId) {
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}
