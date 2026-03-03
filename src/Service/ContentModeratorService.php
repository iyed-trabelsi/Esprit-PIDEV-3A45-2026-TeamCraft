<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ContentModeratorService
{
    private const API_URL = 'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze';
    private const TIMEOUT = 30;
    private const TOXICITY_THRESHOLD = 0.7; // Score above 0.7 is considered toxic

    /**
     * Liste de mots toxiques tunisiens/arabes (en arabe et en latin/arabizi)
     * Ces mots complètent la détection de l'API Perspective
     */
    private const TUNISIAN_BAD_WORDS = [
        // Tunisian/Arabic in Arabic script
        'كلب', 'حمار', 'غبي', 'زبي', 'كس', 'عاهرة', 'قحبة', 'خنزير',
        'شرموط', 'نيك', 'عرص', 'ابن كلب', 'ابن قحبة', 'منيوك',
        'يا حيوان', 'يا كلب', 'يا حمار', 'زك', 'فشخ',
        
        // Tunisian Derja in Latin script (Arabizi/Tounsi)
        'kalb', 'hmar', '7mar', 'ghabi', 'zebi', '9a7ba', 'ka7ba', 
        'charmouta', 'chormouta', 'chihar', 'niik', 'nayek', 'manyouk',
        'ya 7ayawan', 'ya kalb', 'behi', 'nayek waldik', 'omk',
        'khenzir', '5anzir', 'khasra', '5asra', 'nik omk', 'nik waldik',
        'yaatik', 'ya3tik', 'balek', 'ahbal', 'a7bal', 'maskhout',
        
        // Common Arabic profanity
        'kos omk', 'kos emek', '3ars', 'sharmouta', 'merde',
        'ibn kalb', 'ibn 9a7ba', 'ya ibn', 'rouh', 'rou7',
        
        // Variations and mixed
        'klb', 'k9ba', '97ba', 'zeb', 'zby', 'nigga' ,'miboun',
        '3asba', 'asba', 'nik', 'zob', 'zabb'
    ];

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(param: 'perspective_api_key')]
        private string $apiKey,
        private ?LoggerInterface $logger = null
    ) {
        $this->apiKey = trim((string) $this->apiKey);
    }

    /**
     * Détection de contenu toxique via l'API Perspective (Google) + mots-clés tunisiens.
     * Combine deux approches:
     * 1. Détection par mots-clés (rapide, spécifique aux mots tunisiens)
     * 2. Détection par IA (Perspective API - contextuelle, multi-langues)
     * 
     * @param string $text Le texte à analyser
     * @return bool true si le contenu est toxique, false sinon
     */
    public function isToxic(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        // 1. Vérification rapide par mots-clés tunisiens
        if ($this->containsTunisianBadWords($text)) {
            $this->logger?->info('ContentModerator: Tunisian bad word detected in text', [
                'text_preview' => mb_substr($text, 0, 50),
            ]);
            return true;
        }

        // 2. Vérification par API Perspective (si la clé est configurée)
        if ($this->apiKey === '' || $this->apiKey === '0') {
            $this->logger?->warning('ContentModerator: PERSPECTIVE_API_KEY is not set, relying on keyword filter only.');
            return false;
        }

        try {
            $url = self::API_URL . '?key=' . urlencode($this->apiKey);
            
            $response = $this->client->request('POST', $url, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'comment' => [
                        'text' => $text,
                    ],
                    'languages' => ['en', 'fr', 'ar', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ja'], // Multi-language support
                    'requestedAttributes' => [
                        'TOXICITY' => (object)[],
                        'SEVERE_TOXICITY' => (object)[],
                        'INSULT' => (object)[],
                        'PROFANITY' => (object)[],
                        'THREAT' => (object)[],
                    ],
                    'doNotStore' => true, // Don't store comments for privacy
                ],
            ]);

            $data = $response->toArray();
            
            // Check multiple toxicity attributes
            $toxicityScore = $data['attributeScores']['TOXICITY']['summaryScore']['value'] ?? 0;
            $severeToxicityScore = $data['attributeScores']['SEVERE_TOXICITY']['summaryScore']['value'] ?? 0;
            $insultScore = $data['attributeScores']['INSULT']['summaryScore']['value'] ?? 0;
            $profanityScore = $data['attributeScores']['PROFANITY']['summaryScore']['value'] ?? 0;
            $threatScore = $data['attributeScores']['THREAT']['summaryScore']['value'] ?? 0;

            // If any score exceeds threshold, consider it toxic
            if ($toxicityScore >= self::TOXICITY_THRESHOLD ||
                $severeToxicityScore >= self::TOXICITY_THRESHOLD ||
                $insultScore >= self::TOXICITY_THRESHOLD ||
                $profanityScore >= self::TOXICITY_THRESHOLD ||
                $threatScore >= self::TOXICITY_THRESHOLD) {
                return true;
            }

            return false;

        } catch (\Throwable $e) {
            $this->logger?->error('ContentModerator: Perspective API error - ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            // Fail open: if API fails, allow the content to avoid blocking legitimate posts
            return false;
        }
    }

    /**
     * Pour debug : appelle l'API Perspective et retourne la réponse brute et le résultat.
     * 
     * @param string $text Le texte à analyser
     * @return array Informations de debug incluant les scores et erreurs éventuelles
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
    public function debugModeration(string $text): array
    {
        $result = [
            'toxic' => false,
            'api_content' => null,
            'error' => null,
            'key_set' => $this->apiKey !== '' && $this->apiKey !== '0',
            'scores' => [],
        ];

        $text = trim($text);
        if ($text === '') {
            return $result;
        }

        if (!$result['key_set']) {
            $result['error'] = 'PERSPECTIVE_API_KEY non configurée (.env)';
            return $result;
        }

        try {
            $url = self::API_URL . '?key=' . urlencode($this->apiKey);
            
            $response = $this->client->request('POST', $url, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'comment' => [
                        'text' => $text,
                    ],
                    'languages' => ['en', 'fr', 'ar', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ja'],
                    'requestedAttributes' => [
                        'TOXICITY' => (object)[],
                        'SEVERE_TOXICITY' => (object)[],
                        'INSULT' => (object)[],
                        'PROFANITY' => (object)[],
                        'THREAT' => (object)[],
                    ],
                    'doNotStore' => true,
                ],
            ]);

            $data = $response->toArray();
            
            // Extract all scores
            $result['scores'] = [
                'TOXICITY' => $data['attributeScores']['TOXICITY']['summaryScore']['value'] ?? 0,
                'SEVERE_TOXICITY' => $data['attributeScores']['SEVERE_TOXICITY']['summaryScore']['value'] ?? 0,
                'INSULT' => $data['attributeScores']['INSULT']['summaryScore']['value'] ?? 0,
                'PROFANITY' => $data['attributeScores']['PROFANITY']['summaryScore']['value'] ?? 0,
                'THREAT' => $data['attributeScores']['THREAT']['summaryScore']['value'] ?? 0,
            ];
            
            $result['api_content'] = json_encode($result['scores'], JSON_PRETTY_PRINT);
            
            // Check if any score exceeds threshold
            foreach ($result['scores'] as $scoreValue) {
                if ($scoreValue >= self::TOXICITY_THRESHOLD) {
                    $result['toxic'] = true;
                    break;
                }
            }

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

    /**
     * Vérifie si le texte contient des mots tunisiens/arabes toxiques.
     * Recherche insensible à la casse pour les mots en caractères latins.
     * 
     * @param string $text Le texte à vérifier
     * @return bool true si un mot toxique est trouvé
     */
    private function containsTunisianBadWords(string $text): bool
    {
        // Normaliser le texte pour la comparaison
        $textLower = mb_strtolower($text, 'UTF-8');
        
        foreach (self::TUNISIAN_BAD_WORDS as $badWord) {
            $badWordLower = mb_strtolower($badWord, 'UTF-8');
            
            // Vérifier si le mot est présent (en tant que mot complet ou partie de mot)
            // Utilise mb_strpos pour supporter les caractères Unicode (arabe)
            if (mb_strpos($textLower, $badWordLower, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        
        return false;
    }
}
