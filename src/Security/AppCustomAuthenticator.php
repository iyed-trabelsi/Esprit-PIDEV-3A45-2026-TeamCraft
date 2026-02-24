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
        $user = $token->getUser();

        // 1. Vérification du statut du compte
        if (!$user->isActive()) {
            $request->getSession()->invalidate();
            $request->getSession()->getFlashBag()->add('danger', 'Votre compte est suspendu.');
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        // 2. Historique et Analyse de risque (IA)
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');
        $city = 'Paris'; // Ville de test ou via API GeoIP

        $analysis = $this->securityScorer->getRiskScore($user, $ip, $city, $userAgent);

        $history = new LoginHistory();
        $history->setUser($user);
        $history->setIpAdress($ip);
        $history->setUserAgent($userAgent);
        $history->setCity($city);
        $history->setCreatedAt(new \DateTimeImmutable());
        $history->setRiskScore($analysis['score']);
        
        $this->entityManager->persist($history);

        // 3. CAS SPÉCIFIQUE : ADMIN -> Redirection vers FACE ID
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->entityManager->flush();
            return new RedirectResponse($this->urlGenerator->generate('app_face_id'));
        }

        // 4. Analyse Adaptive (2FA par email si score élevé pour users non-admin)
        if ($analysis['score'] >= 50) {
            $code = (string)random_int(100000, 999999);
            $user->setSecurityCode($code);
            $this->entityManager->flush();

            $emailMessage = (new Email())
                ->from('security@teamcraft.com')
                ->to($user->getEmail())
                ->subject('Code de sécurité TeamCraft')
                ->html("<p>Votre code de vérification est : <strong>$code</strong></p>");

            $this->mailer->send($emailMessage);

            $request->getSession()->set('2fa_required', true);
            return new RedirectResponse($this->urlGenerator->generate('app_verify_security'));
        }

        // 5. Redirection standard (Home)
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