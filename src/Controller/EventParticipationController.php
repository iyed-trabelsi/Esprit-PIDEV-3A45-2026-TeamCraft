<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Participation;
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

        // Check capacity
        $currentParticipants = count($evenement->getParticipations());
        $maxCapacity = $evenement->getPlace()->getCapaciteMax();

        if ($currentParticipants >= $maxCapacity) {
            $this->addFlash('error', 'Désolé, cet événement est complet.');
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
}
