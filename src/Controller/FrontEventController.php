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

class FrontEventController extends AbstractController
{
    #[Route('/events', name: 'app_events', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $myEvents = [];
        $forms = [];
        
        // Get search and sort parameters for My Events
        $mySearch = $request->query->get('my_search', '');
        $mySort = $request->query->get('my_sort', '');
        
        if ($user) {
            $myEvents = $evenementRepository->findBy(['organisateur' => $user]);
            
            // Update status for user's events
            foreach ($myEvents as $event) {
                $this->updateEventStatus($event, $entityManager);
            }
            
            // Apply search filter to My Events
            if ($mySearch) {
                $myEvents = array_filter($myEvents, function($event) use ($mySearch) {
                    return stripos($event->getNomEvenement(), $mySearch) !== false;
                });
            }
            
            // Apply sorting to My Events
            $myEvents = $this->sortEvents($myEvents, $mySort);
            
            // Create a form for each user event
            foreach ($myEvents as $event) {
                $forms[$event->getId()] = $this->createForm(EvenementType::class, $event, [
                    'action' => $this->generateUrl('app_front_event_edit', ['id' => $event->getId()]),
                    'method' => 'POST',
                ])->createView();
            }
        }
        
        // Get search and filter parameters for All Events
        $search = $request->query->get('search', '');
        $filterType = $request->query->get('type', '');
        $filterStatus = $request->query->get('status', '');
        $sort = $request->query->get('sort', '');
        
        // Fetch all events
        $allEvents = $evenementRepository->findAll();
        
        // Update status for all events
        foreach ($allEvents as $event) {
            $this->updateEventStatus($event, $entityManager);
        }
        
        // Apply filters
        $events = array_filter($allEvents, function($event) use ($search, $filterType, $filterStatus) {
            // Search filter
            if ($search && stripos($event->getNomEvenement(), $search) === false) {
                return false;
            }
            
            // Type filter
            if ($filterType && stripos($event->getTypeEvenement(), $filterType) === false) {
                return false;
            }
            
            // Status filter
            if ($filterStatus && $event->getStatus() !== $filterStatus) {
                return false;
            }
            
            return true;
        });
        
        // Apply sorting to All Events
        $events = $this->sortEvents($events, $sort);
        
