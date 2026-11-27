<?php

declare(strict_types=1);

function movieYouTubeEmbedUrl(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    $videoId = null;
    if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtube\.com\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $matches) === 1) {
        $videoId = $matches[1];
    }

    if ($videoId === null) {
        return null;
    }

    return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId);
}
