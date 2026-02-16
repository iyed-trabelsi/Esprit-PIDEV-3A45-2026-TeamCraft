<?php

namespace App\Controller;

use App\Entity\Participation;
use App\Form\ParticipationType;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/participation')]
final class ParticipationController extends AbstractController
{
    #[Route('/', name: 'app_participation_index', methods: ['GET'])]
    public function index(ParticipationRepository $participationRepository): Response
    {
        return $this->render('backoffice/participation/index.html.twig', [
            'participations' => $participationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_participation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $participation = new Participation();
        $form = $this->createForm(ParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Manual Validation
            $errors = [];
            if ($participation->getDateInscription() === null) {
                $errors[] = 'La date d\'inscription est obligatoire.';
            }
            
            if (count($errors) > 0) {
               foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $entityManager->persist($participation);
                $entityManager->flush();

                return $this->redirectToRoute('admin_events', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('backoffice/participation/new.html.twig', [
            'participation' => $participation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_participation_show', methods: ['GET'])]
    public function show(Participation $participation): Response
    {
        return $this->render('backoffice/participation/show.html.twig', [
            'participation' => $participation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_participation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
             // Manual Validation
            $errors = [];
            if ($participation->getDateInscription() === null) {
                $errors[] = 'La date d\'inscription est obligatoire.';
            }
            
            if (count($errors) > 0) {
               foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $entityManager->flush();

                return $this->redirectToRoute('admin_events', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('backoffice/participation/edit.html.twig', [
            'participation' => $participation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_participation_delete', methods: ['POST'])]
    public function delete(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$participation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($participation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_participation_index', [], Response::HTTP_SEE_OTHER);
    }
}
