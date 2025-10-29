#!/usr/bin/env python3
"""
AI-based image comparison using CLIP vision model
Compares two images and outputs similarity score, confidence, and detected issues
"""

import sys
import json
import os
from pathlib import Path

try:
    import torch
    from PIL import Image
    import numpy as np
    from transformers import CLIPProcessor, CLIPModel
    import cv2
except ImportError as e:
    print(json.dumps({
        'error': f'Missing dependency: {str(e)}',
        'ai_similarity_score': None,
        'ai_confidence': 0.0,
        'ai_detected_issues': ['AI inference unavailable - dependencies missing'],
        'ai_issue_labels': []
    }))
    sys.exit(1)


def load_clip_model():
    """Load CLIP model for image comparison"""
    try:
        model = CLIPModel.from_pretrained("openai/clip-vit-base-patch32")
        processor = CLIPProcessor.from_pretrained("openai/clip-vit-base-patch32")
        return model, processor
    except Exception as e:
        raise RuntimeError(f"Failed to load CLIP model: {str(e)}")


def compute_clip_similarity(image1_path, image2_path, model, processor):
    """Compute similarity between two images using CLIP embeddings"""
    try:
        # Load images
        image1 = Image.open(image1_path).convert('RGB')
        image2 = Image.open(image2_path).convert('RGB')
        
        # Process images
        inputs = processor(images=[image1, image2], return_tensors="pt", padding=True)
        
        # Get embeddings
        with torch.no_grad():
            image_features = model.get_image_features(**inputs)
        
        # Normalize embeddings
        image_features = image_features / image_features.norm(dim=-1, keepdim=True)
        
        # Compute cosine similarity
        similarity = torch.nn.functional.cosine_similarity(
            image_features[0:1], 
            image_features[1:2]
        ).item()
        
        # Convert from [-1, 1] to [0, 100] scale
        similarity_score = ((similarity + 1) / 2) * 100
        
        return similarity_score
        
    except Exception as e:
        raise RuntimeError(f"CLIP similarity computation failed: {str(e)}")


def analyze_visual_differences(image1_path, image2_path):
    """Analyze visual differences using OpenCV for detailed issue detection"""
    try:
        img1 = cv2.imread(image1_path)
        img2 = cv2.imread(image2_path)
        
        if img1 is None or img2 is None:
            return [], {}
        
        # Resize to same dimensions for comparison
        height = min(img1.shape[0], img2.shape[0])
        width = min(img1.shape[1], img2.shape[1])
        img1 = cv2.resize(img1, (width, height))
        img2 = cv2.resize(img2, (width, height))
        
        issues = []
        metrics = {}
        gray1 = cv2.cvtColor(img1, cv2.COLOR_BGR2GRAY)
        gray2 = cv2.cvtColor(img2, cv2.COLOR_BGR2GRAY)

        # Color histogram comparison (correlation)
        hist1 = cv2.calcHist([img1], [0, 1, 2], None, [16, 16, 16], [0, 256, 0, 256, 0, 256])
        hist2 = cv2.calcHist([img2], [0, 1, 2], None, [16, 16, 16], [0, 256, 0, 256, 0, 256])
        hist1 = cv2.normalize(hist1, hist1).flatten()
        hist2 = cv2.normalize(hist2, hist2).flatten()
        color_correlation = cv2.compareHist(hist1.astype('float32'), hist2.astype('float32'), cv2.HISTCMP_CORREL)
        metrics['histogram_correlation'] = round(float(color_correlation), 3)

        if color_correlation < 0.4:
            issues.append('Major color and texture mismatch detected')
        elif color_correlation < 0.75:
            issues.append('Noticeable color differences observed')

        # Brightness comparison
        brightness1 = float(np.mean(gray1))
        brightness2 = float(np.mean(gray2))
        brightness_diff = abs(brightness1 - brightness2)
        metrics['brightness_difference'] = round(brightness_diff, 2)
        metrics['brightness_reference'] = round(brightness1, 2)
        metrics['brightness_return'] = round(brightness2, 2)

        if brightness_diff > 60:
            issues.append('Severe lighting difference detected between reference and return images')
        elif brightness_diff > 30:
            issues.append('Moderate lighting difference detected')

        # Edge detection for structural changes
        edges1 = cv2.Canny(gray1, 50, 150)
        edges2 = cv2.Canny(gray2, 50, 150)
        edge_diff = np.sum(np.abs(edges1.astype(float) - edges2.astype(float))) / (height * width)
        metrics['edge_difference'] = round(float(edge_diff), 2)

        if edge_diff > 35:
            issues.append('Structural outline differs greatly – possible different item returned')
        elif edge_diff > 20:
            issues.append('Surface patterns and edges differ noticeably')

        # Feature matching (ORB)
        orb = cv2.ORB_create(600)
        kp1, des1 = orb.detectAndCompute(gray1, None)
        kp2, des2 = orb.detectAndCompute(gray2, None)
        keypoints_ref = len(kp1) if kp1 is not None else 0
        keypoints_ret = len(kp2) if kp2 is not None else 0
        metrics['keypoints_reference'] = keypoints_ref
        metrics['keypoints_return'] = keypoints_ret

        feature_match_ratio = 0.0
        if des1 is not None and des2 is not None and keypoints_ref > 0 and keypoints_ret > 0:
            bf = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)
            matches = bf.match(des1, des2)
            matches = sorted(matches, key=lambda m: m.distance)
            total_possible = min(keypoints_ref, keypoints_ret)
            if total_possible > 0:
                feature_match_ratio = len(matches) / total_possible
        metrics['feature_match_ratio'] = round(float(feature_match_ratio), 3)

        if feature_match_ratio < 0.1 and total_possible > 50:
            issues.append('Object features do not match the reference item (very low feature alignment)')
        elif feature_match_ratio < 0.25 and total_possible > 50:
            issues.append('Low feature alignment detected – item may differ from reference')

        # Blur detection (variance of Laplacian)
        lap1 = cv2.Laplacian(gray1, cv2.CV_64F).var()
        lap2 = cv2.Laplacian(gray2, cv2.CV_64F).var()
        metrics['sharpness_reference'] = round(float(lap1), 2)
        metrics['sharpness_return'] = round(float(lap2), 2)

        if lap2 < 20 and lap1 > (lap2 * 2):
            issues.append('Return photo appears blurry – surface details not clearly visible')

        return issues, metrics
        
    except Exception as e:
        return [f'Visual analysis error: {str(e)}'], {'error': str(e)}


