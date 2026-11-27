<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond(405, ['success' => false, 'error' => 'Only GET requests are supported.']);
}

foreach (['q', 'genre', 'sort', 'direction', 'page', 'per_page'] as $parameterName) {
    if (isset($_GET[$parameterName]) && !is_string($_GET[$parameterName])) {
        respond(400, ['success' => false, 'error' => 'Search parameters must be single values.']);
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$genre = trim((string) ($_GET['genre'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'title');
$direction = strtolower((string) ($_GET['direction'] ?? 'asc'));
$pageInput = $_GET['page'] ?? '1';
$perPageInput = $_GET['per_page'] ?? '8';

$queryLength = preg_match_all('/./us', $query);
$genreLength = preg_match_all('/./us', $genre);
if ($queryLength === false || $queryLength > 200 || $genreLength === false || $genreLength > 50) {
    respond(400, ['success' => false, 'error' => 'Search filters are too long.']);
}

if (!in_array($sort, ['title', 'year', 'duration'], true)) {
    respond(400, ['success' => false, 'error' => 'Invalid sort option.']);
}

if (!in_array($direction, ['asc', 'desc'], true)) {
    respond(400, ['success' => false, 'error' => 'Invalid sort direction.']);
}

$page = filter_var($pageInput, FILTER_VALIDATE_INT);
$perPage = filter_var($perPageInput, FILTER_VALIDATE_INT);
if ($page === false || $page < 1 || $perPage === false || $perPage < 1 || $perPage > 24) {
    respond(400, ['success' => false, 'error' => 'Page must be positive and page size must be between 1 and 24.']);
}

$sortColumns = [
    'title' => 'm.title',
    'year' => 'm.release_year',
    'duration' => 'm.duration_min',
];
$orderBy = $sortColumns[$sort] . ' ' . strtoupper($direction);
if ($sort === 'year') {
    $orderBy .= ' NULLS LAST';
}
$orderBy .= ', m.id ASC';

$conditions = ['m.is_active = TRUE'];
$parameters = [];
if ($query !== '') {
    $conditions[] = 'm.title ILIKE :query';
    $parameters['query'] = '%' . $query . '%';
}
if ($genre !== '') {
    $conditions[] = 'EXISTS (
        SELECT 1
        FROM movie_genres mg_filter
        JOIN genres g_filter ON g_filter.id = mg_filter.genre_id
        WHERE mg_filter.movie_id = m.id AND g_filter.name = :genre
    )';
    $parameters['genre'] = $genre;
}
$where = implode(' AND ', $conditions);

try {
    $pdo = require __DIR__ . '/../config/database.php';
    $countStatement = $pdo->prepare("SELECT COUNT(*) FROM movies m WHERE $where");
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $totalPages = (int) ceil($total / $perPage);
    $page = $totalPages === 0 ? 1 : min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $statement = $pdo->prepare(
        "SELECT m.id, m.slug, m.title, m.duration_min, m.release_year, m.poster
         FROM movies m
         WHERE $where
         ORDER BY $orderBy
         LIMIT :limit OFFSET :offset"
    );
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $items = $statement->fetchAll();

    if ($items !== []) {
        $movieIds = array_column($items, 'id');
        $placeholders = implode(',', array_fill(0, count($movieIds), '?'));
        $genreStatement = $pdo->prepare(
            "SELECT mg.movie_id, g.name
             FROM movie_genres mg
             JOIN genres g ON g.id = mg.genre_id
             WHERE mg.movie_id IN ($placeholders)
             ORDER BY g.name"
        );
        $genreStatement->execute($movieIds);
        $genresByMovie = [];
        foreach ($genreStatement->fetchAll() as $row) {
            $genresByMovie[(string) $row['movie_id']][] = $row['name'];
        }
        foreach ($items as &$item) {
            $item['genres'] = $genresByMovie[(string) $item['id']] ?? [];
        }
        unset($item);
    }

    respond(200, [
        'success' => true,
        'data' => [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ],
    ]);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    respond(503, ['success' => false, 'error' => 'The movie database is temporarily unavailable.']);
}
