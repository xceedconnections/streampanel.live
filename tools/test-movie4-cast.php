<?php
require __DIR__ . '/../config/database.php';
$c = getDBConnection();
$row = $c->query("SELECT cast_data, genres, tags FROM movies WHERE id=4")->fetch_assoc();
echo "cast len: ".strlen($row['cast_data'])."\n";
echo "cast sample: ".substr($row['cast_data'],0,200)."\n";
$attr = htmlspecialchars($row['cast_data'], ENT_QUOTES, 'UTF-8');
echo "attr len: ".strlen($attr)."\n";
echo "json valid: ".(json_decode($row['cast_data']) !== null || $row['cast_data'] === '[]' ? 'yes' : 'no')."\n";
$check = $row['cast_data'];
echo "decode===null: ".(json_decode($check) === null ? 'yes' : 'no')."\n";
