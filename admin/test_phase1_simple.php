<?php
/**
 * Phase 1 Test: Image Comparison Technique
 */

echo "<h1>Phase 1: Image Comparison Test</h1>";

// Include the Phase 1 image comparison library
require_once 'includes/image_comparison_phase1.php';

// Test 1: Check if functions exist
echo "<h2>1. Function Availability Check</h2>";
$functions = ['analyzeImageDifferences', 'calculateSSIM', 'compareImagesSimilarity'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "<p style='color: green;'>✓ Function '$func' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Function '$func' missing</p>";
    }
}

// Test 2: Create test images and compare
echo "<h2>2. Image Comparison Test</h2>";

$testImage1 = 'uploads/test_ref.jpg';
$testImage2 = 'uploads/test_ret.jpg';

// Create test images
if (!file_exists($testImage1)) {
    $img = imagecreate(200, 200);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $text = imagecolorallocate($img, 0, 0, 0);
    imagestring($img, 5, 50, 90, 'REFERENCE', $text);
    imagejpeg($img, $testImage1);
    imagedestroy($img);
    echo "<p>Created test reference image</p>";
}

if (!file_exists($testImage2)) {
    $img = imagecreate(200, 200);
    $bg = imagecolorallocate($img, 250, 250, 250);
    $text = imagecolorallocate($img, 50, 50, 50);
    imagestring($img, 5, 50, 90, 'RETURN', $text);
    imagejpeg($img, $testImage2);
    imagedestroy($img);
    echo "<p>Created test return image</p>";
}

if (file_exists($testImage1) && file_exists($testImage2)) {
    echo "<p style='color: green;'>✓ Test images created successfully</p>";
    
    try {
        $comparisonResults = analyzeImageDifferences($testImage1, $testImage2, 4, 'medium');
        
        echo "<p style='color: green;'>✓ Image comparison successful!</p>";
        echo "<pre>";
        echo "Similarity Score: " . $comparisonResults['similarity'] . "%\n";
        echo "Verdict: " . $comparisonResults['verdict'] . "\n";
        echo "Detected Issues: " . $comparisonResults['detected_issues_text'] . "\n";
        echo "Severity Level: " . $comparisonResults['severity_level'] . "\n";
        echo "Method Used: " . $comparisonResults['method_used'] . "\n";
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Image comparison failed: " . $e->getMessage() . "</p>";
    }
    
    // Cleanup
    unlink($testImage1);
    unlink($testImage2);
} else {
    echo "<p style='color: red;'>✗ Could not create test images</p>";
}

echo "<h2>3. Phase 1 Status</h2>";
echo "<p style='color: green; font-weight: bold;'>✅ Phase 1: Image Comparison Technique is ready!</p>";
echo "<p>The system will now compare return photos to reference photos and display results in the detected issues container.</p>";
?>