        return $this->render('frontoffice/events/index.html.twig', [
            'events' => $events, 
            'myEvents' => $myEvents,
            'forms' => $forms,
            'search' => $search,
            'filterType' => $filterType,
            'filterStatus' => $filterStatus,
            'sort' => $sort,
            'mySearch' => $mySearch,
            'mySort' => $mySort
        ]);
    }

    private function updateEventStatus(Evenement $event, EntityManagerInterface $entityManager): void
    {
        $now = new \DateTime();
        $statusChanged = false;
        
        // Priority 1: Check if event date has passed
        if ($event->getDateFin() < $now && $event->getStatus() !== 'over') {
            $event->setStatus('over');
            $statusChanged = true;
        }
        // Priority 2: Check if event is at full capacity (only if not over)
        elseif ($event->getStatus() !== 'over') {
            $participationCount = count($event->getParticipations());
            $capaciteMax = $event->getPlace()->getCapaciteMax();
            
            if ($participationCount >= $capaciteMax && $event->getStatus() !== 'closed') {
                $event->setStatus('closed');
                $statusChanged = true;
            }
            // Reopen event if capacity becomes available and event is still ongoing
            elseif ($participationCount < $capaciteMax && $event->getStatus() === 'closed' && $event->getDateFin() >= $now) {
                $event->setStatus('open');
                $statusChanged = true;
            }
        }
        
        if ($statusChanged) {
            $entityManager->flush();
        }
    }


    private function sortEvents(array $events, string $sort): array
    {
        if (empty($sort)) {
            return $events;
        }

        $eventsArray = array_values($events);
        
        switch ($sort) {
            case 'date_desc':
                usort($eventsArray, fn($a, $b) => $b->getDateDebut() <=> $a->getDateDebut());
                break;
            case 'date_asc':
                usort($eventsArray, fn($a, $b) => $a->getDateDebut() <=> $b->getDateDebut());
                break;
            case 'name_asc':
                usort($eventsArray, fn($a, $b) => strcasecmp($a->getNomEvenement(), $b->getNomEvenement()));
                break;
            case 'name_desc':
                usort($eventsArray, fn($a, $b) => strcasecmp($b->getNomEvenement(), $a->getNomEvenement()));
                break;
            case 'status_open':
                usort($eventsArray, fn($a, $b) => ($b->getStatus() === 'open' ? 1 : 0) <=> ($a->getStatus() === 'open' ? 1 : 0));
                break;
            case 'status_closed':
                usort($eventsArray, fn($a, $b) => ($b->getStatus() === 'closed' ? 1 : 0) <=> ($a->getStatus() === 'closed' ? 1 : 0));
                break;
        }
        
        return $eventsArray;
    }

    #[Route('/events/delete/{id}', name: 'app_front_event_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            // Check if the current user is the organizer
            if ($this->getUser() === $evenement->getOrganisateur()) {
                $entityManager->remove($evenement);
                $entityManager->flush();
                $this->addFlash('success', 'Événement supprimé avec succès.');
            } else {
                $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer cet événement.');
            }
        }

        return $this->redirectToRoute('app_events', [], Response::HTTP_SEE_OTHER);
    }

    private function validateEvenement(Evenement $evenement, \Symfony\Component\Form\FormInterface $form, bool $isNew = false): array
    {
        $errors = [];
        
        if (empty($evenement->getNomEvenement()) || strlen($evenement->getNomEvenement()) < 8) {
            $errors[] = 'Le nom de l\'événement doit contenir au moins 8 caractères.';
        }
        
        if (empty($evenement->getTypeEvenement()) || strlen($evenement->getTypeEvenement()) < 8) {
            $errors[] = 'Le type de l\'événement doit contenir au moins 8 caractères.';
        }
        
        if ($isNew) {
            if ($evenement->getDateDebut() === null || $evenement->getDateDebut() < new \DateTime('today')) {
                $errors[] = 'La date de début ne peut pas être dans le passé et doit être renseignée.';
            }
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
        
        return $errors;
    }

    #[Route('/events/edit/{id}', name: 'app_front_event_edit', methods: ['POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, EvenementRepository $evenementRepository, \Symfony\Component\String\Slugger\SluggerInterface $slugger): Response
    {
        // Check if user is the organizer
        if ($this->getUser() !== $evenement->getOrganisateur()) {
            throw $this->createAccessDeniedException();
        }

        // Get redirect parameters
        $redirectRoute = $request->request->get('redirect_route', 'app_events');
        $redirectParams = json_decode($request->request->get('redirect_params', '[]'), true);

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Manual Validation (same as backoffice) - ignore Symfony validation
            $errors = $this->validateEvenement($evenement, $form, false);

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                
                // Redirect back with errors
                $request->getSession()->set('reopen_edit_event_modal_' . $evenement->getId(), true);
                return $this->redirectToRoute($redirectRoute, $redirectParams);
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

                $entityManager->flush();
                $this->addFlash('success', 'Événement modifié avec succès.');
                return $this->redirectToRoute($redirectRoute, $redirectParams, Response::HTTP_SEE_OTHER);
            }
        }

        return $this->redirectToRoute($redirectRoute, $redirectParams);
    }

    #[Route('/events/create', name: 'app_front_event_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, \Symfony\Component\String\Slugger\SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        $redirectRoute = $request->request->get('redirect_route', 'app_events');
        $redirectParams = json_decode($request->request->get('redirect_params', '[]'), true);

        if ($form->isSubmitted()) {
            // Manual Validation (same as backoffice) - ignore Symfony validation
            $errors = $this->validateEvenement($evenement, $form, true);

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                // Set flag to reopen modal on redirect
                $request->getSession()->set('reopen_create_event_modal', true);
                return $this->redirectToRoute($redirectRoute, $redirectParams);
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
                $this->addFlash('success', 'Événement créé avec succès.');
                return $this->redirectToRoute($redirectRoute, $redirectParams, Response::HTTP_SEE_OTHER);
            }
        }

        return $this->redirectToRoute($redirectRoute, $redirectParams);
    }

    #[Route('/my-events/manage', name: 'app_my_events_manage', methods: ['GET'])]
    public function myEventsManage(EvenementRepository $evenementRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // Get user's events
        $myEvents = $evenementRepository->findBy(['organisateur' => $user]);
        
        // Update status for each event
        foreach ($myEvents as $event) {
            $this->updateEventStatus($event, $entityManager);
        }
        
        // Create edit forms for each event
        $eventForms = [];
        foreach ($myEvents as $event) {
            $eventForms[$event->getId()] = $this->createForm(EvenementType::class, $event, [
                'action' => $this->generateUrl('app_front_event_edit', ['id' => $event->getId()]),
                'method' => 'POST',
            ])->createView();
        }

        return $this->render('frontoffice/events/_my_events_section.html.twig', [
            'myEvents' => $myEvents,
            'eventForms' => $eventForms,
            'is_team_manage' => true
        ]);
    }

    #[Route('/api/my-events/calendar', name: 'app_api_my_events_calendar', methods: ['GET'])]
    public function calendarData(EntityManagerInterface $entityManager, EvenementRepository $repo): Response
    {
        $user = $this->getUser();
        
        // Fetch ALL events instead of just participated ones
        $events = $repo->findAll();
        
        $eventsData = [];
        foreach ($events as $event) {
            // Sync status using existing logic
            $this->updateEventStatus($event, $entityManager);
            
            // Check if user is participating
            $isParticipating = false;
            if ($user) {
                foreach ($event->getParticipations() as $p) {
                    if ($p->getUser() === $user) {
                        $isParticipating = true;
                        break;
                    }
                }
            }

            // Status-based coloring
            // Green (#10b981) for Open
            // Red (#f43f5e) for Closed
            // Grey (#6c757d) for Over
            $color = '#10b981'; 
            if ($event->getStatus() === 'over') {
                $color = '#6c757d';
            } elseif ($event->getStatus() === 'closed') {
                $color = '#f43f5e';
            }

            $eventsData[] = [
                'id' => $event->getId(),
                'title' => ($isParticipating ? '★ ' : '') . $event->getNomEvenement(),
                'start' => $event->getDateDebut()->format(\DateTime::ISO8601),
                'end' => $event->getDateFin()->format(\DateTime::ISO8601),
                'color' => $color,
                'url' => $this->generateUrl('app_events'),
                'extendedProps' => [
                    'status' => $event->getStatus(),
                    'type' => $event->getTypeEvenement(),
                    'place' => $event->getPlace()->getNomPlace(),
                    'isParticipating' => $isParticipating
                ]
            ];
        }

        return $this->json($eventsData);
    }
}
