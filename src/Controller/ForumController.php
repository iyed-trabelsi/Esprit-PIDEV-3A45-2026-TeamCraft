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
        private \App\Service\ContentModeratorService $moderator
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
        return $this->render('frontoffice/forum/index.html.twig', [
            'rubriques' => $rubriques,
            'mes_rubriques' => $mesRubriques,
            'currentSort' => $sort,
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

        if ($form->isSubmitted() && $form->isValid() && !$existingRubrique) {
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
        if ($post->getStatut() !== 'published' && !$this->isAuthor($post->getAuteur())) {
            throw $this->createNotFoundException('Ce post n\'est pas accessible.');
        }
        $post->incrementNbVues();
        $this->em->flush();
        $comments = $this->commentRepository->findTopLevelByPost($post);
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

            if ($this->moderator->isToxic($post->getTitre()) || ($post->getContenu() && $this->moderator->isToxic($post->getContenu()))) {
                $this->addFlash('error', 'Votre post contient des propos inappropriés.');
                return $this->render('frontoffice/forum/post_form.html.twig', [
                    'post' => $post,
                    'form' => $form,
                    'is_edit' => false,
                ]);
            }

            $file = $form->get('image')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $file->move($this->getParameter('uploads_post_dir'), $newFilename);
                    $post->setImage($newFilename);
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
                $this->addFlash('error', 'Votre post contient des propos inappropriés.');
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
                    $file->move($this->getParameter('uploads_post_dir'), $newFilename);
                    $post->setImage($newFilename);
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

    #[Route('/post/{id}/comment', name: 'app_forum_comment_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function commentNew(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $post = $this->postRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post introuvable.');
        }
        if ($post->getStatut() !== 'published') {
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
            if ($this->moderator->isToxic($contenu)) {
                $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'errors' => ['Votre commentaire contient des propos inappropriés.']], 400);
                }
                $this->addFlash('error', 'Votre commentaire contient des propos inappropriés.');
                return $this->redirect($this->generateUrl('app_forum_post_show', ['id' => $id]) . '#comments');
            }

            $this->em->persist($comment);
            $this->em->flush();
            $this->addFlash('success', 'Commentaire ajouté.');

            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
            if ($isAjax) {
                $auteur = $comment->getAuteur();
                $csrfTokenManager = $this->container->get('security.csrf.token_manager');
                return new JsonResponse([
                    'success' => true,
                    'comment' => [
                        'id' => $comment->getId(),
                        'contenu' => $comment->getContenu(),
                        'auteur' => $auteur ? ($auteur->getPseudo() ?: $auteur->getUsername()) : 'Anonyme',
                        'dateCommentaire' => $comment->getDateCommentaire()?->format('d/m/Y H:i'),
                        'parentId' => $comment->getParent()?->getId(),
                        'isSubreply' => $comment->getParent() && $comment->getParent()->getParent() !== null,
                        'tokenDelete' => $csrfTokenManager->getToken('delete_comment_' . $comment->getId())->getValue(),
                        'editUrl' => $this->generateUrl('app_forum_comment_edit', ['id' => $comment->getId()]),
                        'deleteUrl' => $this->generateUrl('app_forum_comment_delete', ['id' => $comment->getId()]),
                    ],
                ]);
            }
        } else {
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
            if ($this->moderator->isToxic($contenu)) {
                $this->addFlash('error', 'Votre commentaire contient des propos inappropriés.');
                return $this->render('frontoffice/forum/comment_edit.html.twig', [
                    'comment' => $comment,
                    'form' => $form,
                ]);
            }

            $this->em->flush();
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
}
