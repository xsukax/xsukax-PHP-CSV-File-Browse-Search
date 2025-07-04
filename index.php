<?php
/**
 * CSV Search Script - Large File Optimized
 * 
 * This script provides efficient search functionality for large CSV files
 * without loading the entire file into memory at once.
 * 
 * Features:
 * - Memory-efficient streaming processing
 * - Pagination support
 * - Case-insensitive search across all columns
 * - Performance monitoring
 * - Large file handling
 */

// Configuration variables
$csv_file = 'data.txt';                    // Path to your CSV file
$search = isset($_GET['q']) ? trim($_GET['q']) : '';  // Get search query from URL
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;  // Current page number
$per_page = 1000;                          // Number of records per page

// Memory management - increase PHP memory limit for large files
// Adjust this value based on your server's available memory
ini_set('memory_limit', '1024M');

// Basic file validation
if (!file_exists($csv_file)) {
    die("Error: CSV file not found.");
}

/**
 * Process large CSV files line by line to avoid memory exhaustion
 * 
 * This function reads the CSV file sequentially without loading
 * everything into memory at once.
 * 
 * @param string $csv_file Path to the CSV file
 * @param string $search Search term to filter records
 * @param int $page Current page number for pagination
 * @param int $per_page Number of records per page
 * @return array Contains headers, filtered results, and total match count
 */
function processLargeCSV($csv_file, $search, $page, $per_page) {
    // Open file handle for reading
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        die("Error: Unable to open CSV file.");
    }
    
    // Read the first line as headers
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        die("Error: Unable to read CSV headers.");
    }
    
    // Add ID column to headers for row numbering
    array_unshift($headers, 'ID');
    
    // Initialize variables for processing
    $filtered = [];           // Array to store filtered results for current page
    $total_matches = 0;       // Counter for total matching records
    $id_counter = 1;          // Sequential ID counter for all rows
    $start_index = ($page - 1) * $per_page;  // Calculate starting index for current page
    $current_match = 0;       // Counter for current matching record position
    
    // Process file line by line to avoid memory issues
    while (($row = fgetcsv($handle)) !== false) {
        // Skip empty rows to save processing time
        if (empty($row) || (count($row) == 1 && trim($row[0]) == '')) {
            continue;
        }
        
        // Determine if current row matches search criteria
        $match = false;
        if ($search === '') {
            // If no search term, include all rows
            $match = true;
        } else {
            // Search through all columns in the row
            foreach ($row as $cell) {
                // Case-insensitive search
                if (stripos(strtolower($cell), strtolower($search)) !== false) {
                    $match = true;
                    break;  // Stop searching once match is found in any column
                }
            }
        }
        
        // Process matching rows
        if ($match) {
            $total_matches++;
            
            // Only collect rows for the current page to save memory
            if ($current_match >= $start_index && count($filtered) < $per_page) {
                // Add ID to the beginning of the row
                array_unshift($row, $id_counter);
                $filtered[] = $row;
            }
            
            $current_match++;
            $id_counter++;
            
            // Early exit optimization for large files
            // If we have enough results and we're past the current page,
            // continue counting remaining matches for accurate pagination
            if (count($filtered) >= $per_page && $current_match > $start_index + $per_page) {
                // Count remaining matches without storing them
                while (($row = fgetcsv($handle)) !== false) {
                    // Skip empty rows
                    if (empty($row) || (count($row) == 1 && trim($row[0]) == '')) {
                        continue;
                    }
                    
                    // Check if row matches search criteria
                    if ($search === '') {
                        $total_matches++;
                    } else {
                        foreach ($row as $cell) {
                            if (stripos(strtolower($cell), strtolower($search)) !== false) {
                                $total_matches++;
                                break;
                            }
                        }
                    }
                }
                break;  // Exit the main processing loop
            }
        }
    }
    
    // Close file handle to free resources
    fclose($handle);
    
    // Return processed data
    return [
        'headers' => $headers,
        'filtered' => $filtered,
        'total_matches' => $total_matches
    ];
}

