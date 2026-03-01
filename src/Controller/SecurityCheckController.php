<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityCheckController extends AbstractController
{
    #[Route('/verify-security', name: 'app_verify_security')]
    public function verify(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        if ($request->isMethod('POST')) {
            $submittedCode = $request->request->get('code');
            
            if ($submittedCode == $user->getSecurityCode()) {
                $request->getSession()->remove('2fa_required');
                $user->setSecurityCode(null); // On vide le code après usage
                // On flush ici si nécessaire via EntityManager
                return $this->redirectToRoute('app_home'); // Ta page d'accueil
            }
            $this->addFlash('danger', 'Code invalide !');
        }

        return $this->render('security/verify.html.twig');
    }
}