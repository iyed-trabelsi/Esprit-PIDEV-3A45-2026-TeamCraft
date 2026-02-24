<?php

namespace App\Controller;

use App\Entity\Postulation;
use App\Repository\PostulationRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/manage/applications')]
class ApplicationController extends AbstractController
{
    #[Route('/{id}/accept', name: 'app_application_accept', methods: ['POST'])]
    public function accept(
        int $id,
        PostulationRepository $postulationRepository,
        EntityManagerInterface $em,
        Request $request,
        EmailService $emailService
    ): Response {
        $postulation = $postulationRepository->find($id);
        if (!$postulation) {
            throw $this->createNotFoundException('Postulation non trouvée.');
        }

        $offer = $postulation->getOffer();
        $team = $offer->getTeam();

        // Vérification des droits
        if ($team->getOwner() !== $this->getUser() && !$team->isCoOwner($this->getUser())) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à gérer cette candidature.');
            return $this->redirectToReferer($request);
        }

        if (!$this->isCsrfTokenValid('accept' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToReferer($request);
        }

        // Mise à jour du statut
        $postulation->setStatus('accepted');

        $team->addMember($postulation->getUser());
        $em->persist($team);
        $em->flush();

        // Envoi de l'email de notification
        try {
            $emailService->sendApplicationStatusEmail($postulation, 'accepted');
            $this->addFlash('success', 'Candidature acceptée ! Le joueur a rejoint l\'équipe et a été notifié par email. ✅');
        } catch (\Exception $e) {
            // L'action principale (acceptation) a réussi, on informe juste que l'email a échoué
            $this->addFlash('success', 'Candidature acceptée ! Le joueur a rejoint l\'équipe.');
            $this->addFlash('warning', 'Note : l\'email de notification n\'a pas pu être envoyé. (' . $e->getMessage() . ')');
        }

        return $this->redirectToReferer($request);
    }

    #[Route('/{id}/refuse', name: 'app_application_refuse', methods: ['POST'])]
    public function refuse(
        int $id,
        PostulationRepository $postulationRepository,
        EntityManagerInterface $em,
        Request $request,
        EmailService $emailService
    ): Response {
        $postulation = $postulationRepository->find($id);
        if (!$postulation) {
            throw $this->createNotFoundException('Postulation non trouvée.');
        }

        $offer = $postulation->getOffer();
        $team = $offer->getTeam();

        // Vérification des droits : Owner OU Co-Owner
        if ($team->getOwner() !== $this->getUser() && !$team->isCoOwner($this->getUser())) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à gérer cette candidature.');
            return $this->redirectToReferer($request);
        }

        if (!$this->isCsrfTokenValid('refuse' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToReferer($request);
        }

        // Mise à jour du statut
        $postulation->setStatus('refused');
        $em->flush();

        // Envoi de l'email de notification
        try {
            $emailService->sendApplicationStatusEmail($postulation, 'refused');
            $this->addFlash('error', 'Candidature refusée. Le joueur a été notifié par email.');
        } catch (\Exception $e) {
            // L'action principale (refus) a réussi, on informe juste que l'email a échoué
            $this->addFlash('error', 'Candidature refusée.');
            $this->addFlash('warning', 'Note : l\'email de notification n\'a pas pu être envoyé. (' . $e->getMessage() . ')');
        }

        return $this->redirectToReferer($request);
    }

    /**
     * Redirige vers la page précédente (referer) ou vers l'accueil.
     */
    private function redirectToReferer(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        return $this->redirectToRoute('app_home');
    }
}
