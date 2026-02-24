<?php

namespace App\Controller;

use App\Service\GeminiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GeminiChatController extends AbstractController
{
    public function __construct(
        private GeminiService $geminiService
    ) {
    }

    #[Route('/chat', name: 'app_gemini_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        if (empty(trim($message))) {
            return new JsonResponse(['error' => 'Message is required'], 400);
        }

        try {
            $aiResponse = $this->geminiService->generateResponse($message);

            return new JsonResponse([
                'response' => $aiResponse
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }
}
