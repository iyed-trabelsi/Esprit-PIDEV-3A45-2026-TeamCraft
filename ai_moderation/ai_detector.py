#!/usr/bin/env python3
"""
Local AI Image Moderation - NudeNet-based detection engine.
Uses NudeNet to identify specific exposed body parts (breasts, genitalia, etc.).
Runs entirely offline.
"""

import argparse
import json
import os
import sys
from pathlib import Path

# NudeNet labels that we consider problematic
SHARP_LABELS = [
    "BUTTOCKS_EXPOSED",
    "FEMALE_BREAST_EXPOSED",
    "FEMALE_GENITALIA_EXPOSED",
    "ANUS_EXPOSED",
    "MALE_GENITALIA_EXPOSED",
]

ALLOWED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".gif", ".webp"}
MAX_IMAGE_SIZE_MB = 20


def main() -> None:
    parser = argparse.ArgumentParser(description="Analyze image for moderation using NudeNet")
    parser.add_argument("image_path", type=str, help="Absolute path to the image file")
    args = parser.parse_args()

    # Output is always JSON (success or error)
    result = run_analysis(args.image_path)
    print(json.dumps(result, ensure_ascii=False))


def run_analysis(image_path: str) -> dict:
    """Validate input, load NudeNet, compute detections, return structured JSON."""
    # 1. Validate path
    path = Path(image_path).resolve()
    if not path.exists():
        return output_error("FILE_NOT_FOUND", f"Image not found: {image_path}")
    if not path.is_file():
        return output_error("INVALID_INPUT", "Path is not a file")
    
    suffix = path.suffix.lower()
    if suffix not in ALLOWED_EXTENSIONS:
        return output_error(
            "INVALID_TYPE",
            f"Allowed types: {', '.join(ALLOWED_EXTENSIONS)}",
        )
    try:
        size_mb = path.stat().st_size / (1024 * 1024)
        if size_mb > MAX_IMAGE_SIZE_MB:
            return output_error("FILE_TOO_LARGE", f"Max size {MAX_IMAGE_SIZE_MB}MB")
    except OSError as e:
        return output_error("IO_ERROR", str(e))

    # 2. Load NudeNet
    try:
        from nudenet import NudeDetector
    except ImportError as e:
        return output_error("DEPENDENCY_ERROR", f"Missing dependency: {e}")

    try:
        # Initializing detector (might download/load model)
        detector = NudeDetector()
    except Exception as e:
        return output_error("MODEL_LOAD_ERROR", str(e))

    # 3. Detect
    try:
        detections = detector.detect(str(path))
    except Exception as e:
        return output_error("INFERENCE_ERROR", str(e))

    # 4. Build label -> score map (taking max score if multiple detections of same type)
    scores = {}
    for det in detections:
        label = det["class"]
        score = float(det["score"])
        if label not in scores or score > scores[label]:
            scores[label] = round(score, 6)

    # Ensure all Sharp labels have at least 0.0 if not detected
    for label in SHARP_LABELS:
        if label not in scores:
            scores[label] = 0.0

    return {
        "success": True,
        "scores": scores,
        "labels": SHARP_LABELS, # The core labels PHP should check
    }


def output_error(code: str, message: str) -> dict:
    return {
        "success": False,
        "error": {
            "code": code,
            "message": message,
        },
        "scores": {},
    }


if __name__ == "__main__":
    main()
    sys.exit(0)
