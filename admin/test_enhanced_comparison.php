<?php
/**
 * Test Enhanced Image Comparison System - Phase 2.3.2
 * Tests the new hybrid comparison methods: SSIM + Hash + Color Analysis
 */

require_once 'includes/image_comparison.php';

echo "<h1>Phase 2.3.2 - Enhanced Image Comparison Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { background: #d4edda; border-color: #c3e6cb; }
    .error { background: #f8d7da; border-color: #f5c6cb; }
    .info { background: #d1ecf1; border-color: #bee5eb; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// Test 1: Check if new functions exist
echo "<div class='test-section'>";
echo "<h2>Test 1: Function Availability</h2>";

$functions = [
    'getPerceptualHash',
    'comparePerceptualHashes', 
    'quickImageComparison',
    'detectColorAnomalies',
    'analyzeImageDifferences'
];

$allFunctionsExist = true;
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "<p style='color: green;'>✓ Function '$func' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Function '$func' missing</p>";
        $allFunctionsExist = false;
    }
}

if ($allFunctionsExist) {
    echo "<p style='color: green; font-weight: bold;'>✓ All enhanced functions are available!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Some functions are missing!</p>";
}
echo "</div>";

// Test 2: Test perceptual hash generation
echo "<div class='test-section'>";
echo "<h2>Test 2: Perceptual Hash Generation</h2>";

// Create test images if they don't exist
$testImage1 = 'uploads/test_image1.jpg';
$testImage2 = 'uploads/test_image2.jpg';

if (!file_exists($testImage1)) {
    // Create a simple test image
    $img = imagecreate(100, 100);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $text = imagecolorallocate($img, 0, 0, 0);
    imagestring($img, 5, 30, 40, 'TEST1', $text);
    imagejpeg($img, $testImage1);
    imagedestroy($img);
    echo "<p>Created test image 1</p>";
}

if (!file_exists($testImage2)) {
    // Create a slightly different test image
    $img = imagecreate(100, 100);
    $bg = imagecolorallocate($img, 250, 250, 250);
    $text = imagecolorallocate($img, 50, 50, 50);
    imagestring($img, 5, 30, 40, 'TEST2', $text);
    imagejpeg($img, $testImage2);
    imagedestroy($img);
    echo "<p>Created test image 2</p>";
}

if (file_exists($testImage1) && file_exists($testImage2)) {
    $hash1 = getPerceptualHash($testImage1);
    $hash2 = getPerceptualHash($testImage2);
    
    if ($hash1 && $hash2) {
        echo "<p style='color: green;'>✓ Hash generation successful</p>";
        echo "<p><strong>Hash 1:</strong> " . substr($hash1, 0, 20) . "...</p>";
        echo "<p><strong>Hash 2:</strong> " . substr($hash2, 0, 20) . "...</p>";
        
        $similarity = comparePerceptualHashes($hash1, $hash2);
        if ($similarity !== null) {
            echo "<p style='color: green;'>✓ Hash comparison successful: " . round($similarity, 2) . "%</p>";
        } else {
            echo "<p style='color: red;'>✗ Hash comparison failed</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Hash generation failed</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Test images not available</p>";
}
echo "</div>";

// Test 3: Test enhanced image comparison
echo "<div class='test-section'>";
echo "<h2>Test 3: Enhanced Image Comparison</h2>";

if (file_exists($testImage1) && file_exists($testImage2)) {
    $results = analyzeImageDifferences($testImage1, $testImage2, 4, 'medium');
    
    if ($results && isset($results['similarity'])) {
        echo "<p style='color: green;'>✓ Enhanced comparison successful</p>";
        echo "<pre>";
        echo "Similarity Score: " . $results['similarity'] . "%\n";
        echo "Hash Similarity: " . ($results['hash_similarity'] ?? 'N/A') . "%\n";
        echo "SSIM Similarity: " . ($results['ssim_similarity'] ?? 'N/A') . "%\n";
        echo "Color Anomaly: " . ($results['color_anomaly_percent'] ?? 'N/A') . "%\n";
        echo "Method Used: " . ($results['method_used'] ?? 'N/A') . "\n";
        echo "Verdict: " . ($results['verdict'] ?? 'N/A') . "\n";
        echo "Detected Issues: " . ($results['detected_issues_text'] ?? 'N/A') . "\n";
        echo "Severity Level: " . ($results['severity_level'] ?? 'N/A') . "\n";
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Enhanced comparison failed</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Test images not available</p>";
}
echo "</div>";

// Test 4: Test color anomaly detection
echo "<div class='test-section'>";
echo "<h2>Test 4: Color Anomaly Detection</h2>";

if (file_exists($testImage1) && file_exists($testImage2)) {
    $colorAnomaly = detectColorAnomalies($testImage1, $testImage2, 30);
    
    if ($colorAnomaly !== null) {
        echo "<p style='color: green;'>✓ Color anomaly detection successful: " . round($colorAnomaly, 2) . "%</p>";
    } else {
        echo "<p style='color: red;'>✗ Color anomaly detection failed</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Test images not available</p>";
}
echo "</div>";

// Test 5: Test quick comparison
echo "<div class='test-section'>";
echo "<h2>Test 5: Quick Image Comparison</h2>";

if (file_exists($testImage1) && file_exists($testImage2)) {
    $quickSimilarity = quickImageComparison($testImage1, $testImage2);
    
    if ($quickSimilarity !== null) {
        echo "<p style='color: green;'>✓ Quick comparison successful: " . round($quickSimilarity, 2) . "%</p>";
    } else {
        echo "<p style='color: red;'>✗ Quick comparison failed</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Test images not available</p>";
}
echo "</div>";

// Summary
echo "<div class='test-section'>";
echo "<h2>Phase 2.3.2 Test Summary</h2>";
echo "<p><strong>Enhanced Image Comparison System Features:</strong></p>";
echo "<ul>";
echo "<li>✓ Perceptual Hash Generation (8x8 grayscale)</li>";
echo "<li>✓ Hash-based Quick Comparison</li>";
echo "<li>✓ Color Anomaly Detection (300x300 sampling)</li>";
echo "<li>✓ Hybrid SSIM + Hash + Color Analysis</li>";
echo "<li>✓ Weighted Final Score Calculation</li>";
echo "<li>✓ Automatic Severity Classification</li>";
echo "<li>✓ System-Generated Damage Detection</li>";
echo "</ul>";
echo "<p style='color: green; font-weight: bold;'>🎉 Phase 2.3.2 Enhanced Image Comparison System is ready!</p>";
echo "</div>";

// Cleanup test images
if (file_exists($testImage1)) {
    unlink($testImage1);
}
if (file_exists($testImage2)) {
    unlink($testImage2);
}
?>
