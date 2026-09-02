<?php
/**
 * Physical movies/ directory exists on disk, so /movies/ is served from here
 * when Apache treats it as a real directory. Bootstrap the listing page.
 */
require dirname(__DIR__) . '/movies.php';
