<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Local AI image moderation using a Python CLIP script.
 * No external APIs. Runs ai_detector.py and applies moderation rules.
 */
class ImageModerationService
{
    private const CATEGORY_EXPLICIT = [
        "FEMALE_BREAST_EXPOSED",
        "FEMALE_GENITALIA_EXPOSED",
        "BUTTOCKS_EXPOSED",
        "ANUS_EXPOSED",
        "MALE_GENITALIA_EXPOSED",
    ];

    private const CATEGORY_SUGGESTIVE = [
        "FEMALE_BREAST_COVERED",
        "BUTTOCKS_COVERED",
        "FEMALE_GENITALIA_COVERED",
        "BELLY_EXPOSED",
    ];




    /** Labels for warning messages */
    private const SENSITIVE_LABELS = [
        "FEMALE_BREAST_EXPOSED" => "corps exposé",
        "FEMALE_GENITALIA_EXPOSED" => "contenu explicite",
        "BUTTOCKS_EXPOSED" => "nudité partielle",
        "ANUS_EXPOSED" => "contenu sexuel",
        "MALE_GENITALIA_EXPOSED" => "contenu explicite",
    ];

    /** Score above this triggers a warning */
    private const WARNING_LABEL_THRESHOLD = 0.50;

    /** Timeout for the Python script (first run can download the CLIP model). */
    private const PROCESS_TIMEOUT = 300;

