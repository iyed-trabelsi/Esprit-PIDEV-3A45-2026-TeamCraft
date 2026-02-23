<?php

namespace App\Controller;

use App\Service\AiSchedulingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class EventApiController extends AbstractController
{
    #[Route('/api/events/ai-generate', name: 'api_ai_generate_event', methods: ['GET', 'POST'])]
    public function generate(AiSchedulingService $aiService): JsonResponse
    {
        try {
            $data = $aiService->generateOptimalEvent();
            return $this->json([
                'success' => true,
                'data' => $data,
                'debug_reasoning' => $data['reasoning'] ?? 'none'
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
