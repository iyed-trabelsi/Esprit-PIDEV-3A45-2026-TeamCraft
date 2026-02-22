<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/events')]
final class EvenementController extends AbstractController
{
    #[Route('/', name: 'admin_events', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepository, \App\Repository\PlaceRepository $placeRepository, \App\Repository\ParticipationRepository $participationRepository): Response
    {
        // Auto-update expired statuses
        $evenementRepository->updateExpiredStatuses();

        // Event Sort & Search
        $evSort = $request->query->get('ev_sort', 'nomEvenement');
        $evDir = $request->query->get('ev_dir', 'ASC');
        $evQuery = $request->query->get('ev_q');
        $evStatus = $request->query->get('ev_status');
        $evenements = $evenementRepository->searchByName($evQuery, $evSort, $evDir, $evStatus);

        // Place Sort & Search
        $plSort = $request->query->get('pl_sort', 'capaciteMax');
        $plDir = $request->query->get('pl_dir', 'ASC');
        $plQuery = $request->query->get('pl_q');
        $places = $placeRepository->searchByCapacity($plQuery, $plSort, $plDir);

        // Participation Sort & Search
        $paSortField = $request->query->get('pa_sort', 'dateInscription');
        $paDir = $request->query->get('pa_dir', 'DESC');
        $paQuery = $request->query->get('pa_q');
        $paSort = 'p.' . $paSortField;
        if ($paSortField === 'evenement') {
            $paSort = 'e.nomEvenement';
        } elseif ($paSortField === 'user') {
            $paSort = 'u.username';
        }
        $participations = $participationRepository->findAllWithEventAndUser($paSort, $paDir, $paQuery);

        return $this->render('backoffice/evenement/index.html.twig', [
            'evenements' => $evenements,
            'places' => $places,
            'participations' => $participations,
            'ev_sort' => $evSort, 'ev_dir' => $evDir, 'ev_q' => $evQuery, 'ev_status' => $evStatus,
            'pl_sort' => $plSort, 'pl_dir' => $plDir, 'pl_q' => $plQuery,
            'pa_sort' => $paSortField, 'pa_dir' => $paDir, 'pa_q' => $paQuery
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Manual Validation (unchanged)
            $errors = [];
            if (empty($evenement->getNomEvenement()) || strlen($evenement->getNomEvenement()) < 8) {
                $errors[] = 'Le nom de l\'événement doit contenir au moins 8 caractères.';
            }
            if (empty($evenement->getTypeEvenement()) || strlen($evenement->getTypeEvenement()) < 8) {
                $errors[] = 'Le type de l\'événement doit contenir au moins 8 caractères.';
            }
            if ($evenement->getDateDebut() === null || $evenement->getDateDebut() < new \DateTime('today')) {
                $errors[] = 'La date de début ne peut pas être dans le passé et doit être renseignée.';
            }
            if ($evenement->getDateFin() === null || $evenement->getDateDebut() === null || $evenement->getDateFin() < $evenement->getDateDebut()) {
                $errors[] = 'La date de fin doit être postérieure à la date de début.';
            }
            if (!in_array($evenement->getStatus(), ['open', 'closed', 'over'])) {
                $errors[] = 'Le statut doit être "open", "closed" ou "over".';
            }

            // Manual Photo Validation
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $ext = strtolower($imageFile->guessExtension());
                $allowed = ['jpeg', 'jpg', 'png', 'webp'];
                if (!in_array($ext, $allowed)) {
                    $msg = 'Seuls les formats jpeg, png et webp sont acceptés.';
                    $errors[] = $msg;
                    $form->get('imageFile')->addError(new \Symfony\Component\Form\FormError($msg));
                }
                if ($imageFile->getSize() > 2 * 1024 * 1024) {
                    $msg = 'L\'image ne doit pas dépasser 2 Mo.';
                    $errors[] = $msg;
                    $form->get('imageFile')->addError(new \Symfony\Component\Form\FormError($msg));
                }
            }

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                // Handle image upload (facultatif)
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                        $newFilename
                    );
                    $evenement->setImageEvenement($newFilename);
                }

                $evenement->setOrganisateur($this->getUser());
                $entityManager->persist($evenement);
                $entityManager->flush();

                return $this->redirectToRoute('admin_events', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('backoffice/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('backoffice/evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Manual Validation (unchanged)
            $errors = [];
            if (empty($evenement->getNomEvenement()) || strlen($evenement->getNomEvenement()) < 8) {
                $errors[] = 'Le nom de l\'événement doit contenir au moins 8 caractères.';
            }
            if (empty($evenement->getTypeEvenement()) || strlen($evenement->getTypeEvenement()) < 8) {
                $errors[] = 'Le type de l\'événement doit contenir au moins 8 caractères.';
            }
            if ($evenement->getDateFin() === null || $evenement->getDateDebut() === null || $evenement->getDateFin() < $evenement->getDateDebut()) {
                $errors[] = 'La date de fin doit être postérieure à la date de début.';
            }
            if (!in_array($evenement->getStatus(), ['open', 'closed', 'over'])) {
                $errors[] = 'Le statut doit être "open", "closed" ou "over".';
            }

            // Manual Photo Validation
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $ext = strtolower($imageFile->guessExtension());
                $allowed = ['jpeg', 'jpg', 'png', 'webp'];
                if (!in_array($ext, $allowed)) {
                    $msg = 'Seuls les formats jpeg, png et webp sont acceptés.';
                    $errors[] = $msg;
                    $form->get('imageFile')->addError(new \Symfony\Component\Form\FormError($msg));
                }
                if ($imageFile->getSize() > 2 * 1024 * 1024) {
                    $msg = 'L\'image ne doit pas dépasser 2 Mo.';
                    $errors[] = $msg;
                    $form->get('imageFile')->addError(new \Symfony\Component\Form\FormError($msg));
                }
            }

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                // Handle image upload (facultatif — keeps old image if none provided)
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                        $newFilename
                    );
                    $evenement->setImageEvenement($newFilename);
                }

                $entityManager->flush();

                return $this->redirectToRoute('admin_events', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('backoffice/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_events', [], Response::HTTP_SEE_OTHER);
    }
}
