<?php
// Clear PHP opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache cleared successfully!<br>";
} else {
    echo "ℹ️ OPcache not enabled<br>";
}

// Clear realpath cache
clearstatcache(true);
echo "✅ Realpath cache cleared!<br>";

// Verify save_penalty_guideline.php content
$file = __DIR__ . '/save_penalty_guideline.php';
$content = file_get_contents($file);

// Check for the old incorrect bind_param
if (strpos($content, "'sssdissl'") !== false) {
    echo "<br>❌ ERROR: File still contains old code 'sssdissl'<br>";
    echo "File needs to be saved properly!<br>";
} else {
    echo "<br>✅ File updated correctly - no 'sssdissl' found<br>";
}

// Check for the correct bind_param
if (strpos($content, "'sssdissi'") !== false) {
    echo "✅ Correct bind_param 'sssdissi' found<br>";
} else {
    echo "❌ WARNING: Correct bind_param 'sssdissi' not found<br>";
}

// Check for JSON response
if (strpos($content, "header('Content-Type: application/json')") !== false) {
    echo "✅ JSON response header found<br>";
} else {
    echo "❌ WARNING: JSON response not implemented<br>";
}

echo "<br><strong>File last modified:</strong> " . date('Y-m-d H:i:s', filemtime($file)) . "<br>";
echo "<br><a href='admin-penalty-guideline.php'>← Back to Penalty Guidelines</a>";
?>
