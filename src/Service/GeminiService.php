<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiService
{
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';
    private const SYSTEM_INSTRUCTION = "Tu es l'assistant IA officiel de TeamCraft, une plateforme de gaming compétitif (LoL, Valorant, CS2).

Consignes strictes :
1. Réponds à toutes les questions de manière **la plus concise possible**, en **moins de 100 mots**.
2. Garde des réponses claires, directes et professionnelles, tout en restant amical.
3. Si l'utilisateur demande explicitement une explication détaillée, ne fournis du détail qu'à ce moment-là.
4. Donne toujours des informations pertinentes, ne répète pas de détails inutiles.
5. Maintiens le contexte de la conversation (5 derniers messages).
6. Si tu ne peux pas répondre ou si une erreur survient, réponds :
   \"Désolé, TeamCraft AI est temporairement indisponible. Réessaie dans quelques instants !\"
7. Réponds en français par défaut.

Exemples :
- Question : \"Que fait TeamCraft ?\" → Réponse : \"TeamCraft connecte les joueurs et les équipes pour LoL, Valorant et CS2, facilite le recrutement et affiche les stats en temps réel.\"
- Question : \"Comment lier mon compte ?\" → Réponse : \"Va dans Paramètres > Lier un compte > Choisis le jeu > Suis les instructions.\"";

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int, array<string, string>> $history Optional conversation history for context maintenance
     */
    public function generateResponse(string $userMessage, array $history = []): string
    {
        try {
            $contents = [];

            // Add system instruction first
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => "INSTRUCTIONS SYSTEME :\n" . self::SYSTEM_INSTRUCTION]]
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => "Compris. Je suis l'assistant officiel de TeamCraft. Je suivrai ces instructions strictement."]]
            ];

            // Add history if provided
            foreach ($history as $msg) {
                $contents[] = [
                    'role' => $msg['role'] === 'bot' ? 'model' : 'user',
                    'parts' => [['text' => $msg['content']]]
                ];
            }

            // Add current message
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $userMessage]]
            ];

            $response = $this->httpClient->request('POST', self::API_ENDPOINT . '?key=' . $this->apiKey, [
                'json' => [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 1024,
                    ]
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $content = $response->getContent(false);
                $this->logger->error('Gemini API Error Status: ' . $statusCode, ['body' => $content]);
                return "Désolé, TeamCraft AI est temporairement indisponible. Réessaie dans quelques instants !";
            }

            $data = $response->toArray();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? "Désolé, TeamCraft AI est temporairement indisponible. Réessaie dans quelques instants !";

        } catch (\Exception $e) {
            $this->logger->error('Gemini API Exception: ' . $e->getMessage());
            return "Désolé, TeamCraft AI est temporairement indisponible. Réessaie dans quelques instants !";
        }
    }
}
