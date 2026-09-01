<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/movies_schema.php';
$c = getDBConnection();
ensureMoviesSchema($c);
echo "Schema OK\n";
