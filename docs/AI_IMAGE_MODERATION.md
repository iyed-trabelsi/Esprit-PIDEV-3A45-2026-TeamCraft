# AI Image Moderation – Symfony integration

Local image moderation for **forum posts** and **forum comments** using a Python CLIP script. No cloud APIs.

## Quick setup

1. **Python environment**  
   See [../ai_moderation/README.md](../ai_moderation/README.md): create a venv, install `ai_moderation/requirements.txt`.

2. **Database**  
   New field: `Comment.moderationStatus` (nullable string). Run:
   ```bash
   php bin/console doctrine:schema:update --force
   ```
   Or generate a migration and run it.

3. **Optional: Python path**  
   If the `python` command is not in the server PATH, or you use a venv, set in `.env`:
   ```env
   PYTHON_PATH=C:\path\to\ai_moderation\.venv\Scripts\python.exe
   ```
   (Use the path to your venv’s `python` or `python3`.)

## Where it runs

- **New/Edit post** with an uploaded image → script runs; reject / pending_review / accept.
- **New/Edit comment** with an uploaded image → same.

No image → no call to the script.

## Moderation logic

| Result          | Condition (score) | Effect |
|-----------------|-------------------|--------|
| **Reject**      | nudity, naked person, blood, graphic violence, gore scene, dead body > 0.7 | Image deleted, error shown, post/comment not saved with that image. |
| **Pending**     | gun weapon or knife attack > 0.6 | Image saved; post `statut = pending_review` or comment `moderationStatus = pending_review` (visible only to author until approved). |
| **Accept**      | Otherwise         | Image saved, content published as usual. |

## Security

- **No shell** – Symfony runs the script via `Symfony\Component\Process\Process` with a list of arguments (Python path, script path, image path). No `shell_exec` or string-based shell command.
- **Path validation** – Only paths under the project directory are accepted; `realpath` and prefix check prevent traversal and injection.
- **Input** – The only argument passed to the script is the resolved image path. No user-controlled string is concatenated into a command.
- **Fail-open** – If the script fails (timeout, missing Python, JSON error), the service returns `accept` so the site does not block uploads; you can log and monitor failures.

## Approving pending content

- **Posts**: In admin (or post edit), change post `statut` from “En attente de modération” to “Publié”.
- **Comments**: Set `moderationStatus` to `null` (approved) in admin or via a small admin action you add.

## Files

| File | Role |
|------|------|
| `ai_moderation/ai_detector.py` | CLIP script: reads image path, outputs JSON scores. |
| `ai_moderation/requirements.txt` | Pip dependencies (torch, transformers, Pillow). |
| `src/Service/ImageModerationService.php` | Runs script, validates path, applies rules, returns status. |
| `config/services.yaml` | Parameters `python_path`, `ai_moderation_script_path`; `ImageModerationService` args. |
| `ForumController` | Calls `ImageModerationService` after uploading post/comment images. |
