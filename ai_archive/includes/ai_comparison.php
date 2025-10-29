<?php
/**
 * AI-based Image Comparison Layer
 * Wrapper for Python CLIP inference script
 * Provides fallback to offline comparison if AI unavailable
 */

// Load configuration
require_once __DIR__ . '/../config/ai_config.php';

// Backward compatibility constants
if (!defined('ENABLE_AI_COMPARISON')) {
    define('ENABLE_AI_COMPARISON', AI_COMPARISON_ENABLED);
}
if (!defined('PYTHON_EXECUTABLE')) {
    define('PYTHON_EXECUTABLE', AI_PYTHON_PATH);
}

/**
 * Run AI-based image comparison using CLIP model
 * 
 * @param string $reference_path Absolute path to reference image
 * @param string $return_path Absolute path to return image
 * @return array AI comparison results or error
 */
function runAIComparison($reference_path, $return_path) {
    // Check if AI comparison is enabled
    if (!ENABLE_AI_COMPARISON) {
        return [
            'success' => false,
            'error' => 'AI comparison disabled',
            'ai_similarity_score' => null,
            'ai_confidence' => 0.0,
            'ai_detected_issues' => [],
            'ai_issue_labels' => [],
            'status' => 'disabled'
        ];
    }
    
    // Validate input paths
    if (!file_exists($reference_path)) {
        return [
            'success' => false,
            'error' => 'Reference image not found',
            'ai_similarity_score' => null,
            'ai_confidence' => 0.0,
            'ai_detected_issues' => ['Reference image not found'],
            'ai_issue_labels' => [],
            'status' => 'error'
        ];
    }
    
    if (!file_exists($return_path)) {
        return [
            'success' => false,
            'error' => 'Return image not found',
            'ai_similarity_score' => null,
            'ai_confidence' => 0.0,
            'ai_detected_issues' => ['Return image not found'],
            'ai_issue_labels' => [],
            'status' => 'error'
        ];
    }
    
    // Get script path
    $script_path = __DIR__ . '/../ai/compare_images.py';
    if (!file_exists($script_path)) {
        return [
            'success' => false,
            'error' => 'AI script not found',
            'ai_similarity_score' => null,
            'ai_confidence' => 0.0,
            'ai_detected_issues' => ['AI inference script not available'],
            'ai_issue_labels' => [],
            'status' => 'error'
        ];
    }
    
    // Escape paths for shell execution
    $ref_escaped = escapeshellarg($reference_path);
    $ret_escaped = escapeshellarg($return_path);
    $script_escaped = escapeshellarg($script_path);
    
    // Build command
    $command = PYTHON_EXECUTABLE . " {$script_escaped} {$ref_escaped} {$ret_escaped} 2>&1";
    
    // Execute Python script with timeout
    $start_time = microtime(true);
    logAIEvent("Executing AI comparison: {$command}", 'DEBUG');
    $output = shell_exec($command);
    $execution_time = microtime(true) - $start_time;
    logAIEvent("AI execution completed in {$execution_time}s", 'DEBUG');
    
    // Parse JSON output
    if (empty($output)) {
        return [
            'success' => false,
            'error' => 'AI script produced no output',
            'ai_similarity_score' => null,
            'ai_confidence' => 0.0,
            'ai_detected_issues' => ['AI inference failed - no output'],
            'ai_issue_labels' => [],
            'status' => 'error',
            'execution_time' => $execution_time
        ];
    }
    
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Failed to parse AI output: ' . json_last_error_msg(),
            'raw_output' => $output,
            'ai_similarity_score' => null,
            'ai_confidence' => 0.0,
            'ai_detected_issues' => ['AI inference output parsing failed'],
            'ai_issue_labels' => [],
            'status' => 'error',
            'execution_time' => $execution_time
        ];
    }
    
    // Check for errors in result
    if (isset($result['error'])) {
        return [
            'success' => false,
            'error' => $result['error'],
            'ai_similarity_score' => $result['ai_similarity_score'] ?? null,
            'ai_confidence' => $result['ai_confidence'] ?? 0.0,
            'ai_detected_issues' => $result['ai_detected_issues'] ?? ['AI inference failed'],
            'ai_issue_labels' => $result['ai_issue_labels'] ?? [],
            'status' => 'error',
            'execution_time' => $execution_time
        ];
    }
    
    // Return successful result
    return [
        'success' => true,
        'ai_similarity_score' => $result['ai_similarity_score'] ?? null,
        'ai_confidence' => $result['ai_confidence'] ?? 0.0,
        'ai_detected_issues' => $result['ai_detected_issues'] ?? [],
        'ai_issue_labels' => $result['ai_issue_labels'] ?? [],
        'visual_analysis' => $result['visual_analysis'] ?? [],
        'model_version' => $result['model_version'] ?? 'unknown',
        'status' => 'success',
        'execution_time' => $execution_time
    ];
}

