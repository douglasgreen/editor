<?php
// --- CONFIGURATION ---
// Store data in a CSV file in the current directory
$csvFile = __DIR__ . '/data.csv';
// Set timezone to ensure date calculations are consistent
date_default_timezone_set('UTC');

/**
 * Helper function to read all rows from the CSV safely.
 * Returns an array of rows where each row is [index, timestamp, content].
 * Legacy rows (two fields) will be interpreted as index 1.
 */
function readCsv($filepath) {
    if (!file_exists($filepath)) {
        return [];
    }
    $rows = [];
    $handle = fopen($filepath, 'r');
    if ($handle) {
        // Shared lock for reading
        if (flock($handle, LOCK_SH)) {
            while (($data = fgetcsv($handle)) !== FALSE) {
                $count = count($data);
                if ($count >= 3) {
                    $index = intval($data[0]);
                    $timestamp = $data[1];
                    $content = $data[2];
                } else {
                    continue;
                }
                $rows[] = [$index, $timestamp, $content];
            }
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
    return $rows;
}

/**
 * Helper function to write rows to the CSV safely.
 * Each row is an array [index, timestamp, content].
 */
function writeCsv($filepath, $rows) {
    $handle = fopen($filepath, 'w'); // 'w' truncates the file
    if ($handle) {
        // Exclusive lock for writing
        if (flock($handle, LOCK_EX)) {
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            flock($handle, LOCK_UN);
        }
        fclose($handle);
        return true;
    }
    return false;
}

/**
 * Helper function to filter rows to keep only the last 10 versions per index.
 * Returns an array of rows sorted by index and timestamp descending.
 */
function filterRows($rows) {
    $versionsPerIndex = [];
    foreach ($rows as $row) {
        $index = intval($row[0]);
        if ($index < 1 || $index > 10) {
            continue;
        }
        $versionsPerIndex[$index][] = $row;
    }

    $filteredRows = [];
    foreach ($versionsPerIndex as $index => $indexRows) {
        // Sort by timestamp descending
        usort($indexRows, function($a, $b) {
            return strtotime($b[1]) - strtotime($a[1]);
        });
        // Keep only the first 10 (most recent)
        $filteredRows = array_merge($filteredRows, array_slice($indexRows, 0, 10));
    }
    return $filteredRows;
}

// --- MAIN LOGIC ---

// 1. Load current data
$rows = readCsv($csvFile);

// 2. DATA CLEANUP & ORGANIZATION
// Keep the last 10 versions of content for each index (1-10).
$filteredRows = filterRows($rows);

// Find the most recent content for each index
$latestPerIndex = [];
foreach ($filteredRows as $row) {
    $index = intval($row[0]);
    if (!isset($latestPerIndex[$index])) {
        $latestPerIndex[$index] = $row; // The first one we encounter is the most recent due to sorting
    }
}

// --- HANDLE REQUESTS ---
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Write the cleaned version back so the file stays tidy
    writeCsv($csvFile, array_values($filteredRows));

    // --- LOAD ALL CURRENT CONTENT AS JSON ---
    $output = [];
    foreach ($latestPerIndex as $index => $entry) {
        $output[] = [
            'index' => $entry[0],
            'timestamp' => $entry[1],
            'content' => $entry[2]
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

    // Reload from disk to pick up any concurrent modifications
    $currentRows = readCsv($csvFile);

    // Append the new row
    $timestamp = date('Y-m-d H:i:s');
    $currentRows[] = [$index, $timestamp, $content];

    // Filter to keep only the last 10 versions per index
    $newRows = filterRows($currentRows);

    // Write everything back to the file
    if (writeCsv($csvFile, array_values($newRows))) {
        echo "Content saved successfully.";
    } else {
        http_response_code(500);
        echo "Error: Could not write to data file. Check permissions.";
    }
}
?>
