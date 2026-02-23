<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class SteamService
{
    private const STEAM_API_BASE = 'https://api.steampowered.com';
    private const CS2_APP_ID = '730'; // CS:GO/CS2 App ID (they share the same ID)

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $steamApiKey
    ) {
    }

    /**
     * Validate Steam ID format (64-bit Steam ID)
     */
    public function validateSteamId(string $steamId): bool
    {
        // Steam ID should be a 17-digit number
        return preg_match('/^[0-9]{17}$/', $steamId) === 1;
    }

    /**
     * Get player summary information
     */
    public function getPlayerSummary(string $steamId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::STEAM_API_BASE . '/ISteamUser/GetPlayerSummaries/v0002/', [
                'query' => [
                    'key' => $this->steamApiKey,
                    'steamids' => $steamId,
                ],
            ]);

            $data = $response->toArray();

            $this->logger->info('Steam API GetPlayerSummaries response', ['data' => $data]);

            if (isset($data['response']['players'][0])) {
                return $data['response']['players'][0];
            }

            $this->logger->warning('No player found for Steam ID', ['steamId' => $steamId]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Steam API error (GetPlayerSummaries): ' . $e->getMessage(), [
                'steamId' => $steamId,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Get CS2 user stats
     */
    public function getUserStats(string $steamId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::STEAM_API_BASE . '/ISteamUserStats/GetUserStatsForGame/v0002/', [
                'query' => [
                    'appid' => self::CS2_APP_ID,
                    'key' => $this->steamApiKey,
                    'steamid' => $steamId,
                ],
            ]);

            $data = $response->toArray();

            $this->logger->info('Steam API GetUserStatsForGame response', [
                'steamId' => $steamId,
                'hasStats' => isset($data['playerstats']['stats']),
                'statsCount' => isset($data['playerstats']['stats']) ? count($data['playerstats']['stats']) : 0
            ]);

            if (!isset($data['playerstats']['stats'])) {
                $this->logger->warning('No stats found in response', ['response' => $data]);
                return null;
            }

            return $this->parseCS2Stats($data['playerstats']['stats']);
        } catch (\Exception $e) {
            $this->logger->error('Steam API error (GetUserStatsForGame): ' . $e->getMessage(), [
                'steamId' => $steamId,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Parse CS2 stats from Steam API response
     */
    private function parseCS2Stats(array $stats): array
    {
        // Log all available stat names for debugging
        $availableStats = array_map(fn($stat) => $stat['name'] ?? 'unknown', $stats);
        $this->logger->info('Available CS2 stats', ['stats' => $availableStats]);

        // Create a map of stat name => value for easier access
        $statsData = [];
        foreach ($stats as $stat) {
            $statName = $stat['name'] ?? null;
            $statValue = $stat['value'] ?? 0;
            if ($statName) {
                $statsData[$statName] = $statValue;
            }
        }

        // CS:GO/CS2 uses rounds, not matches for wins
        // total_wins_* are wins on specific maps, we need to sum them or use total_rounds_won
        $totalRoundsWon = $statsData['total_rounds_won'] ?? 0;
        $totalRoundsPlayed = $statsData['total_rounds_played'] ?? 0;

        // For matches, we can use total_matches_won if available, otherwise estimate
        $totalMatchesWon = $statsData['total_matches_won'] ?? 0;
        $totalMatchesPlayed = $statsData['total_matches_played'] ?? 0;

        // If no match data, try to estimate from rounds (typical match is ~16-30 rounds)
        if ($totalMatchesPlayed === 0 && $totalRoundsPlayed > 0) {
            $totalMatchesPlayed = (int) ($totalRoundsPlayed / 23); // Average rounds per match
            $totalMatchesWon = (int) ($totalRoundsWon / 23);
        }

        $parsedStats = [
            'kills' => $statsData['total_kills'] ?? 0,
            'deaths' => $statsData['total_deaths'] ?? 0,
            'wins' => $totalMatchesWon,
            'totalMatches' => $totalMatchesPlayed,
            'totalPlaytime' => $statsData['total_time_played'] ?? 0,
            'headshots' => $statsData['total_kills_headshot'] ?? 0,
            'assists' => $statsData['total_mvps'] ?? 0,
            'losses' => 0,
        ];

        $this->logger->info('Parsed CS2 stats', [
            'parsedStats' => $parsedStats,
            'roundsWon' => $totalRoundsWon,
            'roundsPlayed' => $totalRoundsPlayed
        ]);

        // Calculate playtime in hours
        if ($parsedStats['totalPlaytime'] > 0) {
            $parsedStats['totalPlaytime'] = (int) ($parsedStats['totalPlaytime'] / 3600);
        }

        // Calculate losses
        if ($parsedStats['totalMatches'] > 0 && $parsedStats['wins'] > 0) {
            $parsedStats['losses'] = max(0, $parsedStats['totalMatches'] - $parsedStats['wins']);
        }

        return $parsedStats;
    }

    /**
     * Get complete CS2 profile data
     */
    public function getCS2Profile(string $steamId): ?array
    {
        if (!$this->validateSteamId($steamId)) {
            $this->logger->warning('Invalid Steam ID format', ['steamId' => $steamId]);
            return null;
        }

        $playerSummary = $this->getPlayerSummary($steamId);
        if (!$playerSummary) {
            $this->logger->warning('Could not fetch player summary', ['steamId' => $steamId]);
            return null;
        }

        $stats = $this->getUserStats($steamId);
        if (!$stats) {
            $this->logger->warning('Could not fetch user stats', ['steamId' => $steamId]);
            return null;
        }

        return array_merge($stats, [
            'steamId' => $steamId,
            'personaname' => $playerSummary['personaname'] ?? 'Unknown',
            'profileurl' => $playerSummary['profileurl'] ?? null,
            'avatar' => $playerSummary['avatarfull'] ?? null,
        ]);
    }
}
