<?php
/**
 * AI Comparison Worker (Stub)
 * This processes pending AI comparison jobs in the background
 * Replace the stub logic with actual AI model inference when ready
 */

require_once __DIR__ . '/../config/database.php';

set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '512M');

$processed = 0;
$failed = 0;

echo "=== AI Comparison Worker ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// Fetch pending jobs (limit to 10 per run)
$query = $conn->prepare("SELECT job_id, transaction_id, payload 
    FROM ai_comparison_jobs 
    WHERE status = 'pending' 
    ORDER BY priority ASC, created_at ASC 
    LIMIT 10");
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo "No pending jobs.\n";
    $conn->close();
    exit;
}

while ($job = $result->fetch_assoc()) {
    $jobId = $job['job_id'];
    $txnId = $job['transaction_id'];
    $payload = json_decode($job['payload'], true);
    
    echo "Processing Job #{$jobId} (Transaction #{$txnId})... ";
    
    // Mark job as processing
    $updateStatus = $conn->prepare("UPDATE ai_comparison_jobs SET status = 'processing', updated_at = NOW() WHERE job_id = ?");
    $updateStatus->bind_param('i', $jobId);
    $updateStatus->execute();
    $updateStatus->close();
    
    $updateTxn = $conn->prepare("UPDATE transactions SET ai_analysis_status = 'processing', ai_analysis_message = 'AI analysis in progress' WHERE id = ?");
    $updateTxn->bind_param('i', $txnId);
    $updateTxn->execute();
    $updateTxn->close();
    
    try {
        // === REAL AI INFERENCE ===
        require_once __DIR__ . '/../includes/ai_comparison.php';
        
        $referencePath = $payload['reference_path'] ?? null;
        $returnPath = $payload['return_path'] ?? null;
        $offlineSimilarity = $payload['offline_similarity'] ?? 50.0;
        $offlineSeverity = $payload['offline_severity'] ?? 'medium';
        
        if (!$referencePath || !$returnPath) {
            throw new Exception('Missing image paths in job payload');
        }
        
        // Run AI comparison
        $aiResult = runAIComparison($referencePath, $returnPath);
        
        if (!$aiResult['success']) {
            // AI failed - use offline results as fallback
            echo "AI FAILED (using offline): {$aiResult['error']}\n";
            
            $aiSimilarity = $offlineSimilarity;
            $aiSeverity = $offlineSeverity;
            $detectedIssuesList = ['AI inference unavailable - using offline results'];
            $aiDetectedIssuesText = implode("\n", $detectedIssuesList);
            $aiConfidence = 0.0;
            $modelVersion = 'offline-fallback';
            $blendMethod = 'offline_only';
            $finalScore = $offlineSimilarity;
        } else {
            // AI succeeded - blend with offline results
            $aiSimilarity = $aiResult['ai_similarity_score'];
            $aiConfidence = $aiResult['ai_confidence'];
            
            // Blend scores
            $blendResult = blendComparisonScores($offlineSimilarity, $aiSimilarity, $aiConfidence);
            $finalScore = $blendResult['final_score'];
            $blendMethod = $blendResult['method'];
            
            // Merge detected issues
            $offlineIssues = ''; // Offline issues already in DB
            $aiDetectedIssuesText = mergeDetectedIssues($offlineIssues, $aiResult['ai_detected_issues']);
            $detectedIssuesList = $aiResult['ai_detected_issues'];
            
            // Determine final severity from blended score
            $aiSeverity = determineSeverityLevel($finalScore);
            
            $modelVersion = $aiResult['model_version'] ?? 'clip-vit-base-patch32-v1.0';
            
            // Check for score mismatch
            $hasMismatch = detectScoreMismatch($offlineSimilarity, $aiSimilarity);
            if ($hasMismatch) {
                array_unshift($detectedIssuesList, "⚠️ AI and offline scores differ significantly - manual review recommended");
                $aiDetectedIssuesText = implode("\n", $detectedIssuesList);
            }
        }
        
        $aiResult = [
            'ai_similarity_score' => $aiSimilarity,
            'final_blended_score' => $finalScore ?? $aiSimilarity,
            'ai_confidence' => $aiConfidence ?? 0.0,
            'ai_severity_level' => $aiSeverity,
            'ai_detected_issues' => $aiDetectedIssuesText,
            'ai_detected_issues_list' => $detectedIssuesList,
            'model_version' => $modelVersion ?? 'unknown',
            'blend_method' => $blendMethod ?? 'unknown',
            'offline_score' => $offlineSimilarity,
            'processed_at' => date('Y-m-d H:i:s'),
        ];
        // === END REAL AI INFERENCE ===
        
        // Store AI result
        $resultJson = json_encode($aiResult);
        $completeJob = $conn->prepare("UPDATE ai_comparison_jobs SET status = 'completed', result = ?, processed_at = NOW(), updated_at = NOW() WHERE job_id = ?");
        $completeJob->bind_param('si', $resultJson, $jobId);
        $completeJob->execute();
        $completeJob->close();
        
        // Update transaction with AI results
        $updateAI = $conn->prepare("UPDATE transactions SET 
            ai_analysis_status = 'completed',
            ai_analysis_message = NULL,
            ai_similarity_score = ?,
            ai_severity_level = ?,
            ai_analysis_meta = ?,
            detected_issues = ?
        WHERE id = ?");
        $updateAI->bind_param('dsssi', $aiSimilarity, $aiSeverity, $resultJson, $aiDetectedIssuesText, $txnId);
        $updateAI->execute();
        $updateAI->close();
        
        echo "COMPLETED (AI Score: {$aiSimilarity}%)\n";
        $processed++;
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        echo "FAILED: {$errorMsg}\n";
        
        $failJob = $conn->prepare("UPDATE ai_comparison_jobs SET status = 'failed', error_message = ?, updated_at = NOW() WHERE job_id = ?");
        $failJob->bind_param('si', $errorMsg, $jobId);
        $failJob->execute();
        $failJob->close();
        
        $failTxn = $conn->prepare("UPDATE transactions SET ai_analysis_status = 'failed', ai_analysis_message = ? WHERE id = ?");
        $failTxn->bind_param('si', $errorMsg, $txnId);
        $failTxn->execute();
        $failTxn->close();
        
        $failed++;
    }
}

$query->close();
$conn->close();

echo "\n=== Summary ===\n";
echo "Processed: {$processed}\n";
echo "Failed: {$failed}\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