/**
 * Blend offline and AI comparison scores
 * 
 * @param float $offline_score Offline hybrid comparison score (0-100)
 * @param float $ai_score AI similarity score (0-100)
 * @param float $ai_confidence AI confidence level (0-1)
 * @return array Blended results
 */
function blendComparisonScores($offline_score, $ai_score, $ai_confidence) {
    // If AI confidence is too low, use offline only
    if ($ai_confidence < 0.5) {
        return [
            'final_score' => $offline_score,
            'method' => 'offline_only',
            'reason' => 'Low AI confidence - using offline score',
            'offline_weight' => 1.0,
            'ai_weight' => 0.0
        ];
    }
    
    // Adjust weights based on confidence
    // High confidence: 60% offline, 40% AI
    // Medium confidence: 70% offline, 30% AI
    $offline_weight = $ai_confidence >= 0.8 ? 0.6 : 0.7;
    $ai_weight = 1.0 - $offline_weight;
    
    $final_score = ($offline_weight * $offline_score) + ($ai_weight * $ai_score);
    
    return [
        'final_score' => round($final_score, 2),
        'method' => 'blended',
        'offline_weight' => $offline_weight,
        'ai_weight' => $ai_weight,
        'ai_confidence' => $ai_confidence
    ];
}

/**
 * Merge detected issues from offline and AI analysis
 * 
 * @param string $offline_issues Offline detected issues text
 * @param array $ai_issues_list AI detected issues array
 * @return string Merged issues text
 */
function mergeDetectedIssues($offline_issues, $ai_issues_list) {
    $merged = [];
    
    // Add AI issues first (higher priority)
    if (!empty($ai_issues_list) && is_array($ai_issues_list)) {
        foreach ($ai_issues_list as $issue) {
            $trimmed = trim($issue);
            if (!empty($trimmed)) {
                $merged[] = $trimmed;
            }
        }
    }
    
    // Add offline issues if not redundant
    if (!empty($offline_issues)) {
        $offline_lines = explode("\n", $offline_issues);
        foreach ($offline_lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && !in_array($trimmed, $merged, true)) {
                // Check if similar issue already exists
                $is_duplicate = false;
                foreach ($merged as $existing) {
                    if (stripos($existing, $trimmed) !== false || stripos($trimmed, $existing) !== false) {
                        $is_duplicate = true;
                        break;
                    }
                }
                if (!$is_duplicate) {
                    $merged[] = $trimmed;
                }
            }
        }
    }
    
    return implode("\n", $merged);
}

/**
 * Detect if AI and offline results significantly disagree
 * 
 * @param float $offline_score Offline score (0-100)
 * @param float $ai_score AI score (0-100)
 * @param float $threshold Disagreement threshold
 * @return bool True if scores disagree significantly
 */
function detectScoreMismatch($offline_score, $ai_score, $threshold = 20.0) {
    return abs($offline_score - $ai_score) >= $threshold;
}

/**
 * Determine final severity level from blended score
 * 
 * @param float $final_score Final blended score (0-100)
 * @return string Severity level: 'none', 'medium', 'high'
 */
function determineSeverityLevel($final_score) {
    if ($final_score >= 70) {
        return 'none';
    } elseif ($final_score >= 50) {
        return 'medium';
    } else {
        return 'high';
    }
}
