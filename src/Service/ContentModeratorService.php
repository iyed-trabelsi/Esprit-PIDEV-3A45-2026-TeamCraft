<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ContentModeratorService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT = 30;

    private const SYSTEM_PROMPT = <<<PROMPT
You are a content moderator. Your task is to detect if the given text contains:
- profanity, insults, vulgar language
- hate speech, discrimination, threats
- sexually explicit or highly offensive content

Consider ALL languages and dialects, including but not limited to:
- English, French, Arabic (Modern Standard and dialects)
- Tunisian Arabic (Derja / Tounsi) in both Latin and Arabic script
- Spanish, Italian, German, and any other language

If the text contains such content in ANY language or dialect, reply with exactly "true".
If the text is safe (polite, neutral, or constructive), reply with exactly "false".
Do not explain. Reply only with the single word "true" or "false".
PROMPT;

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(param: 'openai_api_key')]
        private string $apiKey,
        private ?LoggerInterface $logger = null
    ) {
        $this->apiKey = trim((string) $this->apiKey);
    }

    /**
     * Détection de contenu toxique via l’API IA uniquement (toutes langues, dont tunisien/derja).
     */
    public function isToxic(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        if ($this->apiKey === '' || $this->apiKey === '0') {
            $this->logger?->warning('ContentModerator: OPENAI_API_KEY is not set, skipping moderation.');
            return false;
        }

        try {
            $response = $this->client->request('POST', self::API_URL, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $text],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 10,
                ],
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $content = trim(preg_replace('/\s+/', ' ', $content));
            $contentLower = mb_strtolower($content);

            if ($contentLower === 'true' || $contentLower === 'true.' || $contentLower === 'yes') {
                return true;
            }
            if (str_starts_with($contentLower, 'true')) {
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            $this->logger?->error('ContentModerator: API error - ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Pour debug : appelle l'API et retourne la réponse brute et le résultat.
     */
    public function debugModeration(string $text): array
    {
        $result = ['toxic' => false, 'api_content' => null, 'error' => null, 'key_set' => $this->apiKey !== '' && $this->apiKey !== '0'];
        $text = trim($text);
        if ($text === '') {
            return $result;
        }
        if (!$result['key_set']) {
            $result['error'] = 'OPENAI_API_KEY non configurée (.env)';
            return $result;
        }
        try {
            $response = $this->client->request('POST', self::API_URL, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $text],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 10,
                ],
            ]);
            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $result['api_content'] = $content;
            $contentLower = mb_strtolower(trim(preg_replace('/\s+/', ' ', $content)));
            $result['toxic'] = $contentLower === 'true' || $contentLower === 'true.' || $contentLower === 'yes' || str_starts_with($contentLower, 'true');
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $result['http_status'] = $e->getResponse()->getStatusCode();
                try {
                    $result['api_error_body'] = $e->getResponse()->getContent(false);
                } catch (\Throwable) {
                }
            }
        }
        return $result;
    }
}
