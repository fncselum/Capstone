<?php
/**
 * AI Comparison Configuration
 * Adjust these settings based on your environment
 */

// Enable/disable AI comparison (set to false to use offline-only)
define('AI_COMPARISON_ENABLED', true);

// Python executable path
// Windows: 'python' or 'C:\Python39\python.exe'
// Linux/Mac: 'python3' or '/usr/bin/python3'
define('AI_PYTHON_PATH', 'C:\Users\Rosa Maria Elum\AppData\Local\Programs\Python\Python314\python.exe');

// AI comparison timeout (seconds)
define('AI_TIMEOUT', 30);

// Score blending weights (when AI confidence >= 0.8)
define('AI_BLEND_OFFLINE_WEIGHT', 0.6);
define('AI_BLEND_AI_WEIGHT', 0.4);

// Minimum AI confidence to use blended score (0.0 - 1.0)
// Below this threshold, offline score is used
define('AI_MIN_CONFIDENCE', 0.5);

// Score mismatch threshold for warnings
// If abs(offline_score - ai_score) >= this value, show warning
define('AI_MISMATCH_THRESHOLD', 20.0);

// Severity thresholds for final blended score
define('AI_SEVERITY_HIGH_THRESHOLD', 50.0);    // < 50 = high severity
define('AI_SEVERITY_MEDIUM_THRESHOLD', 70.0);  // 50-70 = medium severity
                                                // >= 70 = none (no issues)

// Model settings
define('AI_MODEL_NAME', 'openai/clip-vit-base-patch32');
define('AI_MODEL_VERSION', 'v1.0');

// Logging
define('AI_LOG_ENABLED', true);
define('AI_LOG_PATH', __DIR__ . '/../logs/ai_comparison.log');

/**
 * Log AI comparison events
 */
function logAIEvent($message, $level = 'INFO') {
    if (!AI_LOG_ENABLED) {
        return;
    }
    
    $log_dir = dirname(AI_LOG_PATH);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$level}] {$message}\n";
    
    @file_put_contents(AI_LOG_PATH, $log_message, FILE_APPEND);
}
