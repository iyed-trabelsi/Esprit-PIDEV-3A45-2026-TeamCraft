<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Doctrine\ORM\EntityManagerInterface;

class SecurityController extends AbstractController
{
    #[Route('/login/face-id', name: 'app_face_id')]
    public function faceId(): Response
    {
        // On vérifie quand même que seul l'admin connecté peut être ici
        if (!$this->getUser() || !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('frontoffice/security/face_id.html.twig');
    }
    
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('frontoffice/security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error
        ]);
    }

    #[Route(path: '/verify-security', name: 'app_verify_security')]
    public function verify(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        //hedheya men aandi : si pas d'utilisateur ou utilisateur bloqué, on dégage direct
        if (!$user || !$user->isActive()) {
        $request->getSession()->invalidate();
        $this->addFlash('danger', 'Votre compte a été suspendu.');
        return $this->redirectToRoute('app_login');
        }
        // Sécurité : si pas de code attendu, on dégage
        if (!$user || !$request->getSession()->get('2fa_required')) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $submittedCode = $request->request->get('code');

            if ($submittedCode === $user->getSecurityCode()) {
                // Code correct : on nettoie
                $user->setSecurityCode(null);
                $request->getSession()->remove('2fa_required');
                $entityManager->flush();

                return $this->redirectToRoute('app_home');
            }

            $this->addFlash('danger', 'Le code de sécurité est incorrect. Veuillez réessayer.');
        }

        // ATTENTION : vérifie bien que ton fichier est dans ce dossier
        return $this->render('frontoffice/security/verify.html.twig');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank.');
    }
}