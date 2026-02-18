<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\Rubrique;
use App\Form\CommentType;
use App\Form\PostType;
use App\Form\RubriqueType;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\RubriqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/forum')]
class ForumController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RubriqueRepository $rubriqueRepository,
        private PostRepository $postRepository,
        private CommentRepository $commentRepository,
        private SluggerInterface $slugger,
        private \App\Service\ContentModeratorService $moderator,
        private \App\Service\ImageModerationService $imageModerator,
        private \App\Service\SpamDetectorService $spamDetector,
        private \App\Service\SmartWritingService $smartWriting,
        private \App\Service\ImageGenerationService $imageGenerator,
        private \App\Service\PostRecommendationService $recommendationService
    ) {
    }



    #[Route('', name: 'app_forum', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sort = $request->query->get('sort', 'DESC');
        $rubriques = $this->rubriqueRepository->findAllActive($sort);
        $mesRubriques = [];
        if ($this->getUser()) {
            $mesRubriques = $this->rubriqueRepository->findByAuteur($this->getUser());
        }
        $recommendations = $this->recommendationService->getRecommendations($this->getUser());

        return $this->render('frontoffice/forum/index.html.twig', [
            'rubriques' => $rubriques,
            'mes_rubriques' => $mesRubriques,
            'currentSort' => $sort,
            'recommendations' => $recommendations,
        ]);
    }

    #[Route('/mes-rubriques', name: 'app_forum_mes_rubriques', methods: ['GET'])]
    public function mesRubriques(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $mesRubriques = $this->rubriqueRepository->findByAuteur($user);
        return $this->render('frontoffice/forum/mes_rubriques.html.twig', [
            'mes_rubriques' => $mesRubriques,
        ]);
    }

    #[Route('/rubrique/{id}', name: 'app_forum_rubrique_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function rubriqueShow(int $id): Response
    {
        $rubrique = $this->rubriqueRepository->find($id);
        if (!$rubrique) {
            throw $this->createNotFoundException('Rubrique introuvable.');
        }
        $posts = $this->postRepository->findByRubrique($rubrique, $this->getUser());
        $canEdit = $this->isAuthor($rubrique->getAuteur());
        $canAddPost = $rubrique->getEtat() === 'active';
        return $this->render('frontoffice/forum/rubrique_show.html.twig', [
            'rubrique' => $rubrique,
            'posts' => $posts,
            'canEdit' => $canEdit,
            'canAddPost' => $canAddPost,
        ]);
    }

    #[Route('/rubrique/new', name: 'app_forum_rubrique_new', methods: ['GET', 'POST'])]
    public function rubriqueNew(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $rubrique = new Rubrique();
        $rubrique->setAuteur($this->getUser());
        $topics = $this->rubriqueRepository->findDistinctTopics();
        if (empty($topics)) {
            $topics = ['Général', 'Recrutement', 'Support', 'Discussion', 'Événements'];
        }

        $form = $this->createForm(RubriqueType::class, $rubrique, ['topics' => $topics]);
        $form->handleRequest($request);

        // Sujet : soit liste déroulante, soit champ texte (jamais les deux). Priorité à la liste.
        $selectedTopic = $form->get('topicSelect')->getData();
        if (!empty($selectedTopic)) {
            $rubrique->setExistingTopic($selectedTopic);
            $rubrique->setTopic($selectedTopic); // on prend uniquement la liste, l'input n'est pas obligatoire
        }
        // sinon on garde la valeur du champ "Nouveau sujet" (déjà mappée) et la validation exige qu'il soit rempli

        $existingRubrique = null;

        if ($form->isSubmitted()) {
            $existing = $this->rubriqueRepository->findOneBy(['nomRubrique' => $rubrique->getNomRubrique()]);
            if ($existing && $existing->getId() !== $rubrique->getId()) {
                $form->get('nomRubrique')->addError(new \Symfony\Component\Form\FormError('Ce nom de rubrique est déjà utilisé.'));
                $existingRubrique = $existing;
            }
        }

        if ($form->isSubmitted()) {
             // Debug form validity
             // dd("Form Valid: " . ($form->isValid() ? 'YES' : 'NO'));
        }

        if ($form->isSubmitted() && $form->isValid() && !$existingRubrique) {
            $nomRubrique = $rubrique->getNomRubrique();
            $description = $rubrique->getDescription();
            $topic = $rubrique->getTopic();
            if (($nomRubrique && $this->moderator->isToxic($nomRubrique)) ||
                ($description && $this->moderator->isToxic($description)) ||
                ($topic && $this->moderator->isToxic($topic))) {
                
                $toxicError = '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Veuillez modifier le contenu de votre rubrique.';
                $this->addFlash('warning', $toxicError);
                return $this->render('frontoffice/forum/rubrique_form.html.twig', [
                    'rubrique' => $rubrique,
                    'form' => $form,
                    'is_edit' => false,
                    'existingRubrique' => $existingRubrique,
                    'toxic_error' => $toxicError
                ]);
            }

            // Handle image upload
            $file = $form->get('image')->getData();
            $generatedFilename = $request->request->get('generated_image_filename');
            
            if ($generatedFilename) {
                // Use AI-generated image
                $rubrique->setImage($generatedFilename);
            } elseif ($file) {
                // Use uploaded image
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $uploadDir = $this->getParameter('uploads_rubrique_dir');
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $file->move($uploadDir, $newFilename);
                    $rubrique->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            $rubrique->setNbPosts(0);
            $this->em->persist($rubrique);
            $this->em->flush();
            $this->addFlash('success', 'Rubrique créée.');
            return $this->redirectToRoute('app_forum');
        }
        
        return $this->render('frontoffice/forum/rubrique_form.html.twig', [
            'rubrique' => $rubrique,
            'form' => $form,
            'is_edit' => false,
            'existingRubrique' => $existingRubrique,
        ]);
    }

    #[Route('/rubrique/{id}/edit', name: 'app_forum_rubrique_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function rubriqueEdit(Request $request, int $id): Response
    {
        $rubrique = $this->rubriqueRepository->find($id);
        if (!$rubrique) {
            throw $this->createNotFoundException('Rubrique introuvable.');
        }
        if (!$this->isAuthor($rubrique->getAuteur())) {
            $this->addFlash('error', 'Seul l\'auteur peut modifier cette rubrique.');
            return $this->redirectToRoute('app_forum_rubrique_show', ['id' => $id]);
        }
        
        $topics = $this->rubriqueRepository->findDistinctTopics();
        if (empty($topics)) {
            $topics = ['Général', 'Recrutement', 'Support', 'Discussion', 'Événements'];
        }

        $form = $this->createForm(RubriqueType::class, $rubrique, ['topics' => $topics]);
        $form->handleRequest($request);

        // Sujet : soit liste déroulante, soit champ texte (jamais les deux). Priorité à la liste.
        $selectedTopic = $form->get('topicSelect')->getData();
        if (!empty($selectedTopic)) {
            $rubrique->setExistingTopic($selectedTopic);
            $rubrique->setTopic($selectedTopic); // on prend uniquement la liste, l'input n'est pas obligatoire
        }

        $existingRubrique = null;

        if ($form->isSubmitted()) {
            $existing = $this->rubriqueRepository->findOneBy(['nomRubrique' => $rubrique->getNomRubrique()]);
            if ($existing && $existing->getId() !== $rubrique->getId()) {
                $form->get('nomRubrique')->addError(new \Symfony\Component\Form\FormError('Ce nom de rubrique est déjà utilisé.'));
                $existingRubrique = $existing;
            }
        }

        if ($form->isSubmitted() && $form->isValid() && !$existingRubrique) {
            $nomRubrique = $rubrique->getNomRubrique();
            $description = $rubrique->getDescription();
            $topic = $rubrique->getTopic();

            if (($nomRubrique && $this->moderator->isToxic($nomRubrique)) ||
                ($description && $this->moderator->isToxic($description)) ||
                ($topic && $this->moderator->isToxic($topic))) {
                
                $toxicError = '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Veuillez modifier le contenu de votre rubrique.';
                $this->addFlash('warning', $toxicError);
                return $this->render('frontoffice/forum/rubrique_form.html.twig', [
                    'rubrique' => $rubrique,
                    'form' => $form,
                    'is_edit' => true,
                    'existingRubrique' => $existingRubrique,
                    'toxic_error' => $toxicError
                ]);
            }

            // Handle image upload
            $file = $form->get('image')->getData();
            $generatedFilename = $request->request->get('generated_image_filename');
            $deleteCurrentImage = $request->request->get('delete_current_image') === '1';
            
            if ($generatedFilename) {
                // Use AI-generated image (delete old if exists)
                if ($rubrique->getImage() && $rubrique->getImage() !== $generatedFilename) {
                    $uploadDir = $this->getParameter('uploads_rubrique_dir');
                    $oldImage = $uploadDir . DIRECTORY_SEPARATOR . $rubrique->getImage();
                    if (file_exists($oldImage)) {
                        @unlink($oldImage);
                    }
                }
                $rubrique->setImage($generatedFilename);
            } elseif ($file) {
                // Use uploaded image
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $uploadDir = $this->getParameter('uploads_rubrique_dir');
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    // Delete old image if exists
                    if ($rubrique->getImage()) {
                        $oldImage = $uploadDir . DIRECTORY_SEPARATOR . $rubrique->getImage();
                        if (file_exists($oldImage)) {
                            @unlink($oldImage);
                        }
                    }
                    $file->move($uploadDir, $newFilename);
                    $rubrique->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            } elseif ($deleteCurrentImage) {
                // Explicitly delete image
                if ($rubrique->getImage()) {
                    $uploadDir = $this->getParameter('uploads_rubrique_dir');
                    $oldImage = $uploadDir . DIRECTORY_SEPARATOR . $rubrique->getImage();
                    if (file_exists($oldImage)) {
                        @unlink($oldImage);
                    }
                }
                $rubrique->setImage(null);
            }

            $this->em->flush();
            $this->addFlash('success', 'Rubrique mise à jour.');
            return $this->redirectToRoute('app_forum_rubrique_show', ['id' => $id]);
        }
        
        return $this->render('frontoffice/forum/rubrique_form.html.twig', [
            'rubrique' => $rubrique,
            'form' => $form,
            'is_edit' => true,
            'existingRubrique' => $existingRubrique,
        ]);
    }

    #[Route('/rubrique/{id}/delete', name: 'app_forum_rubrique_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rubriqueDelete(Request $request, int $id): Response
    {
        $rubrique = $this->rubriqueRepository->find($id);
        if (!$rubrique) {
            throw $this->createNotFoundException('Rubrique introuvable.');
        }
        if (!$this->isAuthor($rubrique->getAuteur())) {
            $this->addFlash('error', 'Seul l\'auteur peut supprimer cette rubrique.');
            return $this->redirectToRoute('app_forum');
        }
        if ($this->isCsrfTokenValid('delete_rubrique_' . $id, (string) $request->request->get('_token'))) {
            $this->em->remove($rubrique);
            $this->em->flush();
            $this->addFlash('success', 'Rubrique supprimée.');
        }
        return $this->redirectToRoute('app_forum');
    }

    #[Route('/post/{id}', name: 'app_forum_post_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function postShow(int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable.');
        }
        if ($post->getStatut() !== 'published' && $post->getStatut() !== 'pending_review' && !$this->isAuthor($post->getAuteur())) {
            throw $this->createNotFoundException('Ce post n\'est pas accessible.');
        }
        $post->incrementNbVues();
        $this->em->flush();
        $comments = $this->commentRepository->findTopLevelByPost($post, $this->getUser());
        $canEdit = $this->isAuthor($post->getAuteur());
        $commentForm = $this->createForm(CommentType::class, new Comment());
        return $this->render('frontoffice/forum/post_show.html.twig', [
            'post' => $post,
            'comments' => $comments,
            'canEdit' => $canEdit,
            'commentForm' => $commentForm,
        ]);
    }

    #[Route('/post/new', name: 'app_forum_post_new', methods: ['GET', 'POST'])]
    public function postNew(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $rubriqueId = $request->query->getInt('rubrique');
        $rubrique = $rubriqueId ? $this->rubriqueRepository->find($rubriqueId) : null;
        if ($rubrique && $rubrique->getEtat() !== 'active') {
            $this->addFlash('error', 'Cette rubrique est archivée. Vous ne pouvez pas y ajouter de post.');
            return $this->redirectToRoute('app_forum_rubrique_show', ['id' => $rubrique->getId()]);
        }
        $post = new Post();
        $post->setAuteur($this->getUser());
        if ($rubrique) {
            $post->setRubrique($rubrique);
        }
        $form = $this->createForm(PostType::class, $post, ['with_rubrique' => !$rubrique]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $rubriqueForPost = $post->getRubrique();
            if ($rubriqueForPost && $rubriqueForPost->getEtat() !== 'active') {
                $this->addFlash('error', 'Cette rubrique est archivée. Vous ne pouvez pas y ajouter de post.');
                return $this->redirectToRoute('app_forum');
            }

            $spamResult = $this->spamDetector->checkSpam($this->getUser(), $post->getContenu() ?? $post->getTitre() ?? '', 'post');
            if ($spamResult['isSpam']) {
                $this->addFlash('warning', '🛑 ACTION BLOQUÉE : ' . $spamResult['reason']);
                return $this->render('frontoffice/forum/post_form.html.twig', [
                    'post' => $post,
                    'form' => $form,
                    'is_edit' => false,
                ]);
            }

            if ($this->moderator->isToxic($post->getTitre()) || ($post->getContenu() && $this->moderator->isToxic($post->getContenu()))) {
                $toxicError = '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Votre post contient des propos offensants, des insultes ou du langage vulgaire. Veuillez modifier votre message et respecter la communauté.';
                $this->addFlash('warning', $toxicError);
                return $this->render('frontoffice/forum/post_form.html.twig', [
                    'post' => $post,
                    'form' => $form,
                    'is_edit' => false,
                    'toxic_error' => $toxicError
                ]);
            }

            $file = $form->get('image')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $uploadDir = $this->getParameter('uploads_post_dir');
                    $file->move($uploadDir, $newFilename);
                    $absolutePath = realpath($uploadDir . \DIRECTORY_SEPARATOR . $newFilename) ?: $uploadDir . \DIRECTORY_SEPARATOR . $newFilename;
                    $result = $this->imageModerator->analyzeImage($absolutePath);
                    if ($result['status'] === 'reject') {
                        @unlink($absolutePath);
                        // Erreur technique (script indisponible) → on enregistre le post SANS l'image
                        if (isset($result['error'])) {
                            $this->addFlash('warning', $result['message'] ?? 'L\'image n\'a pas pu être vérifiée et n\'a pas été ajoutée. Votre post a été enregistré.');
                            // on continue : le post sera sauvegardé sans image
                        } else {
                            // Contenu sensible détecté (nudité, etc.) → on ne sauvegarde pas le post
                            $this->addFlash('warning', $result['message'] ?? 'Cette image n\'est pas autorisée.');
                            return $this->render('frontoffice/forum/post_form.html.twig', [
                                'post' => $post,
                                'form' => $form,
                                'is_edit' => false,
                                'toxic_error' => $result['message'] ?? null,
                            ]);
                        }
                    } else {
                        $post->setImage($newFilename);
                        $post->setImageSensitivity((!empty($result['warning_message']) || $result['status'] === 'pending_review') ? 'medium' : null);
                        if (isset($result['error'])) {
                            $this->addFlash('notice', $result['message'] ?? 'Modération indisponible; image enregistrée.');
                        }
                        if ($result['status'] === 'pending_review') {
                            $post->setStatut('pending_review');
                            $this->addFlash('notice', $result['message'] ?? 'Votre post sera visible après modération.');
                        }
                        if (!empty($result['warning_message'])) {
                            $this->addFlash('warning', $result['warning_message']);
                        }
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }
            if ($post->getRubrique()) {
                $rubrique = $post->getRubrique();
                $rubrique->setNbPosts($rubrique->getNbPosts() + 1);
            }
            $this->em->persist($post);
            $this->em->flush();
            $this->addFlash('success', 'Post créé.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $post->getId()]);
        }
        return $this->render('frontoffice/forum/post_form.html.twig', [
            'post' => $post,
            'form' => $form,
            'is_edit' => false,
        ]);
    }

    #[Route('/post/{id}/edit', name: 'app_forum_post_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function postEdit(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable.');
        }
        if ($post->getStatut() !== 'published' && !$this->isAuthor($post->getAuteur())) {
            throw $this->createNotFoundException('Ce post n\'est pas accessible.');
        }
        if (!$this->isAuthor($post->getAuteur())) {
            $this->addFlash('error', 'Seul l\'auteur peut modifier ce post.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
        }
        $form = $this->createForm(PostType::class, $post, ['with_rubrique' => false, 'is_edit' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->moderator->isToxic($post->getTitre()) || ($post->getContenu() && $this->moderator->isToxic($post->getContenu()))) {
                $this->addFlash('warning', '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Votre post contient des propos offensants, des insultes ou du langage vulgaire. Veuillez modifier votre message et respecter la communauté.');
                return $this->render('frontoffice/forum/post_form.html.twig', [
                    'post' => $post,
                    'form' => $form,
                    'is_edit' => true,
                ]);
            }

            $file = $form->get('image')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $uploadDir = $this->getParameter('uploads_post_dir');
                    $file->move($uploadDir, $newFilename);
                    $absolutePath = realpath($uploadDir . \DIRECTORY_SEPARATOR . $newFilename) ?: $uploadDir . \DIRECTORY_SEPARATOR . $newFilename;
                    $result = $this->imageModerator->analyzeImage($absolutePath);
                    if ($result['status'] === 'reject') {
                        @unlink($absolutePath);
                        if (isset($result['error'])) {
                            $this->addFlash('warning', $result['message'] ?? 'L\'image n\'a pas pu être vérifiée et n\'a pas été ajoutée.');
                        } else {
                            $this->addFlash('warning', $result['message'] ?? 'Cette image n\'est pas autorisée.');
                            return $this->render('frontoffice/forum/post_form.html.twig', [
                                'post' => $post,
                                'form' => $form,
                                'is_edit' => true,
                                'toxic_error' => $result['message'] ?? null,
                            ]);
                        }
                    } else {
                        $post->setImage($newFilename);
                        $post->setImageSensitivity((!empty($result['warning_message']) || $result['status'] === 'pending_review') ? 'medium' : null);
                        if (isset($result['error'])) {
                            $this->addFlash('notice', $result['message'] ?? 'Modération indisponible; image enregistrée.');
                        }
                        if ($result['status'] === 'pending_review') {
                            $post->setStatut('pending_review');
                            $this->addFlash('notice', $result['message'] ?? 'Votre post sera visible après modération.');
                        }
                        if (!empty($result['warning_message'])) {
                            $this->addFlash('warning', $result['warning_message']);
                        }
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }
            $this->em->flush();

            if ($post->getStatut() === 'archived') {
                $this->addFlash('success', 'Post archivé.');
                if ($post->getRubrique()) {
                    return $this->redirectToRoute('app_forum_rubrique_show', ['id' => $post->getRubrique()->getId()]);
                }
                return $this->redirectToRoute('app_forum');
            }

            $this->addFlash('success', 'Post mis à jour.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
        }
        return $this->render('frontoffice/forum/post_form.html.twig', [
            'post' => $post,
            'form' => $form,
            'is_edit' => true,
        ]);
    }

    #[Route('/post/{id}/like', name: 'app_forum_post_like', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postLike(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Veuillez vous connecter pour aimer ce post.'], 403);
        }

        $post = $this->postRepository->find($id);
        if (!$post) {
            return new JsonResponse(['error' => 'Post non trouvé.'], 404);
        }

        if ($post->isLikedBy($user)) {
            $post->removeLikedBy($user);
            $liked = false;
        } else {
            $post->addLikedBy($user);
            $liked = true;
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'liked' => $liked,
            'nbLikes' => $post->getNbLikes()
        ]);
    }

    #[Route('/post/{id}/delete', name: 'app_forum_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postDelete(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable.');
        }
        if ($post->getStatut() !== 'published' && !$this->isAuthor($post->getAuteur())) {
            throw $this->createNotFoundException('Ce post n\'est pas accessible.');
        }
        if (!$this->isAuthor($post->getAuteur())) {
            $this->addFlash('error', 'Seul l\'auteur peut supprimer ce post.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
        }
        $rubriqueId = $post->getRubrique()?->getId();
        if ($this->isCsrfTokenValid('delete_post_' . $id, (string) $request->request->get('_token'))) {
            $this->em->remove($post);
            if ($post->getRubrique()) {
                $rubrique = $post->getRubrique();
                $rubrique->setNbPosts(max(0, $rubrique->getNbPosts() - 1));
            }
            $this->em->flush();
            $this->addFlash('success', 'Post supprimé.');
        }
        return $this->redirectToRoute($rubriqueId ? 'app_forum_rubrique_show' : 'app_forum', $rubriqueId ? ['id' => $rubriqueId] : []);
    }

    #[Route('/post/{id}/comment', name: 'app_forum_comment_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function commentNew(Request $request, int $id): Response
    {
        // Si accès en GET (ex: redirection après login), on redirige vers l'affichage du post
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
        }
        $this->denyAccessUnlessGranted('ROLE_USER');
        $post = $this->postRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable.');
        }
        if ($post->getStatut() !== 'published' && $post->getStatut() !== 'pending_review' && !$this->isAuthor($post->getAuteur())) {
            throw $this->createNotFoundException('Ce post n\'est pas accessible.');
        }
        $comment = new Comment();
        $comment->setAuteur($this->getUser());
        $comment->setPost($post);
        $parentId = $request->request->getInt('parent_id');
        if ($parentId) {
            $parent = $this->commentRepository->find($parentId);
            if ($parent && $parent->getPost() === $post) {
                $comment->setParent($parent);
            }
        }
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contenu = $comment->getContenu() ?? '';

            // 0. AI Spam Detection Check
            $spamResult = $this->spamDetector->checkSpam($this->getUser(), $contenu, 'comment');
            if ($spamResult['isSpam']) {
                $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'errors' => [$spamResult['reason']]], 403);
                }
                $this->addFlash('warning', '🛑 ACTION BLOQUÉE : ' . $spamResult['reason']);
                return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $id]) . '#comments');
            }
            $file = $form->get('image')->getData();

            // S'assurer qu'il y a au moins du texte ou une image
            if (empty($contenu) && !$file && !$comment->getImage()) {
                $errorMsg = 'Votre commentaire doit contenir au moins du texte ou une image.';
                $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'errors' => [$errorMsg]], 400);
                }
                $this->addFlash('error', $errorMsg);
                return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $id]) . '#comments');
            }

            if ($contenu && $this->moderator->isToxic($contenu)) {
                $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'errors' => ['⚠️ CONTENU INAPPROPRIÉ ! Votre commentaire contient des insultes ou du langage offensant. Respectez la communauté.']], 400);
                }
                $this->addFlash('warning', '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Votre commentaire contient des propos offensants, des insultes ou du langage vulgaire. Veuillez modifier votre message et respecter la communauté.');
                return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $id]) . '#comments');
            }

            // Gestion de l'image ou AUDIO du commentaire + modération IA locale
            $commentImageWarning = null;
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $uploadDir = $this->getParameter('uploads_comment_dir');
                    $file->move($uploadDir, $newFilename);
                    $absolutePath = realpath($uploadDir . \DIRECTORY_SEPARATOR . $newFilename) ?: $uploadDir . \DIRECTORY_SEPARATOR . $newFilename;
                    
                    // Check if it's an image before moderation
                    $mimeType = mime_content_type($absolutePath);
                    $isImage = str_starts_with($mimeType, 'image/');

                    if ($isImage) {
                        $result = $this->imageModerator->analyzeImage($absolutePath);
                        if ($result['status'] === 'reject') {
                            @unlink($absolutePath);
                            if (!isset($result['error'])) {
                                // Contenu sensible détecté → on ne sauvegarde pas le commentaire
                                $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
                                if ($isAjax) {
                                    return new JsonResponse(['success' => false, 'errors' => [$result['message'] ?? 'Cette image n\'est pas autorisée.']], 400);
                                }
                                $this->addFlash('warning', $result['message'] ?? 'Cette image n\'est pas autorisée.');
                                return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $id]) . '#comments');
                            }
                            $this->addFlash('warning', $result['message'] ?? 'L\'image n\'a pas pu être vérifiée et n\'a pas été ajoutée. Votre commentaire a été enregistré.');
                        } else {
                            $comment->setImage($newFilename);
                            $comment->setImageSensitivity((!empty($result['warning_message']) || $result['status'] === 'pending_review') ? 'medium' : null);
                            if ($result['status'] === 'pending_review') {
                                $comment->setModerationStatus('pending_review');
                            }
                            if (!empty($result['warning_message'])) {
                                $commentImageWarning = $result['warning_message'];
                                $this->addFlash('warning', $result['warning_message']);
                            }
                        }
                    } else {
                        // It's likely audio or other allowed type -> just save it
                        $comment->setImage($newFilename); 
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                }
            }

            $this->em->persist($comment);
            $this->em->flush();
            $this->addFlash('success', 'Commentaire ajouté.');

            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
            if ($isAjax) {
                $auteur = $comment->getAuteur();
                $csrfTokenManager = $this->container->get('security.csrf.token_manager');
                
                // Calculate relative time manually for the immediate response
                // Since it's just created, it's "à l'instant"
                $timeAgo = "à l'instant";

                $json = [
                    'success' => true,
                    'comment' => [
                        'id' => $comment->getId(),
                        'contenu' => $comment->getContenu() ?? '',
                        'image' => $comment->getImage() ? '/uploads/comments/' . $comment->getImage() : null,
                        'auteur' => $auteur ? ($auteur->getPseudo() ?: $auteur->getUsername()) : 'Anonyme',
                        'dateCommentaire' => $comment->getDateCommentaire()?->format('d/m/Y H:i'),
                        'timeAgo' => $timeAgo,
                        'parentId' => $comment->getParent()?->getId(),
                        'isSubreply' => $comment->getParent() && $comment->getParent()->getParent() !== null,
                        'tokenDelete' => $csrfTokenManager->getToken('delete_comment_' . $comment->getId())->getValue(),
                        'editUrl' => $this->generateUrl('app_forum_comment_edit', ['id' => $comment->getId()]),
                        'deleteUrl' => $this->generateUrl('app_forum_comment_delete', ['id' => $comment->getId()]),
                        'nbLikes' => $comment->getNbLikes(),
                    ],
                ];
                if ($commentImageWarning !== null) {
                    $json['warning_message'] = $commentImageWarning;
                }
                return new JsonResponse($json);
            }
        }
 else {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
            if ($isAjax) {
                return new JsonResponse(['success' => false, 'errors' => $errors], 400);
            }
            foreach ($errors as $err) {
                $this->addFlash('error', $err);
            }
        }
        return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $id]) . '#comments');
    }

    #[Route('/comment/{id}/edit', name: 'app_forum_comment_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function commentEdit(Request $request, int $id): Response
    {
        $comment = $this->commentRepository->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }
        if (!$this->isAuthor($comment->getAuteur())) {
            $this->addFlash('error', 'Seul l\'auteur peut modifier ce commentaire.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $comment->getPost()->getId()]);
        }
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $contenu = $comment->getContenu() ?? '';
            $contenu = trim($contenu);
            
            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';

            if ($contenu && $this->moderator->isToxic($contenu)) {
                $errorMsg = '⚠️ CONTENU INAPPROPRIÉ DÉTECTÉ ! Votre commentaire contient des propos offensants.';
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'errors' => [$errorMsg]], 400);
                }
                $this->addFlash('warning', $errorMsg);
                return $this->render('frontoffice/forum/comment_edit.html.twig', [
                    'comment' => $comment,
                    'form' => $form,
                ]);
            }

            // Gestion de l'image ou AUDIO du commentaire + modération IA locale
            $deleteImage = $request->request->get('delete_image');
            if ($deleteImage) {
                $oldImage = $comment->getImage();
                if ($oldImage) {
                    $uploadDir = $this->getParameter('uploads_comment_dir');
                    @unlink($uploadDir . \DIRECTORY_SEPARATOR . $oldImage);
                    $comment->setImage(null);
                    $comment->setImageSensitivity(null);
                    $comment->setModerationStatus(null);
                }
            }

            $file = $form->get('image')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $uploadDir = $this->getParameter('uploads_comment_dir');
                    $file->move($uploadDir, $newFilename);
                    $absolutePath = realpath($uploadDir . \DIRECTORY_SEPARATOR . $newFilename) ?: $uploadDir . \DIRECTORY_SEPARATOR . $newFilename;
                    
                    // Check if it's an image before moderation
                    $mimeType = mime_content_type($absolutePath);
                    $isImage = str_starts_with($mimeType, 'image/');

                    if ($isImage) {
                        $result = $this->imageModerator->analyzeImage($absolutePath);
                        if ($result['status'] === 'reject') {
                            @unlink($absolutePath);
                            if (!isset($result['error'])) {
                                if ($isAjax) {
                                    return new JsonResponse(['success' => false, 'errors' => [$result['message'] ?? 'Cette image n\'est pas autorisée.']], 400);
                                }
                                $this->addFlash('warning', $result['message'] ?? 'Cette image n\'est pas autorisée.');
                                return $this->render('frontoffice/forum/comment_edit.html.twig', ['comment' => $comment, 'form' => $form]);
                            }
                            $this->addFlash('warning', $result['message'] ?? 'L\'image n\'a pas pu être vérifiée et n\'a pas été ajoutée.');
                        } else {
                            $comment->setImage($newFilename);
                            $comment->setImageSensitivity((!empty($result['warning_message']) || $result['status'] === 'pending_review') ? 'medium' : null);
                            if ($result['status'] === 'pending_review') {
                                $comment->setModerationStatus('pending_review');
                            } else {
                                $comment->setModerationStatus(null);
                            }
                            if (!empty($result['warning_message'])) {
                                $this->addFlash('warning', $result['warning_message']);
                            }
                        }
                    } else {
                         // It's likely audio or other allowed type -> just save it
                        $comment->setImage($newFilename);
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                }
            }

            // Si erreurs ajoutées manuellement
            if ($form->getErrors(true)->count() > 0) {
                 if ($isAjax) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    return new JsonResponse(['success' => false, 'errors' => $errors], 400);
                 }
                 return $this->render('frontoffice/forum/comment_edit.html.twig', [
                    'comment' => $comment,
                    'form' => $form,
                ]);
            }

            $this->em->flush();

            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'comment' => [
                        'id' => $comment->getId(),
                        'contenu' => $comment->getContenu(),
                        'image' => $comment->getImage() ? '/uploads/comments/' . $comment->getImage() : null,
                        'dateCommentaire' => $comment->getDateCommentaire()?->format('d/m/Y H:i'),
                    ]
                ]);
            }

            $this->addFlash('success', 'Commentaire mis à jour.');
            return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $comment->getPost()->getId()]) . '#comments');
        }

        return $this->render('frontoffice/forum/comment_edit.html.twig', [
            'comment' => $comment,
            'form' => $form,
        ]);
    }

    #[Route('/comment/{id}/delete', name: 'app_forum_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function commentDelete(Request $request, int $id): Response
    {
        $comment = $this->commentRepository->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }
        if (!$this->isAuthor($comment->getAuteur())) {
            $this->addFlash('error', 'Seul l\'auteur peut supprimer ce commentaire.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $comment->getPost()->getId()]);
        }
        $postId = $comment->getPost()->getId();
        if ($this->isCsrfTokenValid('delete_comment_' . $id, (string) $request->request->get('_token'))) {
            $this->em->remove($comment);
            $this->em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }
        return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $postId]) . '#comments');
    }

    private function isAuthor(?\App\Entity\User $auteur): bool
    {
        $user = $this->getUser();
        return $user && $auteur && $auteur->getId() === $user->getId();
    }

    #[Route('/comment/{id}/like', name: 'app_forum_comment_like', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function commentLike(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Veuillez vous connecter pour aimer ce commentaire.'], 403);
        }

        $comment = $this->commentRepository->find($id);
        if (!$comment) {
            return new JsonResponse(['error' => 'Commentaire non trouvé.'], 404);
        }

        if ($comment->isLikedBy($user)) {
            $comment->removeLikedBy($user);
            $liked = false;
        } else {
            $comment->addLikedBy($user);
            $liked = true;
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'liked' => $liked,
            'nbLikes' => $comment->getNbLikes()
        ]);
    }

    #[Route('/post/{id}/report', name: 'app_forum_post_report', methods: ['POST'])]
    public function reportPost(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $post = $this->postRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Prevent self-reporting
        if ($post->getAuteur() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas signaler votre propre post.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
        }

        $motif = trim((string)$request->request->get('motif'));
        $description = $request->request->get('description');

        if (empty($motif)) {
            $this->addFlash('error', 'Le motif est obligatoire.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
        }

        $signalement = new \App\Entity\Signalement();
        $signalement->setPost($post);
        $signalement->setReporter($user);
        $signalement->setMotif($motif);
        $signalement->setDescription($description);
        $signalement->setStatus('pending');

        $this->em->persist($signalement);
        $this->em->flush();

        $this->addFlash('success', 'Le post a été signalé aux administrateurs.');

        return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
    }

    /**
     * Debug: test image moderation. GET /forum/test-image-moderation?path=<absolute_path>
     * Or with no path, returns config and how to run the Python script manually.
     */
    #[Route('/test-image-moderation', name: 'app_forum_test_image_moderation', methods: ['GET'])]
    public function testImageModeration(Request $request): JsonResponse
    {
        $path = $request->query->get('path');
        if ($path === null || $path === '') {
            return new JsonResponse([
                'usage' => 'Add ?path=<absolute_path_to_image> to test the AI image moderator.',
                'python_path' => $this->getParameter('ai_moderation_python_path'),
                'script_path' => $this->getParameter('ai_moderation_script_path'),
                'uploads_post_dir' => $this->getParameter('uploads_post_dir'),
            ], 200, ['Content-Type' => 'application/json']);
        }
        $result = $this->imageModerator->analyzeImage($path);
        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/test-moderation', name: 'app_forum_test_moderation', methods: ['GET'])]
    public function testModeration(Request $request): Response
    {
        $text = (string) $request->query->get('text', 'Hello world');
        $debug = method_exists($this->moderator, 'debugModeration')
            ? $this->moderator->debugModeration($text)
            : ['key_set' => false, 'error' => 'Service sans debug', 'api_content' => null, 'toxic' => false];

        $html = '<h2>Test modération (API IA)</h2>';
        $html .= '<p><strong>Texte testé :</strong> ' . htmlspecialchars($text) . '</p>';
        $html .= '<p><strong>Clé API configurée :</strong> ' . ($debug['key_set'] ? 'Oui' : 'Non') . '</p>';
        if (!empty($debug['error'])) {
            $html .= '<p style="color:red;"><strong>Erreur :</strong> ' . htmlspecialchars($debug['error']) . '</p>';
            if (!empty($debug['http_status'])) {
                $html .= '<p style="color:red;"><strong>Code HTTP :</strong> ' . (int) $debug['http_status'] . '</p>';
            }
            if (!empty($debug['api_error_body'])) {
                $html .= '<p style="color:red;"><strong>Réponse API (erreur) :</strong> <pre>' . htmlspecialchars($debug['api_error_body']) . '</pre></p>';
            }
        }
        if (array_key_exists('api_content', $debug) && $debug['api_content'] !== null) {
            $html .= '<p><strong>Réponse API :</strong> "' . htmlspecialchars($debug['api_content']) . '"</p>';
        }
        $html .= '<p><strong>Résultat (toxique) :</strong> ' . ($debug['toxic'] ? 'OUI' : 'NON') . '</p>';
        $html .= '<p><small>Ajoutez <code>?text=votre+texte</code> dans l’URL pour tester un autre texte.</small></p>';

        return new Response($html);
    }
    #[Route('/translate', name: 'app_forum_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? '';
        $targetLanguage = $data['targetLanguage'] ?? 'English';

        if (empty(trim($text))) {
            return new JsonResponse(['error' => 'Texte vide'], 400);
        }

        $translated = $this->smartWriting->translateText($text, $targetLanguage);

        return new JsonResponse([
            'success' => true,
            'translatedText' => $translated
        ]);
    }

    #[Route('/api/rubrique/generate-image', name: 'app_forum_rubrique_generate_image', methods: ['POST'])]
    public function generateRubriqueImage(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? null;

        if (empty(trim($name))) {
            return new JsonResponse(['error' => 'Le nom de la rubrique est requis'], 400);
        }

        // Generate image using Hugging Face
        $result = $this->imageGenerator->generateImageForRubrique($name, $description);

        if ($result['success']) {
            return new JsonResponse([
                'success' => true,
                'filename' => $result['filename'],
                'url' => '/uploads/rubriques/' . $result['filename']
            ]);
        } else {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'] ?? 'Erreur lors de la génération de l\'image'
            ], 500);
        }
    }
}
