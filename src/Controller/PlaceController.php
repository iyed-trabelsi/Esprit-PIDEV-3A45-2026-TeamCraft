<?php

namespace App\Controller;

use App\Entity\Place;
use App\Form\PlaceType;
use App\Repository\PlaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/place')]
final class PlaceController extends AbstractController
{
    #[Route('/', name: 'app_place_index', methods: ['GET'])]
    public function index(PlaceRepository $placeRepository): Response
    {
        return $this->render('backoffice/place/index.html.twig', [
            'places' => $placeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_place_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $place = new Place();
        $form = $this->createForm(PlaceType::class, $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
             // Manual Validation
            $errors = [];
            if (empty($place->getNomPlace()) || strlen($place->getNomPlace()) < 3) {
                $errors[] = 'Le nom du lieu doit contenir au moins 3 caractères.';
            }
            if (empty($place->getTypePlace()) || strlen($place->getTypePlace()) < 3) {
                $errors[] = 'Le type du lieu doit contenir au moins 3 caractères.';
            }
            if (empty($place->getAdresse()) || strlen($place->getAdresse()) < 5) {
                $errors[] = 'L\'adresse doit contenir au moins 5 caractères.';
            }
             if ($place->getCapaciteMax() === null || $place->getCapaciteMax() < 1 || $place->getCapaciteMax() > 50000) {
                $errors[] = 'La capacité doit être comprise entre 1 et 50000.';
            }

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $entityManager->persist($place);
                $entityManager->flush();

                return $this->redirectToRoute('admin_events', [], Response::HTTP_SEE_OTHER); // Redirect to admin_events as per original code
            }
        }

        return $this->render('backoffice/place/new.html.twig', [
            'place' => $place,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_place_show', methods: ['GET'])]
    public function show(Place $place): Response
    {
        return $this->render('backoffice/place/show.html.twig', [
            'place' => $place,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_place_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Place $place, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PlaceType::class, $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Manual Validation
            $errors = [];
            if (empty($place->getNomPlace()) || strlen($place->getNomPlace()) < 3) {
                $errors[] = 'Le nom du lieu doit contenir au moins 3 caractères.';
            }
            if (empty($place->getTypePlace()) || strlen($place->getTypePlace()) < 3) {
                $errors[] = 'Le type du lieu doit contenir au moins 3 caractères.';
            }
            if (empty($place->getAdresse()) || strlen($place->getAdresse()) < 5) {
                $errors[] = 'L\'adresse doit contenir au moins 5 caractères.';
            }
             if ($place->getCapaciteMax() === null || $place->getCapaciteMax() < 1 || $place->getCapaciteMax() > 50000) {
                $errors[] = 'La capacité doit être comprise entre 1 et 50000.';
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

        return $this->render('backoffice/place/edit.html.twig', [
            'place' => $place,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_place_delete', methods: ['POST'])]
    public function delete(Request $request, Place $place, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$place->getId(), $request->request->get('_token'))) {
            $entityManager->remove($place);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_events', [], Response::HTTP_SEE_OTHER);
    }
}
