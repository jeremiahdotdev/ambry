#!/usr/bin/env php
<?php

declare(strict_types=1);

$args = array_slice($argv, 1);
$verbose = in_array('--verbose', $args, true);
$database = $args[0] ?? dirname(__DIR__).'/database.sqlite';

if ($database === '--verbose') {
    $database = $args[1] ?? dirname(__DIR__).'/database.sqlite';
}

if (! is_file($database)) {
    fwrite(STDERR, "Database not found: {$database}\n");
    exit(1);
}

$pdo = new PDO('sqlite:'.$database, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$rows = $pdo->query('select id, life_dates from saints where life_dates is not null')->fetchAll();
$updates = [];

foreach ($rows as $row) {
    $current = trim((string) $row['life_dates']);

    if ($current === '' || preg_match('/\b(?:AD|BC)\b/i', $current)) {
        continue;
    }

    if (! preg_match('/\d/', $current)) {
        continue;
    }

    $updates[] = [
        'id' => $row['id'],
        'from' => $current,
        'to' => $current.' AD',
    ];
}

$pdo->beginTransaction();

$statement = $pdo->prepare('update saints set life_dates = :life_dates where id = :id');

foreach ($updates as $update) {
    $statement->execute([
        'id' => $update['id'],
        'life_dates' => $update['to'],
    ]);
}

$pdo->commit();

if ($verbose) {
    foreach ($updates as $update) {
        echo "{$update['id']}: {$update['from']} -> {$update['to']}\n";
    }
}

echo count($updates).' life_dates values normalized.'."\n";
