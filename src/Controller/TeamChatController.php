<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamMessage;
use App\Repository\TeamMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/team-chat')]
class TeamChatController extends AbstractController
{
    #[Route('/{id}/messages', name: 'team_chat_list', methods: ['GET'])]
    public function list(Team $team, TeamMessageRepository $messageRepository): JsonResponse
    {
        // Check access
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$team->getMembers()->contains($user) && $team->getOwner() !== $user && !$team->isCoOwner($user)) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Fetch last 50 messages
        $messages = $messageRepository->findBy(
            ['team' => $team],
            ['createdAt' => 'ASC'],
            50
        );

        $data = [];
        foreach ($messages as $message) {
            $sender = $message->getSender();

            // Determine role for styling
            $role = 'member';
            if ($team->getOwner() === $sender) {
                $role = 'owner';
            } elseif ($team->isCoOwner($sender)) {
                $role = 'co_owner';
            }

            $data[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format('H:i'),
                'sender' => $sender->getPseudo(),
                'senderId' => $sender->getId(),
                'role' => $role,
                'isMe' => $sender === $user,
                'image' => $message->getImage() ? '/uploads/chat/' . $message->getImage() : null
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}/send', name: 'team_chat_send', methods: ['POST'])]
    public function send(Team $team, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$team->getMembers()->contains($user) && $team->getOwner() !== $user && !$team->isCoOwner($user)) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $content = $request->request->get('content');
        $imageFile = $request->files->get('image');

        if ((!$content || trim($content) === '') && !$imageFile) {
            return new JsonResponse(['error' => 'Empty message'], Response::HTTP_BAD_REQUEST);
        }

        $message = new TeamMessage();
        $message->setContent($content ? trim($content) : '');
        $message->setCreatedAt(new \DateTimeImmutable());
        $message->setSender($user);
        $message->setTeam($team);

        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/chat',
                    $newFilename
                );
                $message->setImage($newFilename);
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'Error uploading file'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $em->persist($message);
        $em->flush();

        return new JsonResponse(['status' => 'success']);
    }
}
