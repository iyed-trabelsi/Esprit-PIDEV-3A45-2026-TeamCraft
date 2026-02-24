<?php

namespace App\Controller;

use App\Entity\MediaComment;
use App\Entity\MediaLike;
use App\Entity\MediaSubmission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/media', name: 'api_media_')]
#[IsGranted('ROLE_USER')]
class MediaController extends AbstractController
{
    #[Route('/{id}/like', name: 'like', methods: ['POST'])]
    public function like(MediaSubmission $mediaSubmission, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $likeRepository = $entityManager->getRepository(MediaLike::class);
        $existingLike = $likeRepository->findOneBy(['user' => $user, 'mediaSubmission' => $mediaSubmission]);

        if ($existingLike) {
            $entityManager->remove($existingLike);
            $mediaSubmission->setLikesCount(max(0, $mediaSubmission->getLikesCount() - 1));
            $action = 'unliked';
        } else {
            $like = new MediaLike();
            $like->setUser($user);
            $like->setMediaSubmission($mediaSubmission);
            $entityManager->persist($like);
            $mediaSubmission->setLikesCount($mediaSubmission->getLikesCount() + 1);
            $action = 'liked';
        }

        $entityManager->flush();

        return new JsonResponse([
            'action' => $action,
            'likes' => $mediaSubmission->getLikesCount()
        ]);
    }

    #[Route('/{id}/comment', name: 'comment', methods: ['POST'])]
    public function comment(Request $request, MediaSubmission $mediaSubmission, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (!$content) {
            return new JsonResponse(['error' => 'Content is required'], 400);
        }

        $comment = new MediaComment();
        $comment->setUser($user);
        $comment->setMediaSubmission($mediaSubmission);
        $comment->setContent($content);
        $entityManager->persist($comment);
        $entityManager->flush();

        return new JsonResponse([
            'id' => $comment->getId(),
            'user' => $user->getPseudo() ?? $user->getUsername(),
            'userAvatar' => $user->getProfilePicture(),
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format('c')
        ], 201);
    }

    #[Route('/{id}/comments', name: 'get_comments', methods: ['GET'])]
    public function getComments(MediaSubmission $mediaSubmission): JsonResponse
    {
        $comments = [];
        foreach ($mediaSubmission->getComments() as $comment) {
            /** @var \App\Entity\User $commentUser */
            $commentUser = $comment->getUser();
            $comments[] = [
                'id' => $comment->getId(),
                'user' => $commentUser->getPseudo() ?? $commentUser->getUsername(),
                'userAvatar' => $commentUser->getProfilePicture(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }

        return new JsonResponse(['comments' => $comments]);
    }
}
