<?php

namespace App\Controller;

use App\Entity\FriendRequest;
use App\Entity\User;
use App\Repository\FriendRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class FriendController extends AbstractController
{
    #[Route('/friends', name: 'app_friend_list')]
    public function index(FriendRequestRepository $friendRequestRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // 1. Pending Received
        $pendingReceived = $friendRequestRepository->findBy([
            'receiver' => $user,
            'status' => 'pending'
        ], ['createdAt' => 'DESC']);

        // 2. Pending Sent
        $pendingSent = $friendRequestRepository->findBy([
            'sender' => $user,
            'status' => 'pending'
        ], ['createdAt' => 'DESC']);

        // 3. Friends (Accepted, either sender or receiver)
        // Check custom repository method or simple logic
        // We'll use the repository query in real app, here manual filter for simplicity or custom query
        // Using existing findFriendship is singular, let's fetch all accepted

        $acceptedAsSender = $friendRequestRepository->findBy(['sender' => $user, 'status' => 'accepted']);
        $acceptedAsReceiver = $friendRequestRepository->findBy(['receiver' => $user, 'status' => 'accepted']);
        $friends = array_merge($acceptedAsSender, $acceptedAsReceiver);

        return $this->render('frontoffice/friends/index.html.twig', [
            'pendingReceived' => $pendingReceived,
            'pendingSent' => $pendingSent,
            'friends' => $friends
        ]);
    }

    #[Route('/friend/send/{id}', name: 'app_friend_send', methods: ['POST'])]
    public function send(User $receiver, FriendRequestRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $sender = $this->getUser();
        if (!$sender)
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        if ($sender === $receiver)
            return new JsonResponse(['error' => 'Cannot overwrite self'], 400);

        // Check existing
        $existing = $repo->findFriendship($sender, $receiver);
        if ($existing) {
            return new JsonResponse(['error' => 'Request already exists'], 400);
        }

        $req = new FriendRequest();
        $req->setSender($sender);
        $req->setReceiver($receiver);
        $req->setStatus('pending');

        $em->persist($req);
        $em->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Request sent', 'id' => $req->getId()]);
    }

    #[Route('/friend/action/{id}/{action}', name: 'app_friend_action', methods: ['POST'])]
    public function action(FriendRequest $friendRequest, string $action, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user)
            return new JsonResponse(['error' => 'Not authenticated'], 401);

        // Authorization
        if ($action === 'accept' || $action === 'decline') {
            if ($friendRequest->getReceiver() !== $user) {
                return new JsonResponse(['error' => 'Unauthorized'], 403);
            }
        } elseif ($action === 'cancel') {
            if ($friendRequest->getSender() !== $user) {
                return new JsonResponse(['error' => 'Unauthorized'], 403);
            }
        } else {
            return new JsonResponse(['error' => 'Invalid action'], 400);
        }

        if ($action === 'accept') {
            $friendRequest->setStatus('accepted');
        } elseif ($action === 'decline' || $action === 'cancel') {
            // Delete the request for decline/cancel to allow re-send later or just clean up
            $em->remove($friendRequest);
            $em->flush();
            return new JsonResponse(['status' => 'removed']);
        }

        $em->flush();
        return new JsonResponse(['status' => 'updated', 'new_state' => $action]);
    }

    #[Route('/friends/count', name: 'app_friend_count', methods: ['GET'])]
    public function count(FriendRequestRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user)
            return new JsonResponse(['count' => 0]);

        $count = $repo->countPendingRequests($user);
        return new JsonResponse(['count' => $count]);
    }
}
