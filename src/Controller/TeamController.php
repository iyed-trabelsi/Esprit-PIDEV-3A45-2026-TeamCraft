<?php

namespace App\Controller;

use App\Entity\Team;
use App\Form\TeamType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class TeamController extends AbstractController
{
    #[Route('/teams/create', name: 'team_create')]
    public function create(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = new Team();
        $team->setOwner($user);

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Upload logo
            $logoFile = $form->get('logo')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                $logoFile->move(
                    $this->getParameter('team_logos_directory'),
                    $newFilename
                );

                $team->setLogo($newFilename);
            }

            $team->setCreatedAt(new \DateTimeImmutable());

            $em->persist($team);
            $em->flush();

            $this->addFlash('success', 'Team created successfully!');

            return $this->redirectToRoute('app_teams');
        }

        return $this->render('frontoffice/teams/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/my-teams', name: 'my_teams')]
    public function myTeams(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $teams = $user->getTeams();

        return $this->render('frontoffice/teams/my_teams.html.twig', [
            'teams' => $teams,
        ]);
    }
    #[Route('/teams/{id}/manage', name: 'team_manage')]
    public function manage(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = $em->getRepository(Team::class)->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($team->getOwner() !== $user) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        // Initialize new Offer
        $offer = new \App\Entity\Offer();

        // Handle Offer Creation Form
        if ($request->isMethod('POST') && $request->request->has('create_offer')) {
            $offer->setTeam($team);
            $offer->setTitle($request->request->get('title'));
            $offer->setDescription($request->request->get('description'));
            $offer->setGame($request->request->get('game'));
            $offer->setRole($request->request->get('role'));

            // Handle Rank/Elo
            $rank = $request->request->get('rank');
            if (!$rank && $request->request->get('elo')) {
                $rank = $request->request->get('elo');
            }
            $offer->setRank($rank);

            $offer->setNbPlayerRecruited((int) $request->request->get('nbRecruited'));

            if ($request->request->get('dateCreation')) {
                $offer->setDateCreation(new \DateTime($request->request->get('dateCreation')));
            }
            if ($request->request->get('dateExpiration')) {
                $expirationDate = new \DateTime($request->request->get('dateExpiration'));
                if ($expirationDate <= $offer->getDateCreation()) {
                    $this->addFlash('error', 'Expiration date must be after creation date.');
                    return $this->redirectToRoute('team_manage', ['id' => $id]);
                }
                $offer->setDateExpiration($expirationDate);
            }

            // Handle Poster Upload
            $posterFile = $request->files->get('poster');
            if ($posterFile) {
                $originalFilename = pathinfo($posterFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                // utilizing the existing slugger service or a simple replacement
                $safeFilename = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $originalFilename)));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $posterFile->guessExtension();

                try {
                    $posterFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/offers',
                        $newFilename
                    );
                    $offer->setPoster($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error uploading poster');
                }
            }

            $em->persist($offer);
            $em->flush();

            $this->addFlash('success', 'Offer created successfully!');
            return $this->redirectToRoute('team_manage', ['id' => $id]);
        }

        // Mock Roster Data for Frontend Demo
        $roster = [
            ['name' => 'Wolfy', 'role' => 'Leader', 'status' => 'Online', 'avatar' => 'https://ui-avatars.com/api/?name=Wolfy&background=0D8ABC&color=fff'],
            ['name' => 'Ghosty', 'role' => 'Player', 'status' => 'Offline', 'avatar' => 'https://ui-avatars.com/api/?name=Ghosty&background=random'],
            ['name' => 'NightRider', 'role' => 'Player', 'status' => 'Online', 'avatar' => 'https://ui-avatars.com/api/?name=NightRider&background=random'],
            ['name' => 'Vector', 'role' => 'Player', 'status' => 'Online', 'avatar' => 'https://ui-avatars.com/api/?name=Vector&background=random'],
            ['name' => 'Shadow', 'role' => 'Player', 'status' => 'Online', 'avatar' => 'https://ui-avatars.com/api/?name=Shadow&background=random'],
        ];

        // Map stored game codes to readable labels
        $gameLabels = [
            'valorant' => 'Valorant',
            'lol' => 'League of Legends',
            'wow' => 'World of Warcraft',
            'csgo' => 'CS:GO',
            'overwatch' => 'Overwatch',
            'fortnite' => 'Fortnite',
        ];

        $teamGames = array_map(function ($gameCode) use ($gameLabels) {
            return $gameLabels[$gameCode] ?? ucfirst($gameCode);
        }, $team->getGames() ?? []);

        return $this->render('frontoffice/teams/manage.html.twig', [
            'team' => $team,
            'roster' => $roster,
            'offers' => $team->getOffers(),
            'teamGames' => $teamGames
        ]);
    }

    #[Route('/teams/{id}/edit', name: 'team_edit')]
    public function edit(Request $request, int $id, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = $em->getRepository(Team::class)->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($team->getOwner() !== $user) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Upload logo
            $logoFile = $form->get('logo')->getData();
            if ($logoFile) {
                // Delete old logo if exists (optional, good practice)
                // if ($team->getLogo()) { ... }

                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                $logoFile->move(
                    $this->getParameter('team_logos_directory'),
                    $newFilename
                );

                $team->setLogo($newFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Team updated successfully!');

            return $this->redirectToRoute('team_manage', ['id' => $team->getId()]);
        }

        return $this->render('frontoffice/teams/edit.html.twig', [
            'form' => $form->createView(),
            'team' => $team
        ]);
    }

    #[Route('/teams/{id}/delete', name: 'team_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $team = $em->getRepository(Team::class)->find($id);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($team->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not the owner of this team.');
        }

        if ($this->isCsrfTokenValid('delete_team_' . $team->getId(), $request->request->get('_token'))) {
            $em->remove($team);
            $em->flush();
            $this->addFlash('success', 'Team deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        if ($request->query->get('redirect') === 'admin') {
            return $this->redirectToRoute('admin_teams');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_teams');
        }

        return $this->redirectToRoute('app_teams');
    }
    #[Route('/offers/{id}/delete', name: 'offer_delete', methods: ['POST'])]
    public function deleteOffer(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $offer = $em->getRepository(\App\Entity\Offer::class)->find($id);

        if (!$offer) {
            throw $this->createNotFoundException('Offer not found');
        }

        if ($offer->getTeam()->getOwner() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not authorized to delete this offer.');
        }

        if ($this->isCsrfTokenValid('delete_offer_' . $offer->getId(), $request->request->get('_token'))) {
            $em->remove($offer);
            $em->flush();
            $this->addFlash('success', 'Offer deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        if ($request->request->get('redirect') === 'admin') {
            return $this->redirectToRoute('admin_offers');
        }

        return $this->redirectToRoute('team_manage', ['id' => $offer->getTeam()->getId()]);
    }

    #[Route('/offers/{id}/edit', name: 'offer_edit')]
    public function editOffer(Request $request, int $id, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $offer = $em->getRepository(\App\Entity\Offer::class)->find($id);

        if (!$offer) {
            throw $this->createNotFoundException('Offer not found');
        }

        if ($offer->getTeam()->getOwner() !== $user) {
            throw $this->createAccessDeniedException('You are not authorized to edit this offer.');
        }

        $form = $this->createForm(\App\Form\OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle Poster Update if needed
            $posterFile = $form->get('poster')->getData();
            if ($posterFile) {
                $originalFilename = pathinfo($posterFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $posterFile->guessExtension();

                try {
                    $posterFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/offers',
                        $newFilename
                    );
                    $offer->setPoster($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error uploading poster');
                }
            }

            $em->flush();
            $this->addFlash('success', 'Offer updated successfully.');

            return $this->redirectToRoute('team_manage', ['id' => $offer->getTeam()->getId()]);
        }

        return $this->render('frontoffice/teams/edit_offer.html.twig', [
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }
}