def detect_issues(similarity_score, visual_issues, visual_metrics):
    """Generate detected issues based on similarity score and visual analysis"""
    issues = []
    labels = []
    
    if similarity_score >= 85:
        issues.append('Item returned successfully – no damages detected.')
        labels.append('no_damage')
    elif similarity_score >= 70:
        issues.append('Minor differences detected – item appears acceptable.')
        labels.append('minor_difference')
        if visual_issues:
            issues.extend(visual_issues[:2])  # Add top 2 visual issues
    elif similarity_score >= 50:
        issues.append('Moderate differences detected – manual review recommended.')
        labels.append('moderate_difference')
        if visual_issues:
            issues.extend(visual_issues)
    else:
        issues.append('Significant mismatch detected – possible damage or wrong item.')
        labels.append('major_mismatch')
        if visual_issues:
            issues.extend(visual_issues)
        else:
            issues.append('Item does not match reference image')
    
    return issues, labels


def compute_confidence(similarity_score, visual_issues_count):
    """Compute confidence score based on similarity and analysis depth"""
    # Base confidence from similarity score
    if similarity_score >= 85:
        base_confidence = 0.95
    elif similarity_score >= 70:
        base_confidence = 0.85
    elif similarity_score >= 50:
        base_confidence = 0.75
    else:
        base_confidence = 0.65
    
    # Adjust based on visual analysis
    if visual_issues_count > 0:
        base_confidence = min(base_confidence, 0.90)
    
    return base_confidence


def main():
    if len(sys.argv) != 3:
        print(json.dumps({
            'error': 'Usage: python compare_images.py <reference_image> <return_image>',
            'ai_similarity_score': None,
            'ai_confidence': 0.0,
            'ai_detected_issues': ['Invalid arguments'],
            'ai_issue_labels': []
        }))
        sys.exit(1)
    
    reference_path = sys.argv[1]
    return_path = sys.argv[2]
    
    # Validate paths
    if not os.path.exists(reference_path):
        print(json.dumps({
            'error': f'Reference image not found: {reference_path}',
            'ai_similarity_score': None,
            'ai_confidence': 0.0,
            'ai_detected_issues': ['Reference image not found'],
            'ai_issue_labels': []
        }))
        sys.exit(1)
    
    if not os.path.exists(return_path):
        print(json.dumps({
            'error': f'Return image not found: {return_path}',
            'ai_similarity_score': None,
            'ai_confidence': 0.0,
            'ai_detected_issues': ['Return image not found'],
            'ai_issue_labels': []
        }))
        sys.exit(1)
    
    try:
        # Load CLIP model
        model, processor = load_clip_model()
        
        # Compute similarity
        similarity_score = compute_clip_similarity(reference_path, return_path, model, processor)
        
        # Analyze visual differences
        visual_issues = analyze_visual_differences(reference_path, return_path)
        
        # Detect issues and generate labels
        detected_issues, issue_labels = detect_issues(similarity_score, visual_issues)
        
        # Compute confidence
        confidence = compute_confidence(similarity_score, len(visual_issues))
        
        # Output result as JSON
        result = {
            'ai_similarity_score': round(similarity_score, 2),
            'ai_confidence': round(confidence, 2),
            'ai_detected_issues': detected_issues,
            'ai_issue_labels': issue_labels,
            'visual_analysis': visual_issues,
            'model_version': 'clip-vit-base-patch32-v1.0',
            'status': 'success'
        }
        
        print(json.dumps(result))
        sys.exit(0)
        
    except Exception as e:
        print(json.dumps({
            'error': str(e),
            'ai_similarity_score': None,
            'ai_confidence': 0.0,
            'ai_detected_issues': [f'AI inference failed: {str(e)}'],
            'ai_issue_labels': ['error'],
            'status': 'failed'
        }))
        sys.exit(1)


if __name__ == '__main__':
    main()
