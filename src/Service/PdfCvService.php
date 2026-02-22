<?php

namespace App\Service;

use App\Entity\RiotStats;
use App\Entity\SteamStats;
use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * PdfCvService
 *
 * Orchestrates fetching CV data via GamerCvService and rendering it as a PDF.
 */
class PdfCvService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GamerCvService $gamerCvService,
        private readonly string $projectDir
    ) {
    }

    /**
     * Build and return raw PDF bytes.
     */
    public function generatePlayerCv(User $user, ?RiotStats $riotStats, ?SteamStats $steamStats, string $game = 'lol'): string
    {
        // ---- Gather data ----
        $cvData = $this->gamerCvService->buildCvData($user, $riotStats, $game, $steamStats);

        // ---- Configure Dompdf ----
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isRemoteEnabled', true);
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('dpi', 150);
        $pdfOptions->setChroot($this->projectDir);

        $dompdf = new Dompdf($pdfOptions);

        // ---- Load background image as base64 ----
        $bgFileName = sprintf('%s_bg.jpg', $game);
        $bgPath = $this->projectDir . '/public/images/cv_backgrounds/' . $bgFileName;
        $base64Bg = '';
        if (file_exists($bgPath)) {
            $type = pathinfo($bgPath, PATHINFO_EXTENSION);
            $data = file_get_contents($bgPath);
            $base64Bg = 'data:image/' . ($type === 'jpg' ? 'jpeg' : $type) . ';base64,' . base64_encode($data);
        }

        $html = $this->twig->render('pdf/player_cv.html.twig', [
            'user' => $user,
            'cvData' => $cvData,
            'game' => $game,
            'steamStats' => $steamStats,
            'projectDir' => $this->projectDir,
            'base64Bg' => $base64Bg,
            'generationDate' => new \DateTime(),
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
