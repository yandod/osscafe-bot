<?php
require 'vendor/autoload.php';

$url = parse_url(getenv('DATABASE_URL'));
$dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));

try {
    $db = new PDO($dsn, $url['user'], $url['pass']);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit;
}

$stmt = $db->prepare("SELECT * FROM LINE_USERS");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result, JSON_PRETTY_PRINT);
