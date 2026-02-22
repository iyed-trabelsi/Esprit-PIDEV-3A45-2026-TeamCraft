<?php

namespace App\Controller;

use App\Entity\Postulation;
use App\Repository\PostulationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/manage/applications')]
class ApplicationController extends AbstractController
{
    #[Route('/{id}/accept', name: 'app_application_accept', methods: ['POST'])]
    public function accept(int $id, PostulationRepository $postulationRepository, EntityManagerInterface $em, Request $request): Response
    {
        $postulation = $postulationRepository->find($id);
        if (!$postulation) {
            throw $this->createNotFoundException('Postulation non trouvée.');
        }

        $offer = $postulation->getOffer();
        $team = $offer->getTeam();

        // Security check: Only team owner
        if ($team->getOwner() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à gérer cette candidature.');
            return $this->redirectToReferer($request);
        }

        if (!$this->isCsrfTokenValid('accept' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToReferer($request);
        }

        $postulation->setStatus('accepted');
        
        // Add user to team
        $team->addMember($postulation->getUser());
        $em->persist($team);

        $em->flush();

        $this->addFlash('success', 'Candidature acceptée ! Le joueur a rejoint l\'équipe.');
        return $this->redirectToReferer($request);
    }

    #[Route('/{id}/refuse', name: 'app_application_refuse', methods: ['POST'])]
    public function refuse(int $id, PostulationRepository $postulationRepository, EntityManagerInterface $em, Request $request): Response
    {
        $postulation = $postulationRepository->find($id);
        if (!$postulation) {
            throw $this->createNotFoundException('Postulation non trouvée.');
        }

        $offer = $postulation->getOffer();
        $team = $offer->getTeam();

        // Security check: Only team owner
        if ($team->getOwner() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à gérer cette candidature.');
            return $this->redirectToReferer($request);
        }

        if (!$this->isCsrfTokenValid('refuse' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToReferer($request);
        }

        $postulation->setStatus('refused');
        $em->flush();

        $this->addFlash('error', 'Candidature refusée.');
        return $this->redirectToReferer($request);
    }

    private function redirectToReferer(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        return $this->redirectToRoute('app_home');
    }
}
