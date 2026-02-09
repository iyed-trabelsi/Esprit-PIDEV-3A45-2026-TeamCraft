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
    ) {
    }

    #[Route('', name: 'app_forum', methods: ['GET'])]
    public function index(): Response
    {
        $rubriques = $this->rubriqueRepository->findAllActive();
        $mesRubriques = [];
        if ($this->getUser()) {
            $mesRubriques = $this->rubriqueRepository->findByAuteur($this->getUser());
        }
        return $this->render('frontoffice/forum/index.html.twig', [
            'rubriques' => $rubriques,
            'mes_rubriques' => $mesRubriques,
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
        $posts = $this->postRepository->findByRubrique($rubrique);
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
        $form = $this->createForm(RubriqueType::class, $rubrique);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
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
        $form = $this->createForm(RubriqueType::class, $rubrique);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Rubrique mise à jour.');
            return $this->redirectToRoute('app_forum_rubrique_show', ['id' => $id]);
        }
        return $this->render('frontoffice/forum/rubrique_form.html.twig', [
            'rubrique' => $rubrique,
            'form' => $form,
            'is_edit' => true,
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
        if ($post->getStatut() !== 'published') {
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
        if ($post->getStatut() !== 'published') {
            throw $this->createNotFoundException('Ce post n\'est pas accessible.');
        }
        if (!$this->isAuthor($post->getAuteur())) {
            $this->addFlash('error', 'Seul l\'auteur peut modifier ce post.');
            return $this->redirectToRoute('app_forum_post_show', ['id' => $id]);
        }
        $form = $this->createForm(PostType::class, $post, ['with_rubrique' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
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
        if ($post->getStatut() !== 'published') {
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
}
