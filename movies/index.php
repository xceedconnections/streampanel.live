<?php
/**
 * Apache DirectoryIndex fallback for /movies/
 * The physical movies/ folder would otherwise 404 when .htaccess is limited.
 * No domain/path hardcoding — just boots the listing page.
 */
require dirname(__DIR__) . '/movies.php';
