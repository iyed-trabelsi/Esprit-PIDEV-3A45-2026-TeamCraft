<?php

namespace App\Security;

use App\Entity\LoginHistory;
use App\Service\SecurityScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    // UN SEUL CONSTRUCTEUR avec toutes les injections
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SecurityScorer $securityScorer,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer 
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_username', '');
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token', '')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        // 1. Vérification immédiate du statut (Compte activé/bloqué)
        if (!$user->isActive()) {
            // On déconnecte de force
            $request->getSession()->invalidate();
            // Optionnel : ajouter un message flash pour expliquer le blocage
            $request->getSession()->getFlashBag()->add('danger', 'Votre compte est suspendu.');
            
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        // 2. Préparation des données pour l'IA
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');
        $city = 'Paris'; // Ville de test

        // 3. Calcul du score via le service SecurityScorer
        $analysis = $this->securityScorer->getRiskScore($user, $ip, $city, $userAgent);

        // 4. Enregistrement systématique dans l'historique
        $history = new LoginHistory();
        $history->setUser($user);
        $history->setIpAdress($ip);
        $history->setUserAgent($userAgent);
        $history->setCity($city);
        $history->setCreatedAt(new \DateTimeImmutable());
        $history->setRiskScore($analysis['score']);
        
        $this->entityManager->persist($history);

        // 5. Analyse du risque (Sécurité Adaptive)
        if ($analysis['score'] >= 50) {
            $code = (string)random_int(100000, 999999);
            $user->setSecurityCode($code);
            
            // On flush ici pour sauver le code et l'historique avant l'envoi
            $this->entityManager->flush();

            // Envoi de l'email via Mailtrap
            $emailMessage = (new Email())
                ->from('security@teamcraft.com')
                ->to($user->getEmail())
                ->subject('Code de sécurité TeamCraft')
                ->html("<h2>Connexion inhabituelle</h2><p>Votre code de vérification est : <strong>$code</strong></p>");

            $this->mailer->send($emailMessage);

            $request->getSession()->set('2fa_required', true);
            return new RedirectResponse($this->urlGenerator->generate('app_verify_security'));
        }

        // 6. Si tout est OK (score faible)
        $this->entityManager->flush();

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}