// Get file size information for performance monitoring
$file_size = filesize($csv_file);
$file_size_mb = round($file_size / (1024 * 1024), 2);

// Process the CSV file
$result = processLargeCSV($csv_file, $search, $page, $per_page);
$headers = $result['headers'];
$filtered = $result['filtered'];
$total_matches = $result['total_matches'];

// Calculate total pages for pagination
$total_pages = max(1, ceil($total_matches / $per_page));

/**
 * Render pagination navigation
 * 
 * Creates Previous/Next buttons and numbered page links
 * with smart truncation for large page counts
 * 
 * @param string $search Current search term
 * @param int $total_pages Total number of pages
 * @param int $current_page Current page number
 */
function renderPagination($search, $total_pages, $current_page) {
    // Don't show pagination if only one page
    if ($total_pages <= 1) return;
    
    echo '<div class="pagination">';
    
    // Previous button - only show if not on first page
    if ($current_page > 1) {
        echo '<a href="?q=' . urlencode($search) . '&page=' . ($current_page - 1) . '">&laquo; Previous</a>';
    }
    
    // Calculate page range to display (max 10 pages around current)
    $start_page = max(1, $current_page - 5);
    $end_page = min($total_pages, $current_page + 5);
    
    // Show first page if we're not starting from page 1
    if ($start_page > 1) {
        echo '<a href="?q=' . urlencode($search) . '&page=1">1</a>';
        if ($start_page > 2) echo '<span>...</span>';  // Add ellipsis if gap exists
    }
    
    // Display page numbers in calculated range
    for ($p = $start_page; $p <= $end_page; $p++) {
        echo '<a href="?q=' . urlencode($search) . '&page=' . $p . '"' . 
             ($p == $current_page ? ' class="current-page"' : '') . '>' . $p . '</a>';
    }
    
    // Show last page if we're not ending at the last page
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) echo '<span>...</span>';  // Add ellipsis if gap exists
        echo '<a href="?q=' . urlencode($search) . '&page=' . $total_pages . '">' . $total_pages . '</a>';
    }
    
    // Next button - only show if not on last page
    if ($current_page < $total_pages) {
        echo '<a href="?q=' . urlencode($search) . '&page=' . ($current_page + 1) . '">Next &raquo;</a>';
    }
    
    echo '</div>';
}

// Get memory usage information for performance monitoring
$memory_usage = memory_get_usage(true);    // Current memory usage
$memory_peak = memory_get_peak_usage(true); // Peak memory usage

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>xsukax PHP CSV File Browse & Search</title>
<style>
/* Main layout styles */
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Table styles with memory-efficient display */
table { 
    border-collapse: collapse; 
    width: 100%; 
    margin-top: 20px;
}

/* Cell styling with text truncation to prevent memory issues */
th, td { 
    border: 1px solid #ddd; 
    padding: 8px; 
    text-align: left;
    max-width: 200px;          /* Limit cell width */
    overflow: hidden;          /* Hide overflow text */
    text-overflow: ellipsis;   /* Add ... for truncated text */
    white-space: nowrap;       /* Prevent text wrapping */
}

/* Header styling with sticky positioning */
th { 
    background: #f2f2f2; 
    font-weight: bold;
    position: sticky;    /* Keep headers visible while scrolling */
    top: 0;
}

/* Alternating row colors for better readability */
tr:nth-child(even) {
    background-color: #f9f9f9;
}

/* Row hover effect */
tr:hover {
    background-color: #f0f0f0;
}

