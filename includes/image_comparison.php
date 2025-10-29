<?php
/**
 * Image Comparison System - Hybrid SSIM + Perceptual Hash
 * Optimized for equipment return verification
 */

if (!function_exists('compareReturnToReference')) {
    /**
     * Main comparison function - compares return photo to reference
     * 
     * @param string $referencePath Path to reference image (borrow photo or equipment image)
     * @param string $returnPath Path to return photo
     * @param array $options Configuration options
     * @return array Comparison results with similarity score and detected issues
     */
    function normalizeIssueMessage(string $message): string {
        $clean = trim($message);
        if ($clean === '') {
            return $clean;
        }

        // Remove legacy "(Method: hybrid)" or similar suffixes
        $clean = preg_replace('/\s*\(method\s*[:\-]?\s*hybrid\)\.?/i', '', $clean);

        // Collapse repeated whitespace
        $clean = preg_replace('/\s+/u', ' ', $clean);

        return trim($clean);
    }

    function compareReturnToReference(string $referencePath, string $returnPath, array $options = []): array {
        $defaults = [
            'resize' => 300,
            'hash_size' => 16,
            'weights' => ['ssim' => 0.50, 'phash' => 0.30, 'hist' => 0.05, 'edge' => 0.05, 'gradient' => 0.10],
            'item_size' => 'medium',
            'enable_preview' => true,
        ];
        $config = array_merge($defaults, $options);
        $warnings = [];

        $itemSize = strtolower((string)($config['item_size'] ?? 'medium'));
        if ($itemSize === 'large') {
            return [
                'success' => true,
                'similarity' => null,
                'ssim_score' => null,
                'phash_score' => null,
                'pixel_score' => null,
                'pixel_difference_score' => null,
                'hist_score' => null,
                'histogram_score' => null,
                'edge_diff_pct' => null,
                'edge_difference_pct' => null,
                'gradient_score' => null,
                'object_presence_score' => null,
                'confidence_band' => 'low',
                'detected_issues_text' => 'Manual review required (large item).',
                'detected_issues_list' => ['Manual review required (large item).'],
                'severity_level' => 'medium',
                'method_used' => 'skipped',
                'warnings' => ['Large item skipped automatic comparison'],
                'raw_scores' => null,
                'metadata' => [
                    'reference_path' => basename($referencePath),
                    'return_path' => basename($returnPath),
                    'item_size' => $itemSize,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'version' => '2.0',
                ],
            ];
        }

        // Check GD extension
        if (!extension_loaded('gd')) {
            return [
                'success' => false,
                'similarity' => 0,
                'detected_issues_text' => 'Image processing unavailable (GD extension missing)',
                'severity_level' => 'high',
                'warnings' => ['GD extension not available'],
            ];
        }

        // Check file readability
        if (!is_readable($referencePath)) {
            $warnings[] = 'Reference image not readable: ' . basename($referencePath);
        }
        if (!is_readable($returnPath)) {
            $warnings[] = 'Return image not readable: ' . basename($returnPath);
        }
        
        if (count($warnings) > 0) {
            return [
                'success' => false,
                'similarity' => 0,
                'detected_issues_text' => 'Could not read one or both images',
                'severity_level' => 'high',
                'warnings' => $warnings,
            ];
        }

        // Normalize images for comparison
        [$referenceImage, $refWarning] = normalizeImageForComparison($referencePath, (int)$config['resize']);
        [$returnImage, $retWarning] = normalizeImageForComparison($returnPath, (int)$config['resize']);
        [$referenceColorImage, $refColorWarning] = loadColorNormalizedImage($referencePath, (int)$config['resize']);
        [$returnColorImage, $retColorWarning] = loadColorNormalizedImage($returnPath, (int)$config['resize']);

        if ($refWarning) $warnings[] = $refWarning;
        if ($retWarning) $warnings[] = $retWarning;
        if ($refColorWarning) $warnings[] = $refColorWarning;
        if ($retColorWarning) $warnings[] = $retColorWarning;

        if (!$referenceImage || !$returnImage) {
            if ($referenceImage) imagedestroy($referenceImage);
            if ($returnImage) imagedestroy($returnImage);
            if ($referenceColorImage) imagedestroy($referenceColorImage);
            if ($returnColorImage) imagedestroy($returnColorImage);
            
            return [
                'success' => false,
                'similarity' => 0,
                'detected_issues_text' => 'Failed to process images for comparison',
                'severity_level' => 'high',
                'warnings' => $warnings,
            ];
        }

        // Align brightness/contrast before scoring
        $referenceStats = calculateLuminanceStats($referenceImage);
        $returnStats = calculateLuminanceStats($returnImage);

        // Two-pass normalization to avoid dimming good reference when return is blank
        $targetRefMean = $referenceStats['mean'];
        $targetRefStd = max(25.0, min(90.0, $referenceStats['stddev']));
        adjustImageLuminance($referenceImage, $targetRefMean, $targetRefStd, $referenceStats);

        $targetRetMean = ($referenceStats['mean']);
        $targetRetStd = max(20.0, min(80.0, $returnStats['stddev']));
        adjustImageLuminance($returnImage, $targetRetMean, $targetRetStd, $returnStats);

        // Calculate similarity scores
        $ssimScore = computeSSIMScore($referenceImage, $returnImage) * 100;
        $phashScore = computePerceptualHashSimilarity($referenceImage, $returnImage, (int)$config['hash_size']) * 100;
        $pixelScore = computePixelDifferenceScore($referenceImage, $returnImage) * 100;
        
        // Additional metrics for better detection
        if ($referenceColorImage && $returnColorImage) {
            $histScore = computeHistogramSimilarity($referenceColorImage, $returnColorImage) * 100;
            $histScore = sqrt(max(0.0, min(1.0, $histScore / 100))) * 100;
        } else {
            $histScore = 0.0;
        }
        $edgeDiffPct = computeEdgeDifference($referenceImage, $returnImage);
        $gradientSimilarity = computeGradientOrientationSimilarity($referenceImage, $returnImage) * 100;

        // Detect potential object absence or major structural mismatch
        $objectPresenceScore = computeObjectPresenceScore($referenceImage, $returnImage) * 100;

        // Weighted combination with all metrics
        $weights = $config['weights'];
        $ssimWeight = $weights['ssim'] ?? 0.50;
        $phashWeight = $weights['phash'] ?? 0.30;
        $histWeight = $weights['hist'] ?? 0.10;
        $edgeWeightConfigured = $weights['edge'] ?? 0.10;
        $gradientWeight = $weights['gradient'] ?? 0.0;

        $gradientFactor = min(1.0, max(0.0, $gradientSimilarity / 100.0));
        $edgeAttenuation = 0.5 + 0.5 * (1.0 - $gradientFactor);
        $edgeWeight = $edgeWeightConfigured * $edgeAttenuation;

        $weightSum = max(0.01, $ssimWeight + $phashWeight + $histWeight + $edgeWeight + $gradientWeight);

        $combined = (
            $ssimWeight * $ssimScore +
            $phashWeight * $phashScore +
            $histWeight * $histScore +
            $edgeWeight * (100 - ($edgeDiffPct * 100)) +
            $gradientWeight * $gradientSimilarity
        ) / $weightSum;
        $combined = max(0, min(100, $combined));

        // Apply hard penalty if object appears missing or completely different
        if ($objectPresenceScore < 28.0) {
            $combined = min($combined, 30.0);
        } elseif ($objectPresenceScore < 40.0 && $edgeDiffPct > 0.25) {
            $combined = min($combined, 35.0);
        }

        // Determine confidence band and detected issues
        $confidenceBand = 'low';
        $severityLevel = 'high';
        $baseMessage = 'Item mismatch detected – please check return.';
        $detailMessages = [];
        
        if ($combined >= 70) {
            $confidenceBand = 'high';
            $severityLevel = 'none';
            $baseMessage = 'Item returned successfully – no damages detected.';
        } elseif ($combined >= 50) {
            $confidenceBand = 'medium';
            $severityLevel = 'medium';
            $baseMessage = 'Minor visual difference detected – verify manually.';
        }

        // Additional detail cues
        if ($combined < 50) {
            if ($objectPresenceScore < 45.0) {
                $detailMessages[] = 'Object structure not recognized';
            }
            if ($edgeDiffPct > 0.18 && $gradientSimilarity < 70) {
                $detailMessages[] = 'Significant shape difference detected';
            }
            if ($histScore < 55) {
                $detailMessages[] = 'Major color or brightness inconsistency';
            }
            if ($ssimScore < 45) {
                $detailMessages[] = 'Low structural similarity';
            }
        } elseif ($combined < 70) {
            if ($edgeDiffPct > 0.12 && $gradientSimilarity < 80) {
                $detailMessages[] = 'Shape difference observed';
            }
            if ($histScore < 75) {
                $detailMessages[] = 'Color/lighting variation detected';
            }
            if ($ssimScore < 70 && $phashScore > 60) {
                $detailMessages[] = 'Surface texture variation detected';
            }
        }

        $detectedIssuesList = array_merge([$baseMessage], $detailMessages);
        $detectedIssuesList = array_values(array_filter(array_map('normalizeIssueMessage', $detectedIssuesList), static function ($msg) {
            return $msg !== '';
        }));
        $detectedIssuesText = implode("\n", array_map(static function ($message) {
            $trimmed = trim($message);
            if ($trimmed === '') {
                return $trimmed;
            }
            // Ensure consistent capitalization and trailing period
            $normalized = ucfirst($trimmed);
            if (substr($normalized, -1) !== '.') {
                $normalized .= '.';
            }
            return $normalized;
        }, $detectedIssuesList));
        $detectedIssuesText = trim($detectedIssuesText);

        // Clean up GD resources
        imagedestroy($referenceImage);
        imagedestroy($returnImage);
        if ($referenceColorImage) imagedestroy($referenceColorImage);
        if ($returnColorImage) imagedestroy($returnColorImage);

        $roundedSimilarity = round($combined, 2);
        $roundedSSIM = round($ssimScore, 2);
        $roundedPhash = round($phashScore, 2);
        $roundedPixel = round($pixelScore, 2);
        $roundedHist = round($histScore, 2);
        $roundedEdge = round($edgeDiffPct * 100, 2);
        $roundedGradient = round($gradientSimilarity, 2);

        return [
            'success' => true,
            'similarity' => $roundedSimilarity,
            'ssim_score' => $roundedSSIM,
            'phash_score' => $roundedPhash,
            'pixel_score' => $roundedPixel,
            'pixel_difference_score' => $roundedPixel, // backward compatibility
            'hist_score' => $roundedHist,
            'histogram_score' => $roundedHist,
            'edge_diff_pct' => $roundedEdge,
            'edge_difference_pct' => $roundedEdge,
            'gradient_score' => $roundedGradient,
            'object_presence_score' => round($objectPresenceScore, 2),
            'confidence_band' => $confidenceBand,
            'detected_issues_text' => $detectedIssuesText,
            'detected_issues_list' => $detectedIssuesList,
            'severity_level' => $severityLevel,
            'method_used' => 'hybrid',
            'warnings' => $warnings,
            'raw_scores' => [
                'weights' => $weights,
                'effective_edge_weight' => $edgeWeight,
                'edge_attenuation' => $edgeAttenuation,
                'weight_sum' => $weightSum,
                'ssim' => $ssimScore,
                'phash' => $phashScore,
                'histogram' => $histScore,
                'edge_difference' => $edgeDiffPct,
                'gradient' => $gradientSimilarity,
                'object_presence' => $objectPresenceScore,
                'pixel_similarity' => $pixelScore,
            ],
            'metadata' => [
                'reference_path' => basename($referencePath),
                'return_path' => basename($returnPath),
                'item_size' => $config['item_size'],
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0',
            ],
        ];
    }

    /**
     * Normalize image for comparison (resize + grayscale)
     */
    function normalizeImageForComparison(string $path, int $size = 256): array {
        $warning = null;
        $contents = @file_get_contents($path);
        
        if ($contents === false) {
            return [null, 'Failed to read: ' . basename($path)];
        }

        $source = @imagecreatefromstring($contents);
        if (!$source) {
            return [null, 'Invalid image: ' . basename($path)];
        }

        $width = max(1, imagesx($source));
        $height = max(1, imagesy($source));

        $canvas = imagecreatetruecolor($size, $size);
        if (!$canvas) {
            imagedestroy($source);
            return [null, 'Failed to create canvas'];
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $size, $size, $width, $height)) {
            $warning = 'Resample failed: ' . basename($path);
        }

        if (!imagefilter($canvas, IMG_FILTER_GRAYSCALE)) {
            $warning = 'Grayscale conversion failed: ' . basename($path);
        }

        imagedestroy($source);
        return [$canvas, $warning];
    }

    /**
     * Load color image with adaptive normalization for histogram analysis
     */
    function loadColorNormalizedImage(string $path, int $size = 256): array {
        $warning = null;
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return [null, 'Failed to read: ' . basename($path)];
        }

        $source = @imagecreatefromstring($contents);
        if (!$source) {
            return [null, 'Invalid image: ' . basename($path)];
        }

        $width = max(1, imagesx($source));
        $height = max(1, imagesy($source));

        $canvas = imagecreatetruecolor($size, $size);
        if (!$canvas) {
            imagedestroy($source);
            return [null, 'Failed to create canvas'];
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $size, $size, $width, $height)) {
            $warning = 'Resample failed: ' . basename($path);
        }

        applyAdaptiveEqualization($canvas, 32, 2.0);

        imagedestroy($source);
        return [$canvas, $warning];
    }

    /**
     * Apply simple CLAHE-style adaptive equalization for color images
     */
    function applyAdaptiveEqualization($img, int $tileSize = 32, float $clipLimit = 2.0): void {
        $width = imagesx($img);
        $height = imagesy($img);

        $tileSize = max(8, min(128, $tileSize));
        $tilesX = (int)ceil($width / $tileSize);
        $tilesY = (int)ceil($height / $tileSize);
        $clipLimit = max(0.5, min(8.0, $clipLimit));

        for ($ty = 0; $ty < $tilesY; $ty++) {
            $yStart = $ty * $tileSize;
            $yEnd = min($height, $yStart + $tileSize);

            for ($tx = 0; $tx < $tilesX; $tx++) {
                $xStart = $tx * $tileSize;
                $xEnd = min($width, $xStart + $tileSize);

                $histR = array_fill(0, 256, 0);
                $histG = array_fill(0, 256, 0);
                $histB = array_fill(0, 256, 0);
                $pixelCount = 0;

                for ($y = $yStart; $y < $yEnd; $y++) {
                    for ($x = $xStart; $x < $xEnd; $x++) {
                        $color = imagecolorat($img, $x, $y);
                        $r = ($color >> 16) & 0xFF;
                        $g = ($color >> 8) & 0xFF;
                        $b = $color & 0xFF;

                        $histR[$r]++;
                        $histG[$g]++;
                        $histB[$b]++;
                        $pixelCount++;
                    }
                }

                if ($pixelCount === 0) {
                    continue;
                }

                $clipThreshold = max(1, (int)round(($clipLimit * $pixelCount) / 256.0));

                $histR = clipHistogram($histR, $clipThreshold);
                $histG = clipHistogram($histG, $clipThreshold);
                $histB = clipHistogram($histB, $clipThreshold);

                $mapR = buildEqualizationMap($histR, $pixelCount);
                $mapG = buildEqualizationMap($histG, $pixelCount);
                $mapB = buildEqualizationMap($histB, $pixelCount);

                $colorCache = [];

                for ($y = $yStart; $y < $yEnd; $y++) {
                    for ($x = $xStart; $x < $xEnd; $x++) {
                        $color = imagecolorat($img, $x, $y);
                        $r = ($color >> 16) & 0xFF;
                        $g = ($color >> 8) & 0xFF;
                        $b = $color & 0xFF;

                        $nr = $mapR[$r];
                        $ng = $mapG[$g];
                        $nb = $mapB[$b];

                        $key = ($nr << 16) | ($ng << 8) | $nb;
                        if (!isset($colorCache[$key])) {
                            $colorCache[$key] = imagecolorallocate($img, $nr, $ng, $nb);
                        }

                        imagesetpixel($img, $x, $y, $colorCache[$key]);
                    }
                }
            }
        }
    }

    function clipHistogram(array $hist, int $clipThreshold): array {
        $bins = count($hist);
        $excess = 0;
        for ($i = 0; $i < $bins; $i++) {
            if ($hist[$i] > $clipThreshold) {
                $excess += $hist[$i] - $clipThreshold;
                $hist[$i] = $clipThreshold;
            }
        }

        if ($excess > 0) {
            $increment = (int)floor($excess / $bins);
            $remainder = $excess % $bins;
            for ($i = 0; $i < $bins; $i++) {
                $hist[$i] += $increment;
            }
            for ($i = 0; $i < $remainder; $i++) {
                $hist[$i]++;
            }
        }

        return $hist;
    }

    function buildEqualizationMap(array $hist, int $pixelCount): array {
        $bins = count($hist);
        $cdf = 0;
        $scale = 255 / max(1, $pixelCount);
        $map = array_fill(0, $bins, 0);

        for ($i = 0; $i < $bins; $i++) {
            $cdf += $hist[$i];
            $value = (int)round($cdf * $scale);
            if ($value < 0) $value = 0;
            if ($value > 255) $value = 255;
            $map[$i] = $value;
        }

        return $map;
    }

    /**
     * Compute SSIM (Structural Similarity Index) score
     */
    function computeSSIMScore($img1, $img2): float {
        $width = imagesx($img1);
        $height = imagesy($img1);

        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            return 0.0;
        }

        $windowSize = 8;
        $stride = 4;
        $C1 = (0.01 * 255) ** 2;
        $C2 = (0.03 * 255) ** 2;

        $total = 0.0;
        $count = 0;

        for ($y = 0; $y <= $height - $windowSize; $y += $stride) {
            for ($x = 0; $x <= $width - $windowSize; $x += $stride) {
                $window1 = [];
                $window2 = [];

                for ($wy = 0; $wy < $windowSize; $wy++) {
                    for ($wx = 0; $wx < $windowSize; $wx++) {
                        $window1[] = imagecolorat($img1, $x + $wx, $y + $wy) & 0xFF;
                        $window2[] = imagecolorat($img2, $x + $wx, $y + $wy) & 0xFF;
                    }
                }

                $mu1 = array_sum($window1) / count($window1);
                $mu2 = array_sum($window2) / count($window2);

                $sigma1 = 0.0;
                $sigma2 = 0.0;
                $sigma12 = 0.0;

                $n = count($window1);
                for ($i = 0; $i < $n; $i++) {
                    $sigma1 += ($window1[$i] - $mu1) ** 2;
                    $sigma2 += ($window2[$i] - $mu2) ** 2;
                    $sigma12 += ($window1[$i] - $mu1) * ($window2[$i] - $mu2);
                }

                $sigma1 = max(0.0, $sigma1 / ($n - 1));
                $sigma2 = max(0.0, $sigma2 / ($n - 1));
                $sigma12 = $sigma12 / ($n - 1);

                $numerator = (2 * $mu1 * $mu2 + $C1) * (2 * $sigma12 + $C2);
                $denominator = (($mu1 ** 2) + ($mu2 ** 2) + $C1) * ($sigma1 + $sigma2 + $C2);

                if ($denominator > 0) {
                    $ssim = $numerator / $denominator;
                    $total += max(0.0, min(1.0, $ssim));
                    $count++;
                }
            }
        }

        return $count > 0 ? ($total / $count) : 0.0;
    }

    /**
     * Compute perceptual hash similarity
     */
    function computePerceptualHashSimilarity($img1, $img2, int $hashSize = 16): float {
        $hash1 = generateAverageHash($img1, $hashSize);
        $hash2 = generateAverageHash($img2, $hashSize);

        $length = max(strlen($hash1), strlen($hash2));
        if ($length === 0) return 0.0;

        $distance = 0;
        for ($i = 0; $i < $length; $i++) {
            if (($hash1[$i] ?? '0') !== ($hash2[$i] ?? '0')) {
                $distance++;
            }
        }

        return 1 - ($distance / $length);
    }

    /**
     * Generate average hash
     */
    function generateAverageHash($image, int $hashSize = 16): string {
        $hashSize = max(4, min(32, $hashSize));
        $resized = imagecreatetruecolor($hashSize, $hashSize);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $hashSize, $hashSize, imagesx($image), imagesy($image));
        imagefilter($resized, IMG_FILTER_GRAYSCALE);

        $total = 0.0;
        $pixels = [];
        for ($y = 0; $y < $hashSize; $y++) {
            for ($x = 0; $x < $hashSize; $x++) {
                $value = imagecolorat($resized, $x, $y) & 0xFF;
                $pixels[] = $value;
                $total += $value;
            }
        }
        $avg = $total / count($pixels);

        $hash = '';
        foreach ($pixels as $value) {
            $hash .= ($value >= $avg) ? '1' : '0';
        }

        imagedestroy($resized);
        return $hash;
    }

    /**
     * Compute pixel-level difference score
     */
    function computePixelDifferenceScore($img1, $img2): float {
        $width = imagesx($img1);
        $height = imagesy($img1);

        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            return 0.0;
        }

        $step = max(1, (int)floor($width * $height / 65536));
        $totalDiff = 0.0;
        $samples = 0;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $l1 = imagecolorat($img1, $x, $y) & 0xFF;
                $l2 = imagecolorat($img2, $x, $y) & 0xFF;
                $totalDiff += abs($l1 - $l2);
                $samples++;
            }
        }

        if ($samples === 0) return 0.0;

        $avgDiff = $totalDiff / $samples;
        $similarity = 1.0 - min(1.0, $avgDiff / 255.0);

        return max(0.0, min(1.0, $similarity));
    }

    /**
     * Analyse luminance statistics for normalization
     */
    function calculateLuminanceStats($img): array {
        $width = imagesx($img);
        $height = imagesy($img);
        $step = max(1, (int)floor(sqrt(($width * $height) / 65536)));
        $sum = 0.0;
        $sumSquares = 0.0;
        $count = 0;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $value = imagecolorat($img, $x, $y) & 0xFF;
                $sum += $value;
                $sumSquares += $value * $value;
                $count++;
            }
        }

        if ($count === 0) {
            return ['mean' => 128.0, 'stddev' => 64.0];
        }

        $mean = $sum / $count;
        $variance = max(1.0, ($sumSquares / $count) - ($mean * $mean));

        return [
            'mean' => $mean,
            'stddev' => sqrt($variance)
        ];
    }

    /**
     * Adjust image luminance to target mean and stddev
     */
    function adjustImageLuminance($img, float $targetMean, float $targetStd, array $stats): void {
        $width = imagesx($img);
        $height = imagesy($img);
        $currentMean = $stats['mean'] ?? 128.0;
        $currentStd = max(1.0, $stats['stddev'] ?? 64.0);

        $scale = $targetStd / $currentStd;
        $scale = max(0.5, min(2.5, $scale));
        $offset = $targetMean - ($currentMean * $scale);

        $colorCache = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $value = imagecolorat($img, $x, $y) & 0xFF;
                $adjusted = (int)round(($value * $scale) + $offset);
                if ($adjusted < 0) $adjusted = 0;
                if ($adjusted > 255) $adjusted = 255;

                if (!isset($colorCache[$adjusted])) {
                    $colorCache[$adjusted] = imagecolorallocate($img, $adjusted, $adjusted, $adjusted);
                }

                imagesetpixel($img, $x, $y, $colorCache[$adjusted]);
            }
        }
    }

    /**
     * Compute histogram similarity (color/brightness distribution)
     */
    function computeHistogramSimilarity($img1, $img2): float {
        $width = imagesx($img1);
        $height = imagesy($img1);

        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            return 0.0;
        }

        $bins = 16;
        $hist1 = [
            'r' => array_fill(0, $bins, 0.0),
            'g' => array_fill(0, $bins, 0.0),
            'b' => array_fill(0, $bins, 0.0),
        ];
        $hist2 = [
            'r' => array_fill(0, $bins, 0.0),
            'g' => array_fill(0, $bins, 0.0),
            'b' => array_fill(0, $bins, 0.0),
        ];

        $step = max(1, (int)floor(sqrt(($width * $height) / 16384)));
        $samples = 0;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $color1 = imagecolorat($img1, $x, $y);
                $color2 = imagecolorat($img2, $x, $y);

                $r1 = ($color1 >> 16) & 0xFF;
                $g1 = ($color1 >> 8) & 0xFF;
                $b1 = $color1 & 0xFF;

                $r2 = ($color2 >> 16) & 0xFF;
                $g2 = ($color2 >> 8) & 0xFF;
                $b2 = $color2 & 0xFF;

                $binR1 = min($bins - 1, (int)floor($r1 / 256 * $bins));
                $binG1 = min($bins - 1, (int)floor($g1 / 256 * $bins));
                $binB1 = min($bins - 1, (int)floor($b1 / 256 * $bins));

                $binR2 = min($bins - 1, (int)floor($r2 / 256 * $bins));
                $binG2 = min($bins - 1, (int)floor($g2 / 256 * $bins));
                $binB2 = min($bins - 1, (int)floor($b2 / 256 * $bins));

                $hist1['r'][$binR1]++;
                $hist1['g'][$binG1]++;
                $hist1['b'][$binB1]++;

                $hist2['r'][$binR2]++;
                $hist2['g'][$binG2]++;
                $hist2['b'][$binB2]++;

                $samples++;
            }
        }

        if ($samples === 0) {
            return 0.0;
        }

        $similarities = [];
        foreach (['r', 'g', 'b'] as $channel) {
            $similarities[] = calculateHistogramCorrelation($hist1[$channel], $hist2[$channel]);
        }

        $average = array_sum($similarities) / count($similarities);
        return max(0.0, min(1.0, $average));
    }

    function calculateHistogramCorrelation(array $hist1, array $hist2): float {
        $bins = count($hist1);
        if ($bins === 0 || $bins !== count($hist2)) {
            return 0.0;
        }

        $sum1 = array_sum($hist1);
        $sum2 = array_sum($hist2);

        if ($sum1 <= 0 || $sum2 <= 0) {
            return 0.0;
        }

        $mean1 = $sum1 / $bins;
        $mean2 = $sum2 / $bins;

        $numerator = 0.0;
        $denom1 = 0.0;
        $denom2 = 0.0;

        for ($i = 0; $i < $bins; $i++) {
            $diff1 = ($hist1[$i] - $mean1) / $sum1;
            $diff2 = ($hist2[$i] - $mean2) / $sum2;
            $numerator += $diff1 * $diff2;
            $denom1 += $diff1 * $diff1;
            $denom2 += $diff2 * $diff2;
        }

        $denominator = sqrt($denom1 * $denom2);
        if ($denominator <= 1e-8) {
            return 1.0;
        }

        $correlation = $numerator / $denominator;
        return max(0.0, min(1.0, ($correlation + 1.0) / 2.0));
    }

    /**
     * Compute edge difference (shape/structure changes)
     */
    function computeEdgeDifference($img1, $img2): float {
        $width = imagesx($img1);
        $height = imagesy($img1);
        
        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            return 1.0;
        }
        
        // Simple edge detection using Sobel-like operator
        $edges1 = detectEdges($img1);
        $edges2 = detectEdges($img2);
        
        $diffPixels = 0;
        $totalEdgePixels = 0;
        
        $step = max(1, (int)floor(sqrt($width * $height / 10000)));
        
        for ($y = 1; $y < $height - 1; $y += $step) {
            for ($x = 1; $x < $width - 1; $x += $step) {
                $e1 = $edges1[$y][$x] ?? 0;
                $e2 = $edges2[$y][$x] ?? 0;
                
                if ($e1 > 0 || $e2 > 0) {
                    $totalEdgePixels++;
                    if (abs($e1 - $e2) > 0.3) {
                        $diffPixels++;
                    }
                }
            }
        }
        
        if ($totalEdgePixels === 0) return 0.0;
        
        return min(1.0, $diffPixels / $totalEdgePixels);
    }

    /**
     * Simple edge detection
     */
    function detectEdges($img): array {
        $width = imagesx($img);
        $height = imagesy($img);
        $edges = [];
        
        for ($y = 1; $y < $height - 1; $y++) {
            $edges[$y] = [];
            for ($x = 1; $x < $width - 1; $x++) {
                $gx = 0;
                $gy = 0;
                
                // Sobel operator
                $p00 = imagecolorat($img, $x - 1, $y - 1) & 0xFF;
                $p01 = imagecolorat($img, $x, $y - 1) & 0xFF;
                $p02 = imagecolorat($img, $x + 1, $y - 1) & 0xFF;
                $p10 = imagecolorat($img, $x - 1, $y) & 0xFF;
                $p12 = imagecolorat($img, $x + 1, $y) & 0xFF;
                $p20 = imagecolorat($img, $x - 1, $y + 1) & 0xFF;
                $p21 = imagecolorat($img, $x, $y + 1) & 0xFF;
                $p22 = imagecolorat($img, $x + 1, $y + 1) & 0xFF;
                
                $gx = (-$p00 + $p02 - 2*$p10 + 2*$p12 - $p20 + $p22);
                $gy = (-$p00 - 2*$p01 - $p02 + $p20 + 2*$p21 + $p22);
                
                $magnitude = sqrt($gx * $gx + $gy * $gy) / 1448.0; // Normalize
                $edges[$y][$x] = min(1.0, $magnitude);
            }
        }
        
        return $edges;
    }

    /**
     * Compare gradient orientation histograms to gauge rotational similarity
     */
    function computeGradientOrientationSimilarity($img1, $img2): float {
        $width = imagesx($img1);
        $height = imagesy($img1);

        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            return 0.0;
        }

        if ($width < 3 || $height < 3) {
            return 0.0;
        }

        $bins = 12;
        $hist1 = array_fill(0, $bins, 0.0);
        $hist2 = array_fill(0, $bins, 0.0);

        $step = max(1, (int)floor(sqrt(($width * $height) / 16384)));
        $twoPi = 2.0 * M_PI;

        for ($y = 1; $y < $height - 1; $y += $step) {
            for ($x = 1; $x < $width - 1; $x += $step) {
                $gx1 = (imagecolorat($img1, $x + 1, $y) & 0xFF) - (imagecolorat($img1, $x - 1, $y) & 0xFF);
                $gy1 = (imagecolorat($img1, $x, $y + 1) & 0xFF) - (imagecolorat($img1, $x, $y - 1) & 0xFF);
                $mag1 = sqrt(($gx1 * $gx1) + ($gy1 * $gy1));

                $gx2 = (imagecolorat($img2, $x + 1, $y) & 0xFF) - (imagecolorat($img2, $x - 1, $y) & 0xFF);
                $gy2 = (imagecolorat($img2, $x, $y + 1) & 0xFF) - (imagecolorat($img2, $x, $y - 1) & 0xFF);
                $mag2 = sqrt(($gx2 * $gx2) + ($gy2 * $gy2));

                if ($mag1 > 4.0) {
                    $angle1 = atan2($gy1, $gx1);
                    if ($angle1 < 0) {
                        $angle1 += $twoPi;
                    }
                    $bin1 = (int)floor($angle1 / $twoPi * $bins);
                    if ($bin1 >= $bins) $bin1 = $bins - 1;
                    $hist1[$bin1] += $mag1;
                }

                if ($mag2 > 4.0) {
                    $angle2 = atan2($gy2, $gx2);
                    if ($angle2 < 0) {
                        $angle2 += $twoPi;
                    }
                    $bin2 = (int)floor($angle2 / $twoPi * $bins);
                    if ($bin2 >= $bins) $bin2 = $bins - 1;
                    $hist2[$bin2] += $mag2;
                }
            }
        }

        $sum1 = array_sum($hist1);
        $sum2 = array_sum($hist2);

        if ($sum1 <= 0 && $sum2 <= 0) {
            return 1.0;
        }

        if ($sum1 <= 0 || $sum2 <= 0) {
            return 0.0;
        }

        $similarity = 0.0;
        for ($i = 0; $i < $bins; $i++) {
            $p1 = $hist1[$i] / $sum1;
            $p2 = $hist2[$i] / $sum2;
            $similarity += min($p1, $p2);
        }

        return max(0.0, min(1.0, $similarity));
    }

    /**
     * Compute object presence score (detects missing or completely different objects)
     */
    function computeObjectPresenceScore($img1, $img2): float {
        $width = imagesx($img1);
        $height = imagesy($img1);

        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            return 0.0;
        }

        if ($width < 3 || $height < 3) {
            return 0.0;
        }

        // Count significant edge pixels in each image
        $edgeThreshold = 20;
        $edgeCount1 = 0;
        $edgeCount2 = 0;
        $totalPixels = 0;

        $step = max(1, (int)floor(sqrt(($width * $height) / 16384)));

        for ($y = 1; $y < $height - 1; $y += $step) {
            for ($x = 1; $x < $width - 1; $x += $step) {
                $gx1 = abs((imagecolorat($img1, $x + 1, $y) & 0xFF) - (imagecolorat($img1, $x - 1, $y) & 0xFF));
                $gy1 = abs((imagecolorat($img1, $x, $y + 1) & 0xFF) - (imagecolorat($img1, $x, $y - 1) & 0xFF));
                $mag1 = sqrt(($gx1 * $gx1) + ($gy1 * $gy1));

                $gx2 = abs((imagecolorat($img2, $x + 1, $y) & 0xFF) - (imagecolorat($img2, $x - 1, $y) & 0xFF));
                $gy2 = abs((imagecolorat($img2, $x, $y + 1) & 0xFF) - (imagecolorat($img2, $x, $y - 1) & 0xFF));
                $mag2 = sqrt(($gx2 * $gx2) + ($gy2 * $gy2));

                if ($mag1 > $edgeThreshold) {
                    $edgeCount1++;
                }
                if ($mag2 > $edgeThreshold) {
                    $edgeCount2++;
                }
                $totalPixels++;
            }
        }

        if ($totalPixels === 0) {
            return 0.0;
        }

        $edgeDensity1 = $edgeCount1 / $totalPixels;
        $edgeDensity2 = $edgeCount2 / $totalPixels;

        // If one image has very few edges (blank/blurry) and the other has many, object is likely missing
        if ($edgeDensity1 < 0.01 && $edgeDensity2 < 0.01) {
            // Both images are nearly blank; treat as poor object presence
            return 0.05;
        }

        if ($edgeDensity1 < 0.02 || $edgeDensity2 < 0.02) {
            return min($edgeDensity1, $edgeDensity2) / max(0.001, max($edgeDensity1, $edgeDensity2));
        }

        // Compare edge density ratio
        $densityRatio = min($edgeDensity1, $edgeDensity2) / max(0.001, max($edgeDensity1, $edgeDensity2));

        // Compare spatial overlap of edges
        $overlapCount = 0;
        for ($y = 1; $y < $height - 1; $y += $step) {
            for ($x = 1; $x < $width - 1; $x += $step) {
                $gx1 = abs((imagecolorat($img1, $x + 1, $y) & 0xFF) - (imagecolorat($img1, $x - 1, $y) & 0xFF));
                $gy1 = abs((imagecolorat($img1, $x, $y + 1) & 0xFF) - (imagecolorat($img1, $x, $y - 1) & 0xFF));
                $mag1 = sqrt(($gx1 * $gx1) + ($gy1 * $gy1));

                $gx2 = abs((imagecolorat($img2, $x + 1, $y) & 0xFF) - (imagecolorat($img2, $x - 1, $y) & 0xFF));
                $gy2 = abs((imagecolorat($img2, $x, $y + 1) & 0xFF) - (imagecolorat($img2, $x, $y - 1) & 0xFF));
                $mag2 = sqrt(($gx2 * $gx2) + ($gy2 * $gy2));

                if ($mag1 > $edgeThreshold && $mag2 > $edgeThreshold) {
                    $overlapCount++;
                }
            }
        }

        $overlapRatio = $overlapCount / max(1, min($edgeCount1, $edgeCount2));

        // Combine density ratio and spatial overlap
        $presenceScore = ($densityRatio * 0.4 + $overlapRatio * 0.6);

        return max(0.0, min(1.0, $presenceScore));
    }

    /**
     * Generate comparison preview image (side-by-side with diff overlay)
     */
    function generateComparisonPreview(string $referencePath, string $returnPath, string $outputPath): bool {
        $ref = @imagecreatefromstring(@file_get_contents($referencePath));
        $ret = @imagecreatefromstring(@file_get_contents($returnPath));
        
        if (!$ref || !$ret) {
            if ($ref) imagedestroy($ref);
            if ($ret) imagedestroy($ret);
            return false;
        }
        
        $thumbSize = 200;
        $refThumb = imagecreatetruecolor($thumbSize, $thumbSize);
        $retThumb = imagecreatetruecolor($thumbSize, $thumbSize);
        
        imagecopyresampled($refThumb, $ref, 0, 0, 0, 0, $thumbSize, $thumbSize, imagesx($ref), imagesy($ref));
        imagecopyresampled($retThumb, $ret, 0, 0, 0, 0, $thumbSize, $thumbSize, imagesx($ret), imagesy($ret));
        
        // Create combined preview
        $preview = imagecreatetruecolor($thumbSize * 2 + 20, $thumbSize + 40);
        $white = imagecolorallocate($preview, 255, 255, 255);
        $black = imagecolorallocate($preview, 0, 0, 0);
        imagefill($preview, 0, 0, $white);
        
        // Add labels
        imagestring($preview, 3, 10, 5, 'Reference', $black);
        imagestring($preview, 3, $thumbSize + 30, 5, 'Return', $black);
        
        // Copy thumbnails
        imagecopy($preview, $refThumb, 10, 25, 0, 0, $thumbSize, $thumbSize);
        imagecopy($preview, $retThumb, $thumbSize + 20, 25, 0, 0, $thumbSize, $thumbSize);
        
        $result = imagejpeg($preview, $outputPath, 85);
        
        imagedestroy($ref);
        imagedestroy($ret);
        imagedestroy($refThumb);
        imagedestroy($retThumb);
        imagedestroy($preview);
        
        return $result;
    }
}