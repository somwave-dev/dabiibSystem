<?php
require_once __DIR__ . '/config.php';

$sqlFile = __DIR__ . '/../db/clinic.sql';
$marker = '-- Stored procedures for generated CRUD pages';
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    throw new RuntimeException('Could not read db/clinic.sql.');
}

$start = strpos($sql, $marker);
if ($start === false) {
    throw new RuntimeException('Stored procedure section was not found.');
}

$section = substr($sql, $start);
$end = strpos($section, '/*!40101 SET CHARACTER_SET_CLIENT');
if ($end !== false) {
    $section = substr($section, 0, $end);
}

$delimiter = ';';
$buffer = '';
$executed = 0;

foreach (preg_split('/\R/', $section) as $line) {
    $trimmed = trim($line);

    if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
        $delimiter = $matches[1];
        continue;
    }

    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }

    $buffer .= $line . PHP_EOL;
    $current = rtrim($buffer);

    if ($delimiter !== '' && str_ends_with($current, $delimiter)) {
        $statement = trim(substr($current, 0, -strlen($delimiter)));
        $buffer = '';

        if ($statement === '') {
            continue;
        }

        $conn->query($statement);
        $executed++;
    }
}

if (trim($buffer) !== '') {
    $conn->query(trim($buffer));
    $executed++;
}

echo 'Stored procedures installed. Statements executed: ' . $executed . PHP_EOL;
