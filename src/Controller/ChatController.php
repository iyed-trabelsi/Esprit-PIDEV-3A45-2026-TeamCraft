<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\FriendRequestRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    #[Route('/', name: 'app_chat_index', methods: ['GET'])]
    public function index(MessageRepository $messageRepository, FriendRequestRepository $friendRequestRepository): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $friends = $friendRequestRepository->findFriends($currentUser);

        // For simplicity, we can redirect to the most recent conversation or show an empty state
        // In a real app, we'd list conversations.
        return $this->render('frontoffice/chat/index.html.twig', [
            'friends' => $friends
        ]);
    }

    #[Route('/{id}', name: 'app_chat_conversation', methods: ['GET'])]
    public function conversation(User $recipient, MessageRepository $messageRepository, FriendRequestRepository $friendRequestRepository, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser === $recipient) {
            return $this->redirectToRoute('app_profile'); // Cannot chat with self
        }

        $friends = $friendRequestRepository->findFriends($currentUser);
        $messages = $messageRepository->findConversation($currentUser, $recipient);

        // Mark unread as read
        foreach ($messages as $msg) {
            if ($msg->getReceiver() === $currentUser && !$msg->isRead()) {
                $msg->setIsRead(true);
            }
        }
        $em->flush();

        return $this->render('frontoffice/chat/index.html.twig', [
            'recipient' => $recipient,
            'messages' => $messages,
            'friends' => $friends
        ]);
    }

    #[Route('/send/{id}', name: 'app_chat_send', methods: ['POST'])]
    public function send(User $recipient, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? $request->request->get('content');

        if (empty($content)) {
            return new JsonResponse(['error' => 'Content cannot be empty'], 400);
        }

        $message = new Message();
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $message->setSender($currentUser);
        $message->setReceiver($recipient);
        $message->setContent($content);

        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format('H:i'),
                'senderId' => $currentUser->getId()
            ]
        ]);
    }

    #[Route('/edit/{id}', name: 'app_chat_edit', methods: ['POST'])]
    public function edit(Message $message, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($message->getSender() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (empty($content)) {
            return new JsonResponse(['error' => 'Content cannot be empty'], 400);
        }

        $message->setContent($content);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'content' => $content]);
    }

    #[Route('/delete/{id}', name: 'app_chat_delete', methods: ['DELETE'])]
    public function delete(Message $message, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // User can delete their own messages anywhere
        if ($message->getSender() === $currentUser) {
            $em->remove($message);
            $em->flush();
            return new JsonResponse(['status' => 'success']);
        }

        // User can delete others' messages only in their own conversation
        // (i.e., if they are the receiver of the message)
        if ($message->getReceiver() === $currentUser) {
            $em->remove($message);
            $em->flush();
            return new JsonResponse(['status' => 'success']);
        }

        return new JsonResponse(['error' => 'Unauthorized'], 403);
    }

    #[Route('/upload/{id}', name: 'app_chat_upload', methods: ['POST'])]
    public function uploadFile(User $recipient, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser === $recipient) {
            return new JsonResponse(['error' => 'Cannot send to yourself'], 400);
        }

        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }

        // Validate file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return new JsonResponse(['error' => 'File too large (max 10MB)'], 400);
        }

        // Validate file type
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'video/mp4',
            'video/webm'
        ];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return new JsonResponse(['error' => 'File type not allowed'], 400);
        }

        // Determine attachment type
        $mimeType = $file->getMimeType();
        $attachmentType = 'file';
        if (str_starts_with($mimeType, 'image/')) {
            $attachmentType = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $attachmentType = 'video';
        } elseif ($mimeType === 'application/pdf') {
            $attachmentType = 'pdf';
        }

        // Generate unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // Sanitize filename - remove special characters
        $safeFilename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $originalFilename);
        $safeFilename = strtolower($safeFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Create upload directory if it doesn't exist
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/chat';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Move file
        try {
            $file->move($uploadDir, $newFilename);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to upload file: ' . $e->getMessage()], 500);
        }

        // Create message with attachment
        $message = new Message();
        $message->setSender($currentUser);
        $message->setReceiver($recipient);
        $message->setContent($file->getClientOriginalName()); // Store original filename as content
        $message->setAttachment('/uploads/chat/' . $newFilename);
        $message->setAttachmentType($attachmentType);

        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'attachment' => $message->getAttachment(),
                'attachmentType' => $message->getAttachmentType(),
                'createdAt' => $message->getCreatedAt()->format('H:i'),
                'senderId' => $currentUser->getId()
            ]
        ]);
    }
}
