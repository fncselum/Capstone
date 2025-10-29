<?php
/**
 * Test AI Comparison Setup
 * Run this script to verify AI inference is working correctly
 */

require_once __DIR__ . '/../config/ai_config.php';
require_once __DIR__ . '/../includes/ai_comparison.php';

echo "=== AI Comparison Setup Test ===\n\n";

// Test 1: Check Python availability
echo "1. Checking Python installation...\n";
$python_check = shell_exec(AI_PYTHON_PATH . " --version 2>&1");
if (empty($python_check)) {
    echo "   ❌ FAILED: Python not found at: " . AI_PYTHON_PATH . "\n";
    echo "   → Update AI_PYTHON_PATH in config/ai_config.php\n\n";
} else {
    echo "   ✓ Python found: " . trim($python_check) . "\n\n";
}

// Test 2: Check Python script exists
echo "2. Checking AI script...\n";
$script_path = __DIR__ . '/compare_images.py';
if (!file_exists($script_path)) {
    echo "   ❌ FAILED: Script not found at: {$script_path}\n\n";
} else {
    echo "   ✓ Script found: {$script_path}\n\n";
}

// Test 3: Check Python dependencies
echo "3. Checking Python dependencies...\n";
$deps_check = shell_exec(AI_PYTHON_PATH . " -c \"import torch, transformers, PIL, cv2, numpy; print('OK')\" 2>&1");
if (stripos($deps_check, 'OK') === false) {
    echo "   ❌ FAILED: Missing Python dependencies\n";
    echo "   Output: " . trim($deps_check) . "\n";
    echo "   → Run: pip install torch torchvision transformers pillow opencv-python numpy\n\n";
} else {
    echo "   ✓ All dependencies installed\n\n";
}

// Test 4: Check configuration
echo "4. Checking configuration...\n";
echo "   - AI Enabled: " . (AI_COMPARISON_ENABLED ? 'Yes' : 'No') . "\n";
echo "   - Python Path: " . AI_PYTHON_PATH . "\n";
echo "   - Min Confidence: " . AI_MIN_CONFIDENCE . "\n";
echo "   - Blend Weights: " . AI_BLEND_OFFLINE_WEIGHT . " offline, " . AI_BLEND_AI_WEIGHT . " AI\n";
echo "   - Mismatch Threshold: " . AI_MISMATCH_THRESHOLD . "\n\n";

// Test 5: Test with sample images (if available)
echo "5. Testing AI inference...\n";
$test_image_dir = __DIR__ . '/../uploads/equipment';
if (is_dir($test_image_dir)) {
    $images = glob($test_image_dir . '/*.{jpg,jpeg,png}', GLOB_BRACE);
    if (count($images) >= 2) {
        echo "   Found test images, running comparison...\n";
        $img1 = $images[0];
        $img2 = $images[1];
        
        echo "   Reference: " . basename($img1) . "\n";
        echo "   Return: " . basename($img2) . "\n";
        
        $start = microtime(true);
        $result = runAIComparison($img1, $img2);
        $duration = microtime(true) - $start;
        
        if ($result['success']) {
            echo "   ✓ AI inference successful!\n";
            echo "   - Similarity: " . $result['ai_similarity_score'] . "%\n";
            echo "   - Confidence: " . $result['ai_confidence'] . "\n";
            echo "   - Model: " . $result['model_version'] . "\n";
            echo "   - Execution time: " . round($duration, 2) . "s\n";
            echo "   - Detected issues: " . count($result['ai_detected_issues']) . "\n";
            foreach ($result['ai_detected_issues'] as $issue) {
                echo "     • " . $issue . "\n";
            }
        } else {
            echo "   ❌ AI inference failed\n";
            echo "   Error: " . $result['error'] . "\n";
            if (isset($result['raw_output'])) {
                echo "   Raw output:\n" . $result['raw_output'] . "\n";
            }
        }
    } else {
        echo "   ⚠ Not enough test images found in {$test_image_dir}\n";
        echo "   → Upload at least 2 equipment images to test\n";
    }
} else {
    echo "   ⚠ Test image directory not found: {$test_image_dir}\n";
}

echo "\n=== Test Complete ===\n";

// Summary
echo "\n📋 Summary:\n";
if (!empty($python_check) && file_exists($script_path) && stripos($deps_check, 'OK') !== false) {
    echo "✓ Setup appears to be correct!\n";
    echo "→ You can now run: php admin/ai_worker_stub.php\n";
} else {
    echo "❌ Setup incomplete - please fix the issues above\n";
    echo "→ See ai/README.md for detailed setup instructions\n";
}
