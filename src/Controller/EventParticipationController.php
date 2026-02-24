<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Participation;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events')]
class EventParticipationController extends AbstractController
{
    #[Route('/{id}/participate', name: 'app_event_participate', methods: ['POST', 'GET'])]
    public function participate(Evenement $evenement, EntityManagerInterface $entityManager, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour participer à un événement.');
            return $this->redirectToRoute('app_login');
        }

        // Check if user is already participating
        $isParticipating = false;
        foreach ($evenement->getParticipations() as $participation) {
            if ($participation->getUser() === $user) {
                $isParticipating = true;
                break;
            }
        }

        if ($isParticipating) {
            $this->addFlash('warning', 'Vous participez déjà à cet événement.');
            return $this->redirectToRoute('app_events');
        }

        // Check capacity Strict Block
        $currentParticipants = count($evenement->getParticipations());
        $maxCapacity = $evenement->getPlace()->getCapaciteMax();

        if ($currentParticipants >= $maxCapacity || $evenement->getStatus() === 'closed') {
            $this->addFlash('error', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('app_events');
        }

        if ($evenement->getStatus() === 'over') {
            $this->addFlash('error', 'Désolé, cet événement est déjà terminé.');
            return $this->redirectToRoute('app_events');
        }

        // Create new participation
        $participation = new Participation();
        $participation->setEvenement($evenement);
        $participation->setUser($user);
        $participation->setDateInscription(new \DateTime());

        $entityManager->persist($participation);
        $entityManager->flush();

        $this->addFlash('success', 'Votre participation a été confirmée !');

        return $this->redirectToRoute('app_events');
    }

    #[Route('/{id}/cancel-participation', name: 'app_event_cancel_participation', methods: ['POST', 'GET'])]
    public function cancelParticipation(Evenement $evenement, EntityManagerInterface $entityManager, ParticipationRepository $participationRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        // Find the specific participation for this user and event
        $participation = $participationRepository->findOneBy([
            'evenement' => $evenement,
            'user' => $user
        ]);

        if (!$participation) {
            $this->addFlash('warning', 'Vous ne participez pas à cet événement.');
            return $this->redirectToRoute('app_events');
        }

        // Remove the participation
        $entityManager->remove($participation);
        $entityManager->flush();

        $this->addFlash('success', 'Votre participation a été annulée avec succès.');

        return $this->redirectToRoute('app_events');
    }
}
