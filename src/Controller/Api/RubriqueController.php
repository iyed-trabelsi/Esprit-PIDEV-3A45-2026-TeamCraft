<?php

namespace App\Controller\Api;

use App\Entity\Rubrique;
use App\Service\SmartWritingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/rubrique')]
class RubriqueController extends AbstractController
{
    #[Route('/{id}/summary', name: 'api_rubrique_summary', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSummary(
        int $id,
        \Symfony\Component\HttpFoundation\Request $request,
        EntityManagerInterface $em,
        SmartWritingService $aiService
    ): JsonResponse {
        $force = $request->query->getBoolean('force', false);
        $rubrique = $em->getRepository(Rubrique::class)->find($id);

        if (!$rubrique) {
            return new JsonResponse(['error' => 'Rubrique not found'], Response::HTTP_NOT_FOUND);
        }

        // Return cached summary if available and not forced
        if ($rubrique->getAiSummary() && !$force) {
            return new JsonResponse([
                'summary' => $rubrique->getAiSummary(),
                'cached' => true
            ]);
        }

        try {
            // Generate summary
            $summary = $aiService->summarizeRubriqueDiscussion($rubrique);
            
            // Save to database
            $rubrique->setAiSummary($summary);
            $em->persist($rubrique);
            $em->flush();

            return new JsonResponse([
                'summary' => $summary,
                'cached' => false
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to generate summary: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
