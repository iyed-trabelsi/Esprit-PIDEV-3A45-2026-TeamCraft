<?php

namespace App\Controller;

use App\Service\SmartWritingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ai')]
class AiController extends AbstractController
{
    public function __construct(
        private SmartWritingService $smartWritingService
    ) {
    }

    #[Route('/improve-text', name: 'app_ai_improve_text', methods: ['POST'])]
    public function improveText(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? '';
        $tone = $data['tone'] ?? 'professional';

        if (empty(trim($text))) {
            return new JsonResponse(['success' => false, 'message' => 'Text cannot be empty'], 400);
        }

        $improvedText = $this->smartWritingService->improveText($text, $tone);

        return new JsonResponse([
            'success' => true,
            'improvedText' => $improvedText,
        ]);
    }
}
