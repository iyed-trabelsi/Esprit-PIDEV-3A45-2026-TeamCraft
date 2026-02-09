<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Player;
use App\Entity\Manager;
use App\Form\RegistrationFormType;
use App\Security\AppCustomAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. On récupère le mot de passe en clair depuis le champ non-mappé
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // 2. On encode le mot de passe
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // 3. FIX : On remplit les champs obligatoires qui ne sont pas dans le formulaire
            $user->setPseudo($user->getUsername()); // Utilise le username comme pseudo
            $user->setName($user->getUsername());   // Utilise le username comme nom (si requis)
            $user->setIsActive(true);               // Active le compte par défaut

            // 4. Logique Double Rôle
            $userType = $user->getUserType();

            if ($userType === 'player') {
                $player = new Player();
                $player->setUser($user);
                $entityManager->persist($player);
            } elseif ($userType === 'team' || $userType === 'manager') {
                // Vérifie si ton FormType envoie 'team' ou 'manager'
                $manager = new Manager();
                $manager->setUser($user);
                $entityManager->persist($manager);
            }

            // 5. Sauvegarde finale
            $entityManager->persist($user);
            $entityManager->flush();

            // 6. Connexion automatique
            return $security->login($user, AppCustomAuthenticator::class, 'main');
        }

        return $this->render('frontoffice/registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}