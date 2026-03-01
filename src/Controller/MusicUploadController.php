<?php

namespace App\Controller;

use App\Entity\MusicTrack;
use App\Form\MusicUploadType;
use App\Repository\MusicTrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/music', name: 'music_')]
#[IsGranted('ROLE_USER')]
class MusicUploadController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MusicTrackRepository $musicTrackRepository,
        private SluggerInterface $slugger,
        private string $uploadsMusicDir
    ) {
    }

    #[Route('/upload', name: 'upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $musicTrack = new MusicTrack();
        $form = $this->createForm(MusicUploadType::class, $musicTrack);
        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            $errs = [];
            foreach ($form->getErrors(true) as $e) {
                $errs[] = $e->getMessage();
            }
            file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', date('Y-m-d H:i:s') . " | POST | isSubmitted: " . ($form->isSubmitted() ? 'Y' : 'N') . " | isValid: " . ($form->isValid() ? 'Y' : 'N') . " | Errors: " . implode(', ', $errs) . "\nPOST: " . print_r($_POST, true) . "\nFILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);
        }

        // Detect if post_max_size was exceeded
        if ($request->isMethod('POST') && !$form->isSubmitted()) {
            $this->addFlash('error', 'Le fichier envoyé est trop volumineux ou une erreur inattendue s\'est produite.');
            return $this->redirectToRoute('music_upload');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: Entered isValid block\n", FILE_APPEND);

            /** @var UploadedFile $musicFile */
            $musicFile = $form->get('musicFile')->getData();

            if (!$musicFile) {
                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: musicFile is NULL\n", FILE_APPEND);
                $this->addFlash('error', 'Aucun fichier n\'a été sélectionné.');
                return $this->redirectToRoute('music_upload');
            }

            try {
                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: inside Try block\n", FILE_APPEND);
                $originalFilename = pathinfo($musicFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $extension = $musicFile->guessExtension() ?? $musicFile->getClientOriginalExtension();
                if (empty($extension)) {
                    $extension = 'mp3'; // Fallback final
                }
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

                if (!is_dir($this->uploadsMusicDir)) {
                    mkdir($this->uploadsMusicDir, 0777, true);
                }

                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: preparing to get Mime\n", FILE_APPEND);
                $mimeType = $musicFile->getMimeType();
                $fileSize = $musicFile->getSize();
                $originalName = $musicFile->getClientOriginalName();

                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: pre-move\n", FILE_APPEND);
                $musicFile->move($this->uploadsMusicDir, $newFilename);

                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: post-move\n", FILE_APPEND);
                $musicTrack->setFilename($newFilename);
                $musicTrack->setOriginalFilename($originalName);
                $musicTrack->setMimeType($mimeType);
                $musicTrack->setFileSize($fileSize);

                $user = $this->getUser();
                if (!$user) {
                    $this->addFlash('error', 'Erreur : utilisateur non connecté.');
                    return $this->redirectToRoute('music_upload');
                }
                $musicTrack->setUser($user);
                $musicTrack->setDuration(null);

                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: pre-persist\n", FILE_APPEND);
                $this->entityManager->persist($musicTrack);

                try {
                    $this->entityManager->flush();
                    file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: Database flush success. New ID = " . $musicTrack->getId() . "\n", FILE_APPEND);
                } catch (\Exception $e) {
                    file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: Database error: " . $e->getMessage() . "\n", FILE_APPEND);
                    $this->addFlash('error', 'Erreur SQL lors de l\'enregistrement : ' . $e->getMessage());
                    return $this->redirectToRoute('music_upload');
                }

                // Vérifier que l'entité a bien un ID après le flush
                if (!$musicTrack->getId()) {
                    file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: no ID after flush!\n", FILE_APPEND);
                    $this->addFlash('error', 'Erreur : la musique n\'a pas été enregistrée en base de données.');
                    return $this->redirectToRoute('music_upload');
                }

                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "DEBUG: redirecting successfully!\n", FILE_APPEND);
                $this->addFlash('success', 'Musique uploadée avec succès ! (ID: ' . $musicTrack->getId() . ')');
                return $this->redirectToRoute('music_library');
            } catch (\Throwable $e) {
                file_put_contents($this->getParameter('kernel.project_dir') . '/var/log/music_upload_debug.log', "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
                $this->addFlash('error', 'Erreur lors de l\'upload : ' . $e->getMessage());
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            if (!empty($errors)) {
                $this->addFlash('error', 'Erreurs de validation : ' . implode(', ', $errors));
            }
        }

        return $this->render('frontoffice/music/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/library', name: 'library', methods: ['GET'])]
    public function library(): Response
    {
        $user = $this->getUser();
        $tracks = $this->musicTrackRepository->findActiveByUser($user);

        return $this->render('frontoffice/music/library.html.twig', [
            'tracks' => $tracks,
        ]);
    }

    #[Route('/api/tracks', name: 'api_tracks', methods: ['GET'])]
    public function getTracks(): JsonResponse
    {
        $tracks = $this->musicTrackRepository->findAllActive();

        $data = [];
        foreach ($tracks as $track) {
            $data[] = [
                'id' => $track->getId(),
                'title' => $track->getTitle(),
                'artist' => $track->getArtist() ?? 'Unknown',
                'src' => $track->getFileUrl(),
                'duration' => $track->getDuration(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $track = $this->musicTrackRepository->find($id);

        if (!$track) {
            throw $this->createNotFoundException('Musique non trouvée');
        }

        if ($track->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cette musique');
        }

        // Delete file
        $filePath = $this->uploadsMusicDir . '/' . $track->getFilename();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $track->setStatus('deleted');
        $this->entityManager->flush();

        $this->addFlash('success', 'Musique supprimée avec succès !');
        return $this->redirectToRoute('music_library');
    }
}
