<?php

namespace App\EventSubscriber;

use App\Entity\LoginHistory;
use App\Service\SecurityScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginSubscriber implements EventSubscriberInterface
{
    private $em;
    private $scorer;
    private $urlGenerator;

    public function __construct(EntityManagerInterface $em, SecurityScorer $scorer, UrlGeneratorInterface $urlGenerator)
    {
        $this->em = $em;
        $this->scorer = $scorer;
        $this->urlGenerator = $urlGenerator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // On met une priorité très haute (20) pour passer avant la redirection standard
            LoginSuccessEvent::class => ['onLoginSuccess', 20],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        $city = 'Tunis'; // On force pour le test
        $analysis = $this->scorer->getRiskScore($user, $request->getClientIp(), $city, $request->headers->get('User-Agent'));

        // Log de l'historique
        $lh = new LoginHistory();
        $lh->setUser($user);
        $lh->setIpAdress($request->getClientIp());
        $lh->setUserAgent($request->headers->get('User-Agent'));
        $lh->setCreatedAt(new \DateTimeImmutable());
        $lh->setCity($city);
        $this->em->persist($lh);

        if ($analysis['score'] >= 50) {
            $code = (string)random_int(100000, 999999);
            $user->setSecurityCode($code);
            $request->getSession()->set('2fa_required', true);
            $this->em->flush();

            // On intercepte et on change la destination
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_verify_security')));
        } else {
            $this->em->flush();
        }
    }
}