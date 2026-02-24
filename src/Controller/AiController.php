<?php

namespace App\Controller;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Service\SmartWritingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ai')]
class AiController extends AbstractController
{
    public function __construct(
        private SmartWritingService $smartWritingService,
        private PostRepository $postRepository
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

    #[Route('/best-comment/{postId}', name: 'app_ai_best_comment', methods: ['GET'])]
    public function bestComment(int $postId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $post = $this->postRepository->find($postId);
        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Post not found'], 404);
        }

        // Count top-level comments with content
        $validComments = array_filter(
            $post->getComments()->toArray(),
            fn($c) => $c->getParent() === null && !empty(trim((string) $c->getContenu()))
        );

        if (count($validComments) < 2) {
            return new JsonResponse([
                'success'         => true,
                'best_comment_id' => null,
                'confidence'      => 0.0,
                'reason'          => 'Pas assez de commentaires pour analyser.',
            ]);
        }

        $result = $this->smartWritingService->findBestComment($post);

        return new JsonResponse([
            'success'         => true,
            'best_comment_id' => $result['best_comment_id'],
            'confidence'      => $result['confidence'],
            'reason'          => $result['reason'],
        ]);
    }
}
