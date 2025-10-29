# AI Image Comparison Setup

This directory contains the AI-based image comparison system using CLIP (Contrastive Language-Image Pre-training) for enhanced equipment return verification.

## Requirements

### Python Dependencies
```bash
pip install torch torchvision torchaudio
pip install pillow
pip install numpy
pip install opencv-python
pip install transformers
pip install scipy
```

### System Requirements
- Python 3.7 or higher
- At least 2GB RAM (4GB recommended for CLIP model)
- Internet connection for first-time model download (~350MB)

## Installation

1. **Install Python** (if not already installed)
   - Windows: Download from https://www.python.org/downloads/
   - Ensure "Add Python to PATH" is checked during installation

2. **Install Dependencies**
   ```bash
   cd C:\xampp\htdocs\Capstone
   pip install torch torchvision torchaudio
   pip install pillow numpy opencv-python transformers scipy
   ```

3. **Test the Script**
   ```bash
   python ai/compare_images.py path/to/reference.jpg path/to/return.jpg
   ```

4. **Configure PHP**
   - Edit `config/ai_config.php`
   - Set `AI_PYTHON_PATH` to your Python executable path
   - Example Windows: `C:\Python39\python.exe`
   - Example Linux: `/usr/bin/python3`

## How It Works

### 1. CLIP Model
- Uses OpenAI's CLIP vision model to understand image content
- Computes semantic similarity between reference and return images
- More robust to lighting, angle, and background changes than pixel-based methods

### 2. Visual Analysis
- OpenCV-based analysis for detailed issue detection:
  - Color histogram comparison
  - Brightness/lighting differences
  - Edge detection for structural changes
  - Surface texture analysis

### 3. Score Blending
- Combines offline (SSIM + pHash) and AI scores
- Weights adjusted based on AI confidence:
  - High confidence (≥0.8): 60% offline, 40% AI
  - Medium confidence (0.5-0.8): 70% offline, 30% AI
  - Low confidence (<0.5): 100% offline (fallback)

### 4. Issue Detection
- Generates human-readable detected issues:
  - "Item returned successfully – no damages detected"
  - "Minor surface scratches detected"
  - "Significant color variation detected"
  - "Structural differences or damage detected"

## Output Format

The script outputs JSON:
```json
{
  "ai_similarity_score": 85.32,
  "ai_confidence": 0.92,
  "ai_detected_issues": [
    "Item returned successfully – no damages detected."
  ],
  "ai_issue_labels": ["no_damage"],
  "visual_analysis": [],
  "model_version": "clip-vit-base-patch32-v1.0",
  "status": "success"
}
```

## Troubleshooting

### "Python not found"
- Ensure Python is installed and added to PATH
- Update `AI_PYTHON_PATH` in `config/ai_config.php`

### "Module not found"
- Run: `pip install <module_name>`
- Ensure you're using the correct Python environment

### "Model download failed"
- Check internet connection
- First run downloads ~350MB CLIP model from Hugging Face
- Model cached in `~/.cache/huggingface/`

### "Out of memory"
- CLIP requires ~2GB RAM
- Close other applications
- Consider using smaller model variant

### "AI inference too slow"
- First run is slower (model loading + download)
- Subsequent runs use cached model (~2-5 seconds)
- Consider GPU acceleration for faster inference

## Performance

- **First run**: 10-30 seconds (model download + inference)
- **Subsequent runs**: 2-5 seconds (cached model)
- **With GPU**: <1 second (requires CUDA setup)

## Fallback Behavior

If AI inference fails:
1. System logs the error
2. Falls back to offline comparison results
3. User sees "AI inference unavailable - using offline results"
4. Return process continues normally

## Configuration

Edit `config/ai_config.php`:
- `AI_COMPARISON_ENABLED`: Enable/disable AI (true/false)
- `AI_PYTHON_PATH`: Path to Python executable
- `AI_MIN_CONFIDENCE`: Minimum confidence for blending (0.0-1.0)
- `AI_MISMATCH_THRESHOLD`: Score difference for warnings (default: 20)

## Logs

AI execution logs stored in: `logs/ai_comparison.log`
- Execution times
- Errors and warnings
- Model versions used

## Model Information

- **Model**: OpenAI CLIP ViT-B/32
- **Size**: ~350MB
- **Input**: RGB images (any size, auto-resized)
- **Output**: Similarity score 0-100, confidence 0-1
- **License**: MIT (OpenAI)
