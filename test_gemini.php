<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$apiKey = $_ENV['GEMINI_API_KEY'];
$client = HttpClient::create();

$prompt = "You are a data-driven scheduling AI. Generate the absolute most optimal upcoming event. CRITICAL INSTRUCTION: You must reply ONLY with a raw JSON object. Do not include markdown formatting like ```json or ```. Do not include any introductory or concluding text. Your entire response MUST start exactly with '{' and end exactly with '}'. Use this exact schema: {\"nomEvenement\":\"Catchy Event Name\",\"typeEvenement\":\"tournament or scrim or practice\",\"dateDebut\":\"YYYY-MM-DDTHH:MM\",\"dateFin\":\"YYYY-MM-DDTHH:MM\",\"reasoning\":\"A clear, detailed explanation.\"}";

echo "Calling Gemini...\n";

try {
    $response = $client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json'
            ]
        ],
    ]);

    $content = $response->toArray(false);
    file_put_contents('debug_output.json', json_encode($content, JSON_PRETTY_PRINT));
    echo "Dumped to debug_output.json\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
