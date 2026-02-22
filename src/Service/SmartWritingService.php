<?php

namespace App\Service;

use App\Entity\Post;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class SmartWritingService
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'openai/gpt-3.5-turbo'; // Use a cost-effective model or allow config

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(param: 'openrouter_api_key')]
        private string $apiKey,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Improve text based on selected tone.
     */
    public function improveText(string $text, string $tone): string
    {
        if (empty(trim($text))) {
            return '';
        }

        $prompt = $this->createPrompt($text, $tone);

        try {
            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://teamcraft.com', // OpenRouter requirements
                    'X-Title' => 'TeamCraft Forum',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful writing assistant. Rewrite the user\'s text to match the requested tone. IMPORTANT: Always keep the same language the user wrote in (e.g. if they write in French, respond in French; if Arabic, respond in Arabic), UNLESS the goal is specifically to translate. Return ONLY the rewritten text, no explanations.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 500,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? $text;

        } catch (\Throwable $e) {
            $this->logger?->error('SmartWritingService error: ' . $e->getMessage());
            return $text; // Fallback to original
        }
    }

    /**
     * Generate a complete summary of a Rubrique's discussion.
     */
    public function summarizeRubriqueDiscussion(\App\Entity\Rubrique $rubrique): string
    {
        $discussion = "Forum Discussion for Rubrique: " . $rubrique->getNomRubrique() . "\n";
        $discussion .= "Description: " . $rubrique->getDescription() . "\n\n";

        $posts = $rubrique->getPosts();
        $formattedText = "";

        foreach ($posts as $index => $post) {
            $formattedText .= "Post " . ($index + 1) . ": " . $post->getTitre() . "\n";
            $formattedText .= "Content: " . strip_tags((string) $post->getContenu()) . "\n";
            
            $comments = $post->getComments();
            if (!$comments->isEmpty()) {
                $formattedText .= "Comments:\n";
                foreach ($comments as $cIndex => $comment) {
                    $formattedText .= "  Comment " . ($cIndex + 1) . ": " . strip_tags((string) $comment->getContenu()) . "\n";
                }
            }
            $formattedText .= "\n---\n";
        }

        // Limit total text length to avoid token overflow (~15,000 characters for Safety)
        if (strlen($formattedText) > 15000) {
            $formattedText = substr($formattedText, 0, 15000) . "... [Discussion truncated due to length]";
        }

        $prompt = "You are an AI forum analyst.\n\nAnalyze and summarize the following forum discussion using this structure:\n\n" .
                  "Main Topics Discussed\nKey Arguments\nProposed Solutions\nAreas of Disagreement\nOverall Community Sentiment\nFinal Takeaway\n\n" .
                  "Discussion:\n" . $formattedText;

        try {
            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://teamcraft.com',
                    'X-Title' => 'TeamCraft Forum',
                ],
                'json' => [
                    'model' => 'openai/gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an AI forum analyst who provides structured, objective summaries. Use the requested structure and return only the formatted text. IMPORTANT: Write the summary in the SAME LANGUAGE as the majority of the discussion (e.g., if most posts are in French, the summary must be in French).'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 1000,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? 'Summary generation failed (empty response).';

        } catch (\Throwable $e) {
            $this->logger?->error('SmartWritingService summary error: ' . $e->getMessage());
            return 'Summary generation failed due to an error: ' . $e->getMessage();
        }
    }

    /**
     * Translate text to any language.
     */
    public function translateText(string $text, string $targetLanguage): string
    {
        if (empty(trim($text))) {
            return '';
        }

        try {
            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://teamcraft.com',
                    'X-Title' => 'TeamCraft Forum',
                ],
                'json' => [
                    'model' => 'openai/gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a professional translator. Translate the provided text into the requested target language. Maintain the original tone and format. Return ONLY the translated text, no explanations.'],
                        ['role' => 'user', 'content' => "Target Language: $targetLanguage\n\nText to translate:\n$text"],
                    ],
                    'max_tokens' => 2000,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? $text;

        } catch (\Throwable $e) {
            $this->logger?->error('SmartWritingService translation error: ' . $e->getMessage());
            return $text;
        }
    }

    private function createPrompt(string $text, string $tone): string
    {
        $toneInstruction = match ($tone) {
            'professional' => 'Make this text sound professional, clear, and polite.',
            'friendly' => 'Make this text sound friendly, warm, and approachable.',
            'witty' => 'Make this text sound witty, fun, and engaging.',
            'concise' => 'Make this text concise and to the point.',
            'correction' => 'Fix grammar and spelling errors only, keep the tone neutral.',
            'translate_ar' => 'Translate this text to Arabic (Tunisian dialect if informal, MSA if formal).',
            'translate_en' => 'Translate this text to English.',
            'translate_fr' => 'Translate this text to French.',
            default => 'Improve clarity and grammar.',
        };

        if (!str_starts_with($tone, 'translate_')) {
            $toneInstruction .= " IMPORTANT: You MUST keep the same language as the input text. If the user writes in French, stay in French. If the user writes in Arabic, stay in Arabic. DO NOT translate to English.";
        }

        return "Tone/Goal: $toneInstruction\n\nText to rewrite:\n$text";
    }

    /**
     * Ask the AI to identify the single best comment on a Post.
     *
     * Returns an array: ['best_comment_id' => int|null, 'confidence' => float, 'reason' => string]
     */
    public function findBestComment(Post $post): array
    {
        $fallback = ['best_comment_id' => null, 'confidence' => 0.0, 'reason' => 'Analyse impossible.'];

        // Build comment list (top-level only, skip empty)
        $commentLines = [];
        foreach ($post->getComments() as $comment) {
            if ($comment->getParent() !== null) {
                continue; // skip replies
            }
            $text = strip_tags((string) $comment->getContenu());
            if (empty(trim($text))) {
                continue;
            }
            $commentLines[] = sprintf('[ID:%d] %s: %s', $comment->getId(), $comment->getAuteur()?->getPseudo() ?? 'Anonyme', $text);
        }

        if (count($commentLines) < 2) {
            return $fallback;
        }

        $prompt = "You are a competitive gaming forum analyst.\n\n"
            . "Post title: " . $post->getTitre() . "\n"
            . "Post content: " . strip_tags((string) $post->getContenu()) . "\n\n"
            . "Comments:\n" . implode("\n", $commentLines) . "\n\n"
            . "Identify which comment provides the best and most complete, helpful, or insightful response to the post. "
            . "Only choose one. If none provide real value, return null.\n\n"
            . "Return ONLY valid JSON (no markdown, no explanation) in this exact format:\n"
            . '{"best_comment_id": <integer or null>, "confidence": <0.0 to 1.0>, "reason": "<one concise sentence>"}';

        try {
            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => 'https://teamcraft.com',
                    'X-Title'       => 'TeamCraft Forum',
                ],
                'json' => [
                    'model'    => 'openai/gpt-4o-mini',
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a helpful forum analyst. Always respond with only valid JSON, no markdown code blocks.',
                        ],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'      => 200,
                    'response_format' => ['type' => 'json_object'],
                ],
            ]);

            $rawContent = $response->toArray()['choices'][0]['message']['content'] ?? '{}';
            // Strip possible markdown fences if model ignores instructions
            $rawContent = preg_replace('/^```json\s*/i', '', trim($rawContent));
            $rawContent = preg_replace('/```$/', '', $rawContent);
            $decoded = json_decode(trim($rawContent), true);

            if (!is_array($decoded) || !array_key_exists('best_comment_id', $decoded)) {
                return $fallback;
            }

            return [
                'best_comment_id' => $decoded['best_comment_id'],
                'confidence'      => (float) ($decoded['confidence'] ?? 0.0),
                'reason'          => (string) ($decoded['reason'] ?? ''),
            ];

        } catch (\Throwable $e) {
            $this->logger?->error('SmartWritingService::findBestComment error: ' . $e->getMessage());
            return $fallback;
        }
    }
}
