<?php
// Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

if (!function_exists('calculateSSIM')) {
    /**
     * Calculate Structural Similarity Index (SSIM) between two images
     * Returns a value between -1 and 1, where 1 means identical
     * Uses a window-based approach for better accuracy
     */
    function calculateSSIM($img1, $img2, $windowSize = 8, $stride = 4) {
        if (!is_resource($img1) || !is_resource($img2)) {
            return 0;
        }

if (!function_exists('loadImageResource')) {
    /**
     * Load an image from disk and return a GD resource.
     */
    function loadImageResource(string $path)
    {
        if (!is_readable($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        try {
            switch ($info[2]) {
                case IMAGETYPE_JPEG:
                    return @imagecreatefromjpeg($path);
                case IMAGETYPE_PNG:
                    $img = @imagecreatefrompng($path);
                    if ($img) {
                        imagealphablending($img, false);
                        imagesavealpha($img, true);
                    }
                    return $img;
                case IMAGETYPE_GIF:
                    return @imagecreatefromgif($path);
                default:
                    return null;
            }
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('compareImagesPixelSimilarity')) {
    /**
     * Perform a lightweight pixel comparison returning similarity in the 0..1 range.
     */
    function compareImagesPixelSimilarity(string $referencePath, string $returnPath, int $step = 5, int $threshold = 30): ?float
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $ref = loadImageResource($referencePath);
        $ret = loadImageResource($returnPath);

        if (!$ref || !$ret) {
            if ($ref) {
                imagedestroy($ref);
            }
            if ($ret) {
                imagedestroy($ret);
            }
            return null;
        }

        $refWidth = imagesx($ref);
        $refHeight = imagesy($ref);
        $retWidth = imagesx($ret);
        $retHeight = imagesy($ret);

        if ($refWidth === 0 || $refHeight === 0 || $retWidth === 0 || $retHeight === 0) {
            imagedestroy($ref);
            imagedestroy($ret);
            return null;
        }

        $targetWidth = min($refWidth, $retWidth, 300);
        $targetHeight = min($refHeight, $retHeight, 300);

        $refResized = imagecreatetruecolor($targetWidth, $targetHeight);
        $retResized = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled($refResized, $ref, 0, 0, 0, 0, $targetWidth, $targetHeight, $refWidth, $refHeight);
        imagecopyresampled($retResized, $ret, 0, 0, 0, 0, $targetWidth, $targetHeight, $retWidth, $retHeight);

        $total = 0;
        $diff = 0;

        for ($x = 0; $x < $targetWidth; $x += $step) {
            for ($y = 0; $y < $targetHeight; $y += $step) {
                $rgb1 = imagecolorat($refResized, $x, $y);
                $rgb2 = imagecolorat($retResized, $x, $y);

                $r1 = ($rgb1 >> 16) & 0xFF;
                $g1 = ($rgb1 >> 8) & 0xFF;
                $b1 = $rgb1 & 0xFF;

                $r2 = ($rgb2 >> 16) & 0xFF;
                $g2 = ($rgb2 >> 8) & 0xFF;
                $b2 = $rgb2 & 0xFF;

                $pixelDiff = abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);
                if ($pixelDiff > $threshold) {
                    $diff++;
                }
                $total++;
            }
        }

        imagedestroy($ref);
        imagedestroy($ret);
        imagedestroy($refResized);
        imagedestroy($retResized);

        if ($total === 0) {
            return null;
        }

        $similarity = 1 - ($diff / $total);
        return max(0, min(1, $similarity));
    }
}

if (!function_exists('determineDetectedIssuesFromSimilarity')) {
    /**
     * Map similarity score (0..1) to a detected issues string and severity key.
     */
    function determineDetectedIssuesFromSimilarity(?float $similarity): array
    {
        if ($similarity === null) {
            return ['detected_issues' => 'Image comparison unavailable', 'severity' => 'unknown'];
        }

        if ($similarity > 0.90) {
            return ['detected_issues' => 'No issues detected', 'severity' => 'none'];
        }

        if ($similarity >= 0.70) {
            return ['detected_issues' => 'Minor scratches or dirt detected', 'severity' => 'minor'];
        }

        return ['detected_issues' => 'Major damage detected', 'severity' => 'major'];
    }
}
        
        $width = imagesx($img1);
        $height = imagesy($img1);
        
        // Ensure images are the same size
        if ($width !== imagesx($img2) || $height !== imagesy($img2)) {
            return 0;
        }
        
        // Constants for SSIM calculation (standard values)
        $C1 = (0.01 * 255) ** 2;
        $C2 = (0.03 * 255) ** 2;
        $C3 = $C2 / 2;
        
        $ssimTotal = 0;
        $windowCount = 0;
        
        // Process image in windows
        for ($y = 0; $y <= $height - $windowSize; $y += $stride) {
            for ($x = 0; $x <= $width - $windowSize; $x += $stride) {
                $window1 = [];
                $window2 = [];
                
                // Extract window pixels
                for ($wy = 0; $wy < $windowSize; $wy++) {
                    for ($wx = 0; $wx < $windowSize; $wx++) {
                        $px1 = imagecolorat($img1, $x + $wx, $y + $wy);
                        $px2 = imagecolorat($img2, $x + $wx, $y + $wy);
                        
                        // Convert to grayscale using standard luminance formula
                        $r1 = ($px1 >> 16) & 0xFF;
                        $g1 = ($px1 >> 8) & 0xFF;
                        $b1 = $px1 & 0xFF;
                        $l1 = (0.2126 * $r1 + 0.7152 * $g1 + 0.0722 * $b1);
                        
                        $r2 = ($px2 >> 16) & 0xFF;
                        $g2 = ($px2 >> 8) & 0xFF;
                        $b2 = $px2 & 0xFF;
                        $l2 = (0.2126 * $r2 + 0.7152 * $g2 + 0.0722 * $b2);
                        
                        $window1[] = $l1;
                        $window2[] = $l2;
                    }
                }
                
                // Calculate means
                $mu1 = array_sum($window1) / count($window1);
                $mu2 = array_sum($window2) / count($window2);
                
                // Calculate variances and covariance
                $sigma1_sq = 0;
                $sigma2_sq = 0;
                $sigma12 = 0;
                
                $count = count($window1);
                for ($i = 0; $i < $count; $i++) {
                    $sigma1_sq += pow($window1[$i] - $mu1, 2);
                    $sigma2_sq += pow($window2[$i] - $mu2, 2);
                    $sigma12 += ($window1[$i] - $mu1) * ($window2[$i] - $mu2);
                }
                
                $sigma1_sq = max(0, $sigma1_sq / ($count - 1));
                $sigma2_sq = max(0, $sigma2_sq / ($count - 1));
                $sigma12 = $sigma12 / ($count - 1);
                
                // Calculate SSIM for this window
                $numerator = (2 * $mu1 * $mu2 + $C1) * (2 * $sigma12 + $C2);
                $denominator = (($mu1 * $mu1) + ($mu2 * $mu2) + $C1) * ($sigma1_sq + $sigma2_sq + $C2);
                
                if ($denominator > 0) {
                    $ssim = $numerator / $denominator;
                    // Clamp to [0, 1] range
                    $ssim = max(0, min(1, $ssim));
                    $ssimTotal += $ssim;
                    $windowCount++;
                }
            }
        }
        
        // Return average SSIM across all windows
        return $windowCount > 0 ? $ssimTotal / $windowCount : 0;
    }
}

if (!function_exists('loadImageResource')) {
    /**
     * Compare two images and return a similarity score between 0 and 1
     * 1 means identical, 0 means completely different
     */
    function compareImagesSimilarity(string $baseImagePath, string $targetImagePath): ?float {
        // Check if GD extension is loaded
        if (!extension_loaded('gd')) {
            error_log('GD extension not loaded');
            return null;
        }

        // Verify files exist and are readable
        if (!is_readable($baseImagePath) || !is_readable($targetImagePath)) {
            error_log('One or both image files are not readable');
            return null;
        }

        // Get image info to verify they are valid images
        $baseInfo = @getimagesize($baseImagePath);
        $targetInfo = @getimagesize($targetImagePath);
        
        if ($baseInfo === false || $targetInfo === false) {
            error_log('One or both files are not valid images');
            return null;
        }

        // Check if images are too small
        if ($baseInfo[0] < 10 || $baseInfo[1] < 10 || $targetInfo[0] < 10 || $targetInfo[1] < 10) {
            error_log('Images are too small for comparison');
            return null;
        }

        // Load images with appropriate functions based on their type
        $base = null;
        $target = null;
        
        try {
            // Create image resources based on file type
            switch ($baseInfo[2]) {
                case IMAGETYPE_JPEG:
                    $base = @imagecreatefromjpeg($baseImagePath);
                    break;
                case IMAGETYPE_PNG:
                    $base = @imagecreatefrompng($baseImagePath);
                    // Preserve transparency
                    imagealphablending($base, false);
                    imagesavealpha($base, true);
                    break;
                case IMAGETYPE_GIF:
                    $base = @imagecreatefromgif($baseImagePath);
                    break;
                default:
                    error_log('Unsupported image type for base image');
                    return null;
            }
            
            switch ($targetInfo[2]) {
                case IMAGETYPE_JPEG:
                    $target = @imagecreatefromjpeg($targetImagePath);
                    break;
                case IMAGETYPE_PNG:
                    $target = @imagecreatefrompng($targetImagePath);
                    // Preserve transparency
                    imagealphablending($target, false);
                    imagesavealpha($target, true);
                    break;
                case IMAGETYPE_GIF:
                    $target = @imagecreatefromgif($targetImagePath);
                    break;
                default:
                    error_log('Unsupported image type for target image');
                    if ($base) imagedestroy($base);
                    return null;
            }

            if (!$base || !$target) {
                throw new Exception('Failed to load one or both images');
            }
            
            // Resize images to a common size for comparison (256x256 is a good balance between speed and accuracy)
            $width = 256;
        $height = 64;

        $baseResized = imagecreatetruecolor($width, $height);
        $targetResized = imagecreatetruecolor($width, $height);

        // Convert to grayscale and resize
        imagecopyresampled($baseResized, $base, 0, 0, 0, 0, $width, $height, imagesx($base), imagesy($base));
        imagecopyresampled($targetResized, $target, 0, 0, 0, 0, $width, $height, imagesx($target), imagesy($target));

        // Calculate SSIM (Structural Similarity Index)
        $ssim = calculateSSIM($baseResized, $targetResized);
        
        // Convert SSIM from [-1, 1] to [0, 100] scale
        $similarity = (($ssim + 1) / 2) * 100;

        // Clean up
        imagedestroy($base);
        imagedestroy($target);
        imagedestroy($baseResized);
        imagedestroy($targetResized);

        return max(0, min(100, $similarity));
    }
}

if (!function_exists('getImageVerdict')) {
    /**
     * Determine the verification verdict based on similarity score and item size
     */
    function getImageVerdict(float $similarity, string $sizeCategory): array {
        $verdict = '';
        $needsReview = true;
        $confidence = 'low';
        
        // Define thresholds based on item size
        $thresholds = [
            'small' => [
                'verified' => 0.92,  // 92% for auto-verification of small items
                'review' => 0.85,    // 85-92% needs review
                'damaged' => 0.85    // Below 85% flagged as damaged
            ],
            'medium' => [
                'verified' => 0.95,  // 95% for auto-verification of medium items
                'review' => 0.88,    // 88-95% needs review
                'damaged' => 0.88    // Below 88% flagged as damaged
            ],
            'large' => [
                'verified' => 1.0,   // Large items always need review
                'review' => 0.9,     // 90%+ can be auto-verified after review
                'damaged' => 0.9     // Below 90% flagged as damaged
            ]
        ][$sizeCategory] ?? [
            'verified' => 0.9,      // Default thresholds
            'review' => 0.8,
            'damaged' => 0.8
        ];
        
        // Determine verdict
        if ($similarity >= $thresholds['verified']) {
            $verdict = 'Verified Match';
            $needsReview = $sizeCategory === 'large'; // Large items always need review
            $confidence = 'high';
        } elseif ($similarity >= $thresholds['review']) {
            $verdict = 'Possible Damage – Needs Review';
            $needsReview = true;
            $confidence = 'medium';
        } else {
            $verdict = 'Flagged – Damage Detected';
            $needsReview = true;
            $confidence = 'high';
        }
        
        return [
            'verdict' => $verdict,
            'needsReview' => $needsReview,
            'confidence' => $confidence,
            'similarity' => $similarity,
            'thresholds' => $thresholds
        ];
    }
}

if (!function_exists('analyzeImageDifferences')) {
        /**
     * Analyze differences between reference and return images
     * 
     * @param string $referencePath Path to the reference image
     * @param string $returnPath Path to the returned item image
     * @param int $grid Number of grid cells for analysis (e.g., 4 = 4x4 grid)
     * @param string $sizeCategory Size category of the item (small, medium, large)
     * @return array Analysis results including similarity score and difference details
     */
    function analyzeImageDifferences(string $referencePath, string $returnPath, int $grid = 4, string $sizeCategory = 'medium'): array {
        $result = [
            'similarity' => 0,
            'verdict' => 'Unknown',
            'confidence' => 0,
            'issues' => [],
            'grid_analysis' => [],
            'metadata' => [
                'reference_size' => @getimagesize($referencePath),
                'return_size' => @getimagesize($returnPath),
                'comparison_time' => date('Y-m-d H:i:s')
            ]
        ];

        // Check if GD is loaded
        if (!extension_loaded('gd')) {
            $result['issues'][] = 'GD extension not available';
            $result['verdict'] = 'Error: GD extension required';
            return $result;
        }

        // Check if files are readable
        if (!is_readable($referencePath) || !is_readable($returnPath)) {
            $result['issues'][] = 'Could not read image files';
            $result['verdict'] = 'Error: File access denied';
            return $result;
        }

        // Load images
        $reference = @imagecreatefromstring(@file_get_contents($referencePath));
        $returned = @imagecreatefromstring(@file_get_contents($returnPath));

        if (!$reference || !$returned) {
            if ($reference) imagedestroy($reference);
            if ($returned) imagedestroy($returned);
            $result['issues'][] = 'Invalid image format';
            $result['verdict'] = 'Error: Invalid image format';
            return $result;
        }

        // Calculate overall similarity using SSIM
        $similarity = compareImagesSimilarity($referencePath, $returnPath);
        if ($similarity === null) {
            $result['verdict'] = 'Error: Could not compare images';
            return $result;
        }

        $result['similarity'] = round($similarity, 2);
        $result['confidence'] = min(100, max(0, $similarity));
        
        // Determine verdict based on similarity score
        if ($similarity >= 85) {
            $result['verdict'] = 'Verified Match';
        } elseif ($similarity >= 70) {
            $result['verdict'] = 'Possible Damage – Needs Review';
            $result['issues'][] = [
                'type' => 'possible_damage',
                'severity' => 'medium',
                'message' => 'Possible damage detected. Please review the item.'
            ];
        } else {
            $result['verdict'] = 'Flagged – Damage Detected';
            $result['issues'][] = [
                'type' => 'damage',
                'severity' => 'high',
                'message' => 'Significant differences detected. Item may be damaged.'
            ];
        }

        // Grid-based analysis for localized differences
        $width = imagesx($reference);
        $height = imagesy($returned);
        $blockWidth = (int)($width / $grid);
        $blockHeight = (int)($height / $grid);

        for ($i = 0; $i < $grid; $i++) {
            for ($j = 0; $j < $grid; $j++) {
                $x = $i * $blockWidth;
                $y = $j * $blockHeight;
                
                // Create temporary images for the grid cell
                $cellRef = imagecrop($reference, [
                    'x' => $x,
                    'y' => $y,
                    'width' => $blockWidth,
                    'height' => $blockHeight
                ]);
                
                $cellRet = imagecrop($returned, [
                    'x' => $x,
                    'y' => $y,
                    'width' => $blockWidth,
                    'height' => $blockHeight
                ]);
                
                if ($cellRef && $cellRet) {
                    // Save to temp files for comparison
                    $tempRef = tempnam(sys_get_temp_dir(), 'ref_');
                    $tempRet = tempnam(sys_get_temp_dir(), 'ret_');
                    
                    imagejpeg($cellRef, $tempRef, 90);
                    imagejpeg($cellRet, $tempRet, 90);
                    
                    $cellSimilarity = compareImagesSimilarity($tempRef, $tempRet);
                    
                    // Clean up temp files
                    @unlink($tempRef);
                    @unlink($tempRet);
                    
                    $result['grid_analysis'][] = [
                        'x' => $i,
                        'y' => $j,
                        'similarity' => $cellSimilarity,
                        'has_issue' => $cellSimilarity < 70,
                        'verdict' => $cellSimilarity >= 85 ? 'Good' : 
                                    ($cellSimilarity >= 70 ? 'Review' : 'Issue')
                    ];
                    
                    imagedestroy($cellRef);
                    imagedestroy($cellRet);
                }
            }
        }

        // Clean up
        imagedestroy($reference);
        imagedestroy($returned);

        return $result;
    }
}

if (!function_exists('drawGridOnImage')) {
    /**
     * Draw a grid on an image for visualization
     */
    function drawGridOnImage($image, $gridSize, $color) {
        $width = imagesx($image);
        $height = imagesy($image);
        $cellWidth = $width / $gridSize;
        $cellHeight = $height / $gridSize;
        
        // Draw vertical lines
        for ($i = 1; $i < $gridSize; $i++) {
            $x = (int)($i * $cellWidth);
            imageline($image, $x, 0, $x, $height - 1, $color);
        }
        
        // Draw horizontal lines
        for ($i = 1; $i < $gridSize; $i++) {
            $y = (int)($i * $cellHeight);
            imageline($image, 0, $y, $width - 1, $y, $color);
        }
        
        return $image;
    }
}

if (!function_exists('addTextToImage')) {
    /**
     * Add text to an image with a background
     */
    function addTextToImage($image, $text, $x, $y, $fontSize = 5, $textColor = null, $bgColor = null) {
        if ($textColor === null) {
            $textColor = imagecolorallocate($image, 255, 255, 255); // White
        }
        
        if ($bgColor === null) {
            $bgColor = imagecolorallocatealpha($image, 0, 0, 0, 50); // Semi-transparent black
        }
        
        // Get text dimensions
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        
        // Add background
        imagefilledrectangle(
            $image, 
            $x - 2, 
            $y - $textHeight - 2, 
            $x + $textWidth + 2, 
            $y + 2, 
            $bgColor
        );
        
        // Add text
        imagestring($image, $fontSize, $x, $y - $textHeight, $text, $textColor);
        
        return $image;
    }
}

if (!function_exists('generateComparisonPreview')) {
        /**
     * Generate a side-by-side comparison image with annotations
     * 
     * @param string $referencePath Path to the reference image
     * @param string $returnPath Path to the returned item image
     * @param string $outputPath Path to save the output image
     * @param array $analysis Optional analysis results from analyzeImageDifferences()
     * @return bool True on success, false on failure
     */
    function generateComparisonPreview(string $referencePath, string $returnPath, string $outputPath, array $analysis = []): bool {
        if (!extension_loaded('gd')) {
            return false;
        }

        // Load images with error handling
        $reference = @imagecreatefromstring(@file_get_contents($referencePath));
        $returned = @imagecreatefromstring(@file_get_contents($returnPath));
        
        if (!$reference || !$returned) {
            if ($reference) imagedestroy($reference);
            if ($returned) imagedestroy($returned);
            return false;
        }

        // Get dimensions
        $refWidth = imagesx($reference);
        $refHeight = imagesy($reference);
        $retWidth = imagesx($returned);
        $retHeight = imagesy($returned);
        
        // Calculate new dimensions maintaining aspect ratio
        $maxWidth = 400;
        $maxHeight = 300;
        
        $refRatio = $refWidth / $refHeight;
        $retRatio = $retWidth / $retHeight;
        
        $newRefWidth = min($maxWidth, $refWidth);
        $newRefHeight = $newRefWidth / $refRatio;
        
        if ($newRefHeight > $maxHeight) {
            $newRefHeight = $maxHeight;
            $newRefWidth = $newRefHeight * $refRatio;
        }
        
        $newRetWidth = min($maxWidth, $retWidth);
        $newRetHeight = $newRetWidth / $retRatio;
        
        if ($newRetHeight > $maxHeight) {
            $newRetHeight = $maxHeight;
            $newRetWidth = $newRetHeight * $retRatio;
        }
        
        // Create resized images
        $refResized = imagecreatetruecolor($newRefWidth, $newRefHeight);
        $retResized = imagecreatetruecolor($newRetWidth, $newRetHeight);
        
        // Preserve transparency for PNGs
        imagealphablending($refResized, false);
        imagesavealpha($refResized, true);
        imagealphablending($retResized, false);
        imagesavealpha($retResized, true);
        
        // Resize images
        imagecopyresampled($refResized, $reference, 0, 0, 0, 0, $newRefWidth, $newRefHeight, $refWidth, $refHeight);
        imagecopyresampled($retResized, $returned, 0, 0, 0, 0, $newRetWidth, $newRetHeight, $retWidth, $retHeight);
        
        // Calculate canvas size
        $gap = 20;
        $padding = 20;
        $labelHeight = 30;
        $canvasWidth = $newRefWidth + $newRetWidth + $gap + ($padding * 2);
        $canvasHeight = max($newRefHeight, $newRetHeight) + $padding * 2 + $labelHeight;
        
        // Create canvas
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        
        // Fill background
        $bgColor = imagecolorallocate($canvas, 248, 249, 250);
        $borderColor = imagecolorallocate($canvas, 222, 226, 230);
        $textColor = imagecolorallocate($canvas, 33, 37, 41);
        $highlightColor = imagecolorallocate($canvas, 0, 123, 255);
        
        imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $bgColor);
        
        // Add title
        $title = 'Item Comparison - ' . date('Y-m-d H:i:s');
        $titleX = ($canvasWidth - 8 * strlen($title)) / 2;
        imagestring($canvas, 3, $titleX, 5, $title, $textColor);
        
        // Draw reference image with border
        $refX = $padding;
        $refY = $padding + $labelHeight;
        $refBorder = imagecreatetruecolor($newRefWidth + 4, $newRefHeight + 4);
        $borderColor = imagecolorallocate($refBorder, 206, 212, 218);
        imagefill($refBorder, 0, 0, $borderColor);
        imagecopy($refBorder, $refResized, 2, 2, 0, 0, $newRefWidth, $newRefHeight);
        imagecopy($canvas, $refBorder, $refX - 2, $refY - 2, 0, 0, $newRefWidth + 4, $newRefHeight + 4);
        
        // Draw return image with border
        $retX = $padding + $newRefWidth + $gap;
        $retY = $padding + $labelHeight;
        $retBorder = imagecreatetruecolor($newRetWidth + 4, $newRetHeight + 4);
        imagefill($retBorder, 0, 0, $borderColor);
        imagecopy($retBorder, $retResized, 2, 2, 0, 0, $newRetWidth, $newRetHeight);
        imagecopy($canvas, $retBorder, $retX - 2, $retY - 2, 0, 0, $newRetWidth + 4, $newRetHeight + 4);
        
        // Add labels
        $font = 3; // Built-in font (1-5)
        $labelY = $padding + $labelHeight + max($newRefHeight, $newRetHeight) + 5;
        
        // Add reference label with arrow
        $refLabel = 'Original (Borrowed)';
        $refLabelWidth = imagefontwidth($font) * strlen($refLabel);
        $refLabelX = $refX + (($newRefWidth - $refLabelWidth) / 2);
        imagestring($canvas, $font, $refLabelX, $refY - 20, $refLabel, $textColor);
        
        // Add return label with arrow
        $retLabel = 'Returned';
        $retLabelWidth = imagefontwidth($font) * strlen($retLabel);
        $retLabelX = $retX + (($newRetWidth - $retLabelWidth) / 2);
        imagestring($canvas, $font, $retLabelX, $retY - 20, $retLabel, $textColor);
        
        // Add comparison info
        $similarity = compareImagesSimilarity($referencePath, $returnPath);
        if ($similarity !== null) {
            $infoText = sprintf('Similarity: %.1f%%', $similarity);
            $infoX = $canvasWidth - $padding - (imagefontwidth($font) * strlen($infoText));
            imagestring($canvas, $font, $infoX, 5, $infoText, $highlightColor);
            
            // Add verdict
            $verdict = '';
            $verdictColor = $textColor;
            
            if ($similarity >= 85) {
                $verdict = '✓ Verified Match';
                $verdictColor = imagecolorallocate($canvas, 40, 167, 69); // Green
            } elseif ($similarity >= 70) {
                $verdict = '⚠ Needs Review';
                $verdictColor = imagecolorallocate($canvas, 255, 193, 7); // Yellow
            } else {
                $verdict = '✗ Damage Detected';
                $verdictColor = imagecolorallocate($canvas, 220, 53, 69); // Red
            }
            
            imagestring($canvas, $font + 1, $padding, 5, $verdict, $verdictColor);
        }
        
        // Save the output image
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        $result = imagejpeg($canvas, $outputPath, 90);
        
        // Clean up
        imagedestroy($canvas);
        imagedestroy($reference);
        imagedestroy($returned);
        imagedestroy($refResized);
        imagedestroy($retResized);
        imagedestroy($refBorder);
        imagedestroy($retBorder);
        
        return $result !== false;
    }
}
