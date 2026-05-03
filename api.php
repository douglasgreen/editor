<?php
// --- CONFIGURATION ---
// Store data in a CSV file in the current directory
$csvFile = __DIR__ . '/data.csv';
// Set timezone to ensure date calculations are consistent
date_default_timezone_set('UTC');

/**
 * Helper function to read all rows from the CSV safely.
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
                // Expected format: [timestamp, content]
                if (count($data) >= 2) {
                    $rows[] = $data;
                }
            }
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
    return $rows;
}

/**
 * Helper function to write rows to the CSV safely.
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
$originalCount = count($rows);
$cutoffDate = strtotime('-3 days');

// Filter rows where timestamp (index 0) is newer than cutoff
$rows = array_filter($rows, function($row) use ($cutoffDate) {
    // Check if the row has a valid date and is recent enough
    return isset($row[0]) && strtotime($row[0]) > $cutoffDate;
});

// Re-index array after filtering
$rows = array_values($rows);

// Flag to determine if we need to write to disk at the end
$needsSave = (count($rows) !== $originalCount);

// --- HANDLE REQUESTS ---
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // If cleanup happened but no new post, we should save the cleaned version
    if ($needsSave) {
        writeCsv($csvFile, $rows);
    }

    // --- LOAD LATEST CONTENT ---
    // Get the last entry in the array (most recent)
    if (count($rows) > 0) {
        $lastRow = end($rows);
        // Output the content (index 1)
        echo $lastRow[1];
    } else {
        // Default welcome message
        echo "# New Document\n\nStart typing your markdown here.";
    }

} elseif ($method === 'POST') {
    // --- SAVE NEW CONTENT ---
    $content = file_get_contents('php://input');
    $timestamp = date('Y-m-d H:i:s');

    // Append new row
    $rows[] = [$timestamp, $content];
    
    // Write everything back to the file (Cleanup + New Entry)
    if (writeCsv($csvFile, $rows)) {
        echo "Content saved successfully.";
    } else {
        http_response_code(500);
        echo "Error: Could not write to data file. Check permissions.";
    }
}
?>
