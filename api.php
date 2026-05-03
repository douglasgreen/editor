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
                } elseif ($count >= 2) {
                    // Legacy format: timestamp, content
                    $index = 1;
                    $timestamp = $data[0];
                    $content = $data[1];
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

// --- MAIN LOGIC ---

// 1. Load current data
$rows = readCsv($csvFile);

// 2. DATA CLEANUP
// Delete any entries older than 3 days.
$cutoffDate = strtotime('-3 days');

// Build per-index latest list, ignoring rows that are too old
$rowsPerIndex = [];
foreach ($rows as $row) {
    $index = intval($row[0]);
    // Only accept indices 1‑10
    if ($index < 1 || $index > 10) {
        continue;
    }
    $timestamp = $row[1];
    // Skip rows older than cutoff
    if (strtotime($timestamp) <= $cutoffDate) {
        continue;
    }
    // Keep the most recent row for this index
    if (!isset($rowsPerIndex[$index]) || strtotime($timestamp) > strtotime($rowsPerIndex[$index][1])) {
        $rowsPerIndex[$index] = [$index, $timestamp, $row[2]];
    }
}

// Flag to determine if we need to write to disk at the end
$needsSave = true; // always write the cleaned list back

// --- HANDLE REQUESTS ---
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Write the cleaned version back so the file stays tidy
    writeCsv($csvFile, array_values($rowsPerIndex));

    // --- LOAD ALL CURRENT CONTENT AS JSON ---
    $output = [];
    foreach ($rowsPerIndex as $index => $entry) {
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

    // Remove any existing row with the same index (we will replace it)
    $newRows = [];
    foreach ($currentRows as $row) {
        $rowIndex = intval($row[0]);
        $rowTimestamp = $row[1];
        if ($rowIndex == $index) {
            continue; // skip this one, we will add a fresh row
        }
        // Also skip rows that are too old
        if (strtotime($rowTimestamp) <= $cutoffDate) {
            continue;
        }
        $newRows[] = [$rowIndex, $rowTimestamp, $row[2]];
    }

    // Append the new row
    $timestamp = date('Y-m-d H:i:s');
    $newRows[] = [$index, $timestamp, $content];

    // Write everything back to the file
    if (writeCsv($csvFile, array_values($newRows))) {
        echo "Content saved successfully.";
    } else {
        http_response_code(500);
        echo "Error: Could not write to data file. Check permissions.";
    }
}
?>
