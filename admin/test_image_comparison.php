<?php
// Test file for enhanced image comparison system
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/image_comparison.php';

echo "<h2>Enhanced Image Comparison System Test</h2>";

// Test with sample images if they exist
$testImages = [
    'mouse' => [
        'reference' => '../uploads/mouse_1759807569.jpg',
        'return' => '../uploads/mouse_1759814100.jpg'
    ],
    'keyboard' => [
        'reference' => '../uploads/keyboard_1759888342.jpg',
        'return' => '../uploads/keyboard_1759888342.jpg' // Same image for perfect match test
    ]
];

foreach ($testImages as $itemName => $images) {
    echo "<h3>Testing: $itemName</h3>";
    
    $referencePath = $images['reference'];
    $returnPath = $images['return'];
    
    if (file_exists($referencePath) && file_exists($returnPath)) {
        echo "<p>Reference: $referencePath</p>";
        echo "<p>Return: $returnPath</p>";
        
        // Test the enhanced analysis function
        $results = analyzeImageDifferences($referencePath, $returnPath, 4, 'small');
        
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<h4>Analysis Results:</h4>";
        echo "<p><strong>Similarity Score:</strong> " . $results['similarity'] . "%</p>";
        echo "<p><strong>Verdict:</strong> " . $results['verdict'] . "</p>";
        echo "<p><strong>Severity Level:</strong> " . $results['severity_level'] . "</p>";
        echo "<p><strong>Detected Issues:</strong> " . $results['detected_issues_text'] . "</p>";
        
        if (!empty($results['issues'])) {
            echo "<p><strong>Detailed Issues:</strong></p>";
            echo "<ul>";
            foreach ($results['issues'] as $issue) {
                echo "<li><strong>" . $issue['type'] . "</strong> (" . $issue['severity'] . "): " . $issue['message'] . "</li>";
            }
            echo "</ul>";
        }
        
        echo "<p><strong>Grid Analysis:</strong> " . count($results['grid_analysis']) . " areas analyzed</p>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>Test images not found for $itemName</p>";
    }
}

echo "<h3>Severity Level Mapping:</h3>";
echo "<ul>";
echo "<li><strong>severity-none (Green):</strong> No visible damage detected</li>";
echo "<li><strong>severity-low (Blue):</strong> Minor wear detected - within acceptable limits</li>";
echo "<li><strong>severity-medium (Orange):</strong> Noticeable wear or minor damage detected - review recommended</li>";
echo "<li><strong>severity-high (Red):</strong> Significant damage detected - item requires inspection</li>";
echo "</ul>";

echo "<h3>Thresholds by Item Size:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Size</th><th>Excellent</th><th>Good</th><th>Fair</th><th>Poor</th><th>Damaged</th></tr>";
echo "<tr><td>Small</td><td>95%</td><td>90%</td><td>85%</td><td>75%</td><td>70%</td></tr>";
echo "<tr><td>Medium</td><td>92%</td><td>87%</td><td>82%</td><td>75%</td><td>70%</td></tr>";
echo "<tr><td>Large</td><td>90%</td><td>85%</td><td>80%</td><td>75%</td><td>70%</td></tr>";
echo "</table>";

echo "<p><a href='admin-all-transaction.php'>← Back to Transactions</a></p>";
?>

