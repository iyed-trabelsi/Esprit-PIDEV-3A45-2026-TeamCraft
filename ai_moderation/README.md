# Local AI Image Moderation (CLIP)

Moteur de modération d’images pour les posts et commentaires du forum. **Aucune API externe** — tout tourne en local avec OpenAI CLIP (ViT-B/32) via Hugging Face Transformers.

## Prérequis

- Python 3.9+
- ~1,5 Go d’espace disque pour le modèle (téléchargé au premier lancement)

## Installation rapide (Windows)

Si Python n’est pas installé :

```powershell
winget install Python.Python.3.11 --accept-package-agreements --accept-source-agreements
```

Puis dans le projet :

```powershell
cd ai_moderation
py -3 -m venv .venv
.venv\Scripts\pip.exe install -r requirements.txt
```

Dans le fichier **`.env`** à la racine du projet, ajoutez (en adaptant le chemin si besoin) :

```env
PYTHON_PATH=C:/Users/VOTRE_UTILISATEUR/Desktop/final integration/TeamCraft-main/ai_moderation/.venv/Scripts/python.exe
AI_MODERATION_REQUIRED=1
```

La **première** analyse d’image peut prendre 1 à 2 minutes (téléchargement du modèle CLIP). Les suivantes sont rapides.

## Installation (détail)

1. **Create and activate a virtual environment** (recommended):

   ```bash
   cd ai_moderation
   python -m venv .venv
   # Windows:
   .venv\Scripts\activate
   # Linux/Mac:
   source .venv/bin/activate
   ```

2. **Install dependencies**:

   ```bash
   pip install -r requirements.txt
   ```

   First run will download the CLIP model from Hugging Face (no API key required).

3. **Optional: set Python path for Symfony**

   If you use a venv, set the full path so Symfony runs the same interpreter:

   - Windows (PowerShell): `$env:PYTHON_PATH = "C:\path\to\TeamCraft-main\ai_moderation\.venv\Scripts\python.exe"`
   - Or in `.env`: `PYTHON_PATH=C:\path\to\TeamCraft-main\ai_moderation\.venv\Scripts\python.exe`
   - Linux/Mac: `PYTHON_PATH=/path/to/ai_moderation/.venv/bin/python3`

   If unset, Symfony uses `python` from system PATH.

## Test the script

```bash
python ai_detector.py "C:\path\to\an\image.jpg"
```

Output is JSON only (stdout). Example:

```json
{
  "success": true,
  "scores": {
    "nudity": 0.02,
    "naked person": 0.01,
    "blood": 0.03,
    "graphic violence": 0.02,
    "gore scene": 0.01,
    "dead body": 0.02,
    "gun weapon": 0.05,
    "knife attack": 0.03,
    "safe content": 0.81
  },
  "labels": ["nudity", "naked person", "blood", "graphic violence", "gore scene", "dead body", "gun weapon", "knife attack", "safe content"]
}
```

## Moderation rules (Symfony)

- **Reject** (image not saved): nudity, naked person, blood, graphic violence, gore scene, dead body > 0.7
- **Pending review** (saved, hidden until moderator approves): gun weapon or knife attack > 0.6
- **Accept**: otherwise

## Pip dependencies (reference)

| Package       | Purpose                    |
|---------------|----------------------------|
| torch         | PyTorch                    |
| torchvision   | Image handling for CLIP    |
| transformers  | Hugging Face CLIP (ViT-B/32) |
| Pillow        | Load images from disk      |
