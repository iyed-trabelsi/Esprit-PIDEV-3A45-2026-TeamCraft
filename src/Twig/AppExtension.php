<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('ago', [$this, 'timeAgo']),
        ];
    }

    public function timeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $interval = $now->diff($date);

        if ($interval->y > 0) {
            return $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
        }
        if ($interval->m > 0) {
            return $interval->m . ' mois';
        }
        if ($interval->d > 0) {
            return $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
        }
        if ($interval->h > 0) {
            return $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
        }
        if ($interval->i > 0) {
            return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        }
        
        return 'à l\'instant';
    }
}