/* Search form styling */
.search-form {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.search-form input[type="text"] {
    padding: 10px;
    font-size: 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 300px;
}

.search-form button {
    padding: 10px 20px;
    font-size: 16px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 10px;
}

.search-form button:hover {
    background: #005a87;
}

/* Pagination styling */
.pagination { 
    margin: 20px 0; 
    text-align: center;
}

.pagination a, .pagination span { 
    margin: 0 5px; 
    padding: 8px 12px;
    text-decoration: none; 
    border: 1px solid #ddd;
    border-radius: 4px;
    display: inline-block;
}

.pagination a:hover {
    background: #f0f0f0;
}

/* Current page highlighting */
.current-page { 
    font-weight: bold; 
    background: #007cba;
    color: white !important;
    border-color: #007cba;
}

/* Statistics display */
.stats {
    margin: 20px 0;
    padding: 10px;
    background: #e8f4f8;
    border-radius: 4px;
}

/* No results message */
.no-results {
    text-align: center;
    padding: 40px;
    color: #666;
}

/* File information display */
.file-info {
    font-size: 12px;
    color: #666;
    margin-top: 10px;
}

/* Memory usage information */
.memory-info {
    font-size: 11px;
    color: #999;
    margin-top: 5px;
}

/* Loading indicator */
.loading {
    display: none;
    text-align: center;
    padding: 20px;
    color: #666;
}

/* Alert message for large files */
.alert {
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}
</style>
</head>
<body>

<h2>xsukax PHP CSV File Browse & Search</h2>

<!-- Large file warning - shown when file is over 50MB -->
<?php if ($file_size_mb > 50): ?>
<div class="alert">
    <strong>Large File Detected:</strong> This file is <?php echo $file_size_mb; ?>MB. 
    Processing may take a moment. Consider using database indexing for better performance.
</div>
<?php endif; ?>

<!-- Search form with file information -->
<div class="search-form">
    <form method="get" onsubmit="document.querySelector('.loading').style.display='block';">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search across all columns...">
        <button type="submit">Search</button>
        <?php if ($search): ?>
            <a href="?" style="margin-left: 10px; color: #666;">Clear</a>
        <?php endif; ?>
    </form>
    
    <!-- File size information -->
    <div class="file-info">
        File size: <?php echo $file_size_mb; ?>MB
    </div>
    
    <!-- Memory usage monitoring -->
    <div class="memory-info">
        Memory used: <?php echo round($memory_usage / (1024 * 1024), 2); ?>MB | 
        Peak: <?php echo round($memory_peak / (1024 * 1024), 2); ?>MB
    </div>
</div>

<!-- Loading indicator for large file processing -->
<div class="loading">
    <p>Processing large file, please wait...</p>
</div>

<!-- Results display -->
<?php if ($total_matches > 0): ?>
    <!-- Search statistics -->
    <div class="stats">
        <strong>Results:</strong> <?php echo number_format($total_matches); ?> total matches
        <?php if ($search): ?>
            for "<?php echo htmlspecialchars($search); ?>"
        <?php endif; ?>
        | <strong>Page:</strong> <?php echo $page; ?> of <?php echo number_format($total_pages); ?>
        | <strong>Showing:</strong> <?php echo number_format(count($filtered)); ?> records
    </div>

    <!-- Top pagination -->
    <?php renderPagination($search, $total_pages, $page); ?>

    <!-- Data table with horizontal scroll for wide tables -->
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th title="<?php echo htmlspecialchars($header); ?>">
                            <?php echo htmlspecialchars($header); ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td title="<?php echo htmlspecialchars($cell); ?>">
                                <?php echo htmlspecialchars($cell); ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Bottom pagination -->
    <?php renderPagination($search, $total_pages, $page); ?>

<?php else: ?>
    <!-- No results message -->
    <div class="no-results">
        <h3>No matching records found</h3>
        <?php if ($search): ?>
            <p>Try a different search term or <a href="?">view all records</a></p>
        <?php else: ?>
            <p>The CSV file appears to be empty or couldn't be read.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- JavaScript for UI enhancements -->
<script>
// Hide loading message after page loads
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.loading').style.display = 'none';
});
</script>

</body>
</html>