<?php
// --- CONFIGURATION ---
$configFile = __DIR__ . '/config/config.ini';
if (!file_exists($configFile)) {
    throw new RuntimeException('Config file not found. Please create config/config.ini from config.ini.sample');
}

$config = parse_ini_file($configFile, true);
if ($config === false) {
    throw new RuntimeException('Error parsing config file.');
}

$connection = $config['connection'] ?? [];
$host = $connection['host'] ?? '';
$port = (int) ($connection['port'] ?? 3306);
$database = $connection['db'] ?? '';
$user = $connection['user'] ?? '';
$password = $connection['pass'] ?? '';

if ($host === '~' || $database === '~' || $user === '~' || $password === '~' || $host === '' || $database === '' || $user === '' || $password === '') {
    throw new RuntimeException('Config not set up. Please update config.ini');
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
$pdo = new PDO($dsn, $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Set timezone to ensure date calculations are consistent
date_default_timezone_set('UTC');

// --- HANDLE REQUESTS ---
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // --- LOAD ALL CURRENT CONTENT AS JSON ---
    // Rewritten for MySQL 5.7 to avoid CTEs and Window Functions
    $stmt = $pdo->query("
        SELECT dv.page_index, dv.content, dv.created_at
        FROM document_version dv
        INNER JOIN (
            SELECT page_index, MAX(document_version_id) AS max_id
            FROM document_version
            GROUP BY page_index
        ) latest ON dv.document_version_id = latest.max_id
        ORDER BY dv.page_index
    ");

    $output = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $output[] = [
            'index' => (int)$row['page_index'],
            'timestamp' => $row['created_at'],
            'content' => $row['content']
        ];
    }

    // Ensure consistent JSON headers
    header('Content-Type: application/json');
    echo json_encode($output);

} elseif ($method === 'POST') {
    // --- SAVE NEW CONTENT FOR A SPECIFIC INDEX ---
    $input = json_decode(file_get_contents('php://input'), true);
    $index = isset($input['index']) ? intval($input['index']) : null;
    $content = isset($input['content']) ? $input['content'] : '';

    if ($index === null || $index < 1 || $index > 10) {
        http_response_code(400);
        echo "Error: index required and must be 1-10";
        exit;
    }

    // Insert the new version
    $stmt = $pdo->prepare("INSERT INTO document_version (page_index, content) VALUES (:index, :content)");
    $stmt->execute([':index' => $index, ':content' => $content]);

    // Cleanup old versions (keep last 10)
    // Modified PDO placeholders to be unique, ensuring safety when PDO Emulate Prepares is disabled
    $cleanupStmt = $pdo->prepare("
        DELETE FROM document_version
        WHERE page_index = :index
        AND document_version_id NOT IN (
            SELECT document_version_id FROM (
                SELECT document_version_id
                FROM document_version
                WHERE page_index = :index_select
                ORDER BY created_at DESC, document_version_id DESC
                LIMIT 10
            ) AS t
        )
    ");
    $cleanupStmt->execute([
        ':index' => $index,
        ':index_select' => $index
    ]);

    echo "Content saved successfully.";
}
?>