    public function __construct(
        private string $projectDir,
        private string $pythonPath,
        private string $scriptPath,
        private bool $moderationRequired = true,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Analyze an image file and return moderation result.
     *
     * @param string $absoluteImagePath Absolute path to the image file (must be inside project uploads/temp)
     * @return array{status: 'accept'|'pending_review'|'reject', message: string, scores?: array<string, float>, error?: string}
     */
    public function analyzeImage(string $absoluteImagePath): array
    {
        set_time_limit(self::PROCESS_TIMEOUT + 30);

        $normalized = $this->validateAndResolvePath($absoluteImagePath);
        if ($normalized === null) {
            $this->logger?->warning('ImageModeration: invalid path', ['path' => $absoluteImagePath]);
            return $this->failResult('invalid_path', 'Impossible de vérifier cette image.');
        }

        $process = new Process([
            $this->pythonPath,
            $this->scriptPath,
            $normalized,
        ]);
        $process->setTimeout(self::PROCESS_TIMEOUT);

        // LOG COMMAND
        $commandStr = $this->pythonPath . ' ' . $this->scriptPath . ' ' . $normalized;
        @file_put_contents($this->projectDir . '/var/log/ai_moderation_debug.log', date('[Y-m-d H:i:s] ') . "Running: " . $commandStr . "\n", FILE_APPEND);

        $process->run();

        $output = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $exitCode = $process->getExitCode();

        // LOG RAW OUTPUT
        $debugLog = sprintf(
            "[%s] Exit: %d | Output: %s | Stderr: %s\n",
            date('Y-m-d H:i:s'),
            $exitCode,
            mb_substr($output, 0, 500),
            mb_substr($stderr, 0, 500)
        );
        @file_put_contents($this->projectDir . '/var/log/ai_moderation_debug.log', $debugLog, FILE_APPEND);

        if (!$process->isSuccessful()) {
            $this->logger?->error('ImageModeration: script failed', [
                'exit_code' => $exitCode,
                'stderr' => $stderr,
                'path' => $normalized,
            ]);

            // EMERGENCY LOGGING
            @file_put_contents($this->projectDir . '/var/log/ai_moderation_error.log', date('[Y-m-d H:i:s] ') . "Script Failed (Exit $exitCode): $stderr\n", FILE_APPEND);

            if (!$this->moderationRequired) {
                return [
                    'status' => 'accept',
                    'message' => 'Modération indisponible; image enregistrée.',
                    'error' => 'script_failed',
                    'debug_stderr' => $stderr,
                ];
            }
            return [
                'status' => 'reject',
                'message' => 'La modération rencontre un problème technique.',
                'error' => 'script_failed',
                'debug_stderr' => $stderr,
                'debug_stdout' => $output,
                'debug_exit_code' => $process->getExitCode(),
            ];
        }

        // Locate the start of JSON in case of extra noise (like "Loading weights")
        $start = strpos($output, '{');
        if ($start !== false) {
            $output = substr($output, $start);
        }

        $data = json_decode($output, true);
        if (!\is_array($data)) {
            $this->logger?->error('ImageModeration: invalid JSON', ['output_preview' => mb_substr($output, 0, 200)]);
            // EMERGENCY LOGGING
            @file_put_contents($this->projectDir . '/var/log/ai_moderation_error.log', date('[Y-m-d H:i:s] ') . "Invalid JSON: $output\n", FILE_APPEND);

            return $this->failResult('invalid_json', 'La modération n\'a pas pu analyser cette image.');
        }

        if (!empty($data['success']) && isset($data['scores']) && \is_array($data['scores'])) {
            return $this->applyModerationRules($data['scores']); // Pass filename for logging if needed, or handle logging inside
        }

        $errorMsg = $data['error']['message'] ?? $data['error']['code'] ?? 'Unknown error';
        $this->logger?->warning('ImageModeration: script returned error', ['error' => $errorMsg]);
        return $this->failResult((string) ($data['error']['code'] ?? 'unknown'), 'La modération n\'a pas pu analyser cette image.');
    }

    /**
     * When moderation fails: reject (and don't save image) or accept (save image) according to moderationRequired.
     */
    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function failResult(string $errorCode, string $rejectMessage): array
    {
        if (!$this->moderationRequired) {
            return [
                'status' => 'accept',
                'message' => 'Modération indisponible; image enregistrée.',
                'error' => $errorCode,
                'scores' => [],
            ];
        }
        return [
            'status' => 'reject',
            'message' => $rejectMessage . ' Elle n\'a pas été enregistrée.',
            'error' => $errorCode,
            'scores' => [],
        ];
    }

    /**
     * Apply business rules based on Sensitivity Levels.
     * Uses Cumulative Scoring to prevent probability dilution (smearing).
     */
    /**
     * Apply business rules based on Sensitivity Levels.
     * Uses Cumulative Scoring with Noise Filtering to prevent false positives.
     */
    /**
     * @param array<string, float> $scores
     * @return array<string, mixed>
     */
    /**
     * @param array<string, float> $scores
     * @return array<string, mixed>
     */
    /**
     * @param array<string, float> $scores
     * @return array<string, mixed>
     */
    private function applyModerationRules(array $scores): array
    {
        $explicitMax = 0.0;
        $suggestiveMax = 0.0;

        foreach (self::CATEGORY_EXPLICIT as $label) {
            $val = $scores[$label] ?? 0.0;
            if ($val > $explicitMax)
                $explicitMax = $val;
        }

        foreach (self::CATEGORY_SUGGESTIVE as $label) {
            $val = $scores[$label] ?? 0.0;
            if ($val > $suggestiveMax)
                $suggestiveMax = $val;
        }

        // Individual peak check (winner)
        $maxLabel = '';
        $maxScore = 0.0;
        foreach ($scores as $label => $score) {
            if ($score > $maxScore) {
                $maxScore = $score;
                $maxLabel = $label;
            }
        }

        // DEBUG LOGGING
        $logEntry = sprintf(
            "[%s] NudeNet - ExplicitMax: %.2f | SuggestMax: %.2f | Winner: %s (%.2f)\n",
            date('Y-m-d H:i:s'),
            $explicitMax,
            $suggestiveMax,
            $maxLabel,
            $maxScore
        );
        @file_put_contents($this->projectDir . '/var/log/ai_moderation.log', $logEntry, FILE_APPEND);

        /** 
         * DECISION LOGIC (NudeNet Specialized)
         * NudeNet is very specific. If it sees a breast or genitalia with > 60% confidence, it's almost certainly nudity.
         */

        // 1. REJECT if Explicit is high
        if ($explicitMax > 0.60) {
            $flagged = [];
            foreach (self::SENSITIVE_LABELS as $label => $labelFr) {
                if (($scores[$label] ?? 0) > 0.60) {
                    $flagged[] = $labelFr;
                }
            }
            $details = $flagged !== [] ? ' (' . implode(', ', $flagged) . ')' : '';

            return [
                'status' => 'reject',
                'sensitivity' => 'high',
                'message' => 'IMAGE BLOQUÉE : Nudité détectée' . $details . '. Veuillez respecter les règles de la communauté.',
                'scores' => $scores,
            ];
        }

        // 2. PENDING (Blur) if suggestive is notable or explicit is present but low
        if ($suggestiveMax > 0.50 || $explicitMax > 0.40) {
            return $this->withWarning([
                'status' => 'pending_review',
                'sensitivity' => 'medium',
                'message' => 'L\'image a été marquée comme sensible.',
                'scores' => $scores,
            ], $scores);
        }

        /**
         * 3. ACCEPT
         */
        return $this->withWarning([
            'status' => 'accept',
            'sensitivity' => 'low',
            'message' => '',
            'scores' => $scores,
        ], $scores);
    }
    /**
     * Add warning_message and labels_flagged when any sensitive label score is above threshold.
     *
     * @param array<string, mixed> $result
     * @param array<string, float> $scores
     * @return array<string, mixed>
     */
    private function withWarning(array $result, array $scores): array
    {
        $flagged = [];
        foreach (self::SENSITIVE_LABELS as $label => $labelFr) {
            if (($scores[$label] ?? 0) > self::WARNING_LABEL_THRESHOLD) {
                $flagged[] = $labelFr;
            }
        }
        if ($flagged !== []) {
            $result['warning_message'] = 'ATTENTION : Contenu sensible détecté (' . implode(', ', $flagged) . '). Veuillez rester vigilant.';
            $result['labels_flagged'] = $flagged;
        }
        return $result;
    }

    /**
     * Resolve path and ensure it is under project dir (uploads or var).
     * Prevents command injection and path traversal.
     */
    private function validateAndResolvePath(string $path): ?string
    {
        $path = str_replace("\0", '', $path);
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            return null;
        }
        $base = realpath($this->projectDir) ?: $this->projectDir;
        $realNorm = str_replace('\\', '/', $real);
        $baseNorm = rtrim(str_replace('\\', '/', $base), '/') . '/';
        if (strpos($realNorm, $baseNorm) !== 0) {
            return null;
        }
        return $real;
    }
}
