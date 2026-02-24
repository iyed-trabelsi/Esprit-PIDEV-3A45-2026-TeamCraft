<?php

namespace App\Service;

use App\Entity\Postulation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

/**
 * Service responsable de l'envoi des emails de notification
 * liés aux candidatures (acceptation / refus).
 */
class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Envoie un email au joueur pour l'informer du statut de sa candidature.
     *
     * @param Postulation $postulation La candidature concernée
     * @param string      $status      'accepted' ou 'refused'
     *
     * @throws \Exception En cas d'erreur d'envoi
     */
    public function sendApplicationStatusEmail(Postulation $postulation, string $status): void
    {
        $user = $postulation->getUser();
        $offer = $postulation->getOffer();
        $team = $offer->getTeam();

        // Récupération des informations nécessaires
        $playerName = $user->getPseudo() ?? $user->getUsername() ?? $user->getName() ?? 'Joueur';
        $teamName = $team->getName();
        $offerTitle = $offer->getTitle();
        $game = $offer->getGame();
        $role = $offer->getRole();

        // Génération de l'URL absolue vers la page de gestion de l'équipe
        $teamUrl = $this->urlGenerator->generate(
            'team_manage',
            ['id' => $team->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // URL vers la liste des offres disponibles (pour le cas "refusé")
        $offersUrl = $this->urlGenerator->generate(
            'app_offres',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Rendu du template Twig de l'email
        $htmlContent = $this->twig->render('emails/application_status.html.twig', [
            'playerName' => $playerName,
            'teamName' => $teamName,
            'offerTitle' => $offerTitle,
            'game' => $game,
            'role' => $role,
            'status' => $status,
            'teamUrl' => $teamUrl,
            'offersUrl' => $offersUrl,
        ]);

        // Définition du sujet selon le statut (anglais)
        $subject = match ($status) {
            'accepted' => "🎉 Welcome to {$teamName}! Your application has been accepted",
            'refused' => "Update regarding your application to {$teamName}",
            default => "TeamCraft - Application status update",
        };
        // Construction et envoi de l'email
        $email = (new Email())
            ->from(new Address('barbouch4@gmail.com', 'TeamCraft'))
            ->to(new Address($user->getEmail(), $playerName))
            ->subject($subject)
            ->html($htmlContent);

        try {
            $this->mailer->send($email);
            $this->logger->info('Email de candidature envoyé', [
                'to' => $user->getEmail(),
                'status' => $status,
                'team' => $teamName,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de candidature', [
                'to' => $user->getEmail(),
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            // On re-lance l'exception pour que le contrôleur puisse la gérer
            throw $e;
        }
    }
}
