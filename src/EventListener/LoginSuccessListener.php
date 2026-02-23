<?php

namespace App\EventListener;

use App\Entity\LoginHistory;
use App\Service\SecurityScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginSuccessListener
{
    private $entityManager;
    private $requestStack;
    private $securityScorer;
    private $urlGenerator;

    public function __construct(
        EntityManagerInterface $entityManager, 
        RequestStack $requestStack, 
        SecurityScorer $securityScorer,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->securityScorer = $securityScorer;
        $this->urlGenerator = $urlGenerator;
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if (!$user || !$request) return;

        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');
        
        // Simulation ville (Force une ville différente pour le test !)
        $city = 'Tunis'; 

        $analysis = $this->securityScorer->getRiskScore($user, $ip, $city, $userAgent);
        
        // Enregistrement historique
        $loginHistory = new LoginHistory();
        $loginHistory->setUser($user);
        $loginHistory->setIpAdress($ip);
        $loginHistory->setUserAgent($userAgent);
        $loginHistory->setCreatedAt(new \DateTimeImmutable());
        $loginHistory->setCity($city);
        $this->entityManager->persist($loginHistory);

        // SI RISQUE : ON REDIRIGE
        if ($analysis['score'] >= 50) {
            $code = (string)random_int(100000, 999999);
            $user->setSecurityCode($code);
            $request->getSession()->set('2fa_required', true);
            $this->entityManager->flush();

            $response = new RedirectResponse($this->urlGenerator->generate('app_verify_security'));
            $event->setResponse($response);
        } else {
            $this->entityManager->flush();
        }
        
    }
}