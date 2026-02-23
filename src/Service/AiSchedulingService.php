<?php

namespace App\Service;

use App\Repository\EvenementRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AiSchedulingService
{
    private $evenementRepo;
    private $httpClient;
    private $logger;
    private $apiKey;

    public function __construct(
        EvenementRepository $evenementRepo,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $apiKey = 'AIzaSyCW8BJaGZQMvtSYh0n56VIJuRTXLpjn_QY'
    ) {
        $this->evenementRepo = $evenementRepo;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        // Use injected key; if DI fails the hardcoded fallback ensures we never hit the API with an empty key
        $this->apiKey = !empty($apiKey) ? $apiKey : 'AIzaSyCW8BJaGZQMvtSYh0n56VIJuRTXLpjn_QY';
    }

    public function generateOptimalEvent(): array
    {
        $events = $this->evenementRepo->findAll();
        $stats = $this->analyzeHistory($events);

        $currentDateTimeStr = (new \DateTime())->format('Y-m-d H:i');

        $prompt = "You are a senior event analytics consultant advising a competitive gaming community. "
                . "IMPORTANT CONTEXT: Today's exact current date and time is: $currentDateTimeStr\n\n"
                . "Below is detailed performance data from past events. Each entry includes the event name, type, day of week, start time, duration, venue capacity, participants (fill rate %), and average community rating out of 5.\n\n"
                . $stats . "\n\n"
                . "Your task: Analyse this data and recommend the SINGLE most optimal next event. Your tone should be professional, confident, and persuasive — as if presenting a strategic report to stakeholders.\n\n"
                . "STRICT CONSTRAINTS (MANDATORY):\n"
                . "1. nomEvenement MUST be AT LEAST 8 characters long.\n"
                . "2. typeEvenement MUST be AT LEAST 8 characters long.\n"
                . "3. dateDebut MUST be EQUAL TO OR STRICTLY LATER THAN $currentDateTimeStr.\n"
                . "4. dateFin MUST be AFTER dateDebut.\n\n"
                . "Return ONLY a JSON object (no markdown, no extra text). Use this EXACT schema:\n"
                . '{"nomEvenement":"A compelling event name (min 8 chars)","typeEvenement":"The event type (min 8 chars)","dateDebut":"YYYY-MM-DDTHH:MM","dateFin":"YYYY-MM-DDTHH:MM","reasoning":"ONE sentence executive summary of the recommendation.","data_insights":"2-3 sentences citing specific numbers from the data: which time slots had the highest fill rates, which event types scored the best ratings, notable patterns observed.","time_rationale":"2-3 sentences explaining WHY this specific day, time, and duration were chosen based on the data patterns. Be specific with percentages and ratings.","type_rationale":"2-3 sentences explaining why this event type is recommended over alternatives, referencing its historical performance metrics.","confidence_score":85}';

        
        try {
            $this->logger->info('AI Scheduling: Calling Gemini API. Key starts with: ' . substr($this->apiKey, 0, 8));
            $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
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
            if (isset($content['candidates'][0]['content']['parts'][0]['text'])) {
                $generated = trim($content['candidates'][0]['content']['parts'][0]['text']);
                
                // Advanced heuristic JSON cleanup
                $generated = preg_replace('/```json/i', '', $generated);
                $generated = preg_replace('/```/i', '', $generated);
                
                // Try to find the first { and last }
                $start = strpos($generated, '{');
                $end = strrpos($generated, '}');
                
                if ($start !== false && $end !== false) {
                    $jsonStr = substr($generated, $start, $end - $start + 1);
                    $data = json_decode($jsonStr, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['nomEvenement'])) {
                        return $data;
                    } else {
                        $this->logger->error("AI Scheduling JSON Decode Failed. Raw output: " . $generated . " | Error: " . json_last_error_msg());
                    }
                } else {
                    $this->logger->error("AI Scheduling: No JSON structure found. Raw output: " . $generated);
                }
            } else {
                // Log what the API actually returned so we can diagnose auth errors, quota errors, etc.
                $this->logger->error('AI Scheduling: Unexpected API response structure. Raw: ' . json_encode($content));
            }
        } catch (\Exception $e) {
            $this->logger->error('AI Scheduling Exception: ' . $e->getMessage());
        }

        // Return fallback if all fails
        $this->logger->warning("AI Scheduling: Falling back to default event.");
        return $this->getFallbackEvent();
    }

    private function analyzeHistory(array $events): string
    {
        if (count($events) === 0) {
            return "NO HISTORICAL DATA: No past events found. Suggest a generally optimal time slot for a gaming community (e.g., Friday or Saturday evenings around 20:00).";
        }

        $lines = [];
        $hasData = false;

        foreach ($events as $e) {
            if (!in_array($e->getStatus(), ['over', 'closed'])) {
                continue;
            }
            if ($e->getDateDebut() === null || $e->getDateFin() === null) {
                continue;
            }

            $hasData = true;

            $cap  = $e->getPlace() ? $e->getPlace()->getCapaciteMax() : 0;
            $cap  = max(1, (int)$cap);
            $part = count($e->getParticipations());
            $fill = round(($part / $cap) * 100);

            $durationHours = round(($e->getDateFin()->getTimestamp() - $e->getDateDebut()->getTimestamp()) / 3600, 1);

            $day   = $e->getDateDebut()->format('l');    // e.g. Friday
            $time  = $e->getDateDebut()->format('H:i');  // e.g. 20:00

            $rating      = $e->getAverageRating() ?? 0;
            $reviewCount = $e->getReviewCount() ?? 0;
            $ratingStr   = $reviewCount > 0
                ? round($rating, 1) . '/5 (' . $reviewCount . ' review' . ($reviewCount > 1 ? 's' : '') . ')'
                : 'no reviews yet';

            $lines[] = sprintf(
                '- "%s" [%s] on %s at %s | duration: %sh | venue capacity: %d | participants: %d (%d%% full) | avg rating: %s',
                $e->getNomEvenement(),
                $e->getTypeEvenement(),
                $day,
                $time,
                $durationHours,
                $cap,
                $part,
                $fill,
                $ratingStr
            );
        }

        if (!$hasData) {
            return "EVENTS EXIST BUT NO COMPLETED ONES YET: All events are still open. Suggest a generally optimal time slot for a gaming community.";
        }

        return "HISTORICAL EVENT DATA (" . count($lines) . " completed events):\n" . implode("\n", $lines);
    }

    private function getFallbackEvent(): array
    {
        $nextFriday = new \DateTime('next friday 20:00');
        $end = clone $nextFriday;
        $end->modify('+3 hours');

        return [
            'nomEvenement' => 'Friday Night Showdown',
            'typeEvenement' => 'tournament',
            'dateDebut' => $nextFriday->format('Y-m-d\TH:i'),
            'dateFin' => $end->format('Y-m-d\TH:i'),
            'reasoning' => 'L\'IA n\'a pas pu générer une réponse détaillée (pas assez de données historiques ou service en cours de démarrage). L\'horaire proposé (vendredi 20:00) correspond au pic d\'activité général des joueurs, idéal pour maximiser la participation à un tournoi de fin de semaine.'
        ];
    }
}
