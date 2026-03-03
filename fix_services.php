<?php
/**
 * Fix PHPStan missingType.iterableValue errors in Service files
 * Adds @param/@return @var annotations using PHPDoc
 * Uses targeted string replacements per file
 */

$base = __DIR__ . '/src/Service/';
$fixes = [];

// ─── AiSchedulingService.php ────────────────────────────────────────────────
$f = $base . 'AiSchedulingService.php';
$content = file_get_contents($f);
// Fix untyped properties via constructor injection
$content = preg_replace(
    '/private \$evenementRepo;/',
    'private \App\Repository\EvenementRepository $evenementRepo;',
    $content
);
$content = preg_replace(
    '/private \$httpClient;/',
    'private \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient;',
    $content
);
$content = preg_replace(
    '/private \$logger;/',
    'private \Psr\Log\LoggerInterface $logger;',
    $content
);
$content = preg_replace(
    '/private \$apiKey;/',
    'private string $apiKey;',
    $content
);
// Fix method return types with array
$content = preg_replace(
    '/public function generateOptimalEvent\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function generateOptimalEvent(",
    $content
);
$content = preg_replace(
    '/public function analyzeHistory\(array \$events\)/',
    "/**\n     * @param array<int, mixed> \$events\n     */\n    public function analyzeHistory(array \$events)",
    $content
);
$content = preg_replace(
    '/public function getFallbackEvent\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getFallbackEvent(",
    $content
);
file_put_contents($f, $content);
echo "Fixed: AiSchedulingService.php\n";

// ─── Calculator/Cs2PerformanceCalculator.php ───────────────────────────────
$f = $base . 'Calculator/Cs2PerformanceCalculator.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function calculate\(array \$matches, array \$rankData = \[\]\)/',
    "/**\n     * @param array<int, array<string, mixed>> \$matches\n     * @param array<string, mixed> \$rankData\n     */\n    public function calculate(array \$matches, array \$rankData = [])",
    $content
);
file_put_contents($f, $content);
echo "Fixed: Calculator/Cs2PerformanceCalculator.php\n";

// ─── Calculator/GamePerformanceCalculatorInterface.php ─────────────────────
$f = $base . 'Calculator/GamePerformanceCalculatorInterface.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function calculate\(array \$matches, array \$rankData = \[\]\)/',
    "/**\n     * @param array<int, array<string, mixed>> \$matches\n     * @param array<string, mixed> \$rankData\n     */\n    public function calculate(array \$matches, array \$rankData = [])",
    $content
);
file_put_contents($f, $content);
echo "Fixed: Calculator/GamePerformanceCalculatorInterface.php\n";

// ─── Calculator/LolPerformanceCalculator.php ───────────────────────────────
$f = $base . 'Calculator/LolPerformanceCalculator.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function calculate\(array \$matches, array \$rankData = \[\]\)/',
    "/**\n     * @param array<int, array<string, mixed>> \$matches\n     * @param array<string, mixed> \$rankData\n     */\n    public function calculate(array \$matches, array \$rankData = [])",
    $content
);
file_put_contents($f, $content);
echo "Fixed: Calculator/LolPerformanceCalculator.php\n";

// ─── Calculator/ValorantPerformanceCalculator.php ──────────────────────────
$f = $base . 'Calculator/ValorantPerformanceCalculator.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function calculate\(array \$matches, array \$rankData = \[\]\)/',
    "/**\n     * @param array<int, array<string, mixed>> \$matches\n     * @param array<string, mixed> \$rankData\n     */\n    public function calculate(array \$matches, array \$rankData = [])",
    $content
);
file_put_contents($f, $content);
echo "Fixed: Calculator/ValorantPerformanceCalculator.php\n";

// ─── ContentModeratorService.php ───────────────────────────────────────────
$f = $base . 'ContentModeratorService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function debugModeration\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function debugModeration(",
    $content
);
file_put_contents($f, $content);
echo "Fixed: ContentModeratorService.php\n";

// ─── GamerCvService.php ────────────────────────────────────────────────────
$f = $base . 'GamerCvService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function buildCvData\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function buildCvData(",
    $content
);
$content = preg_replace(
    '/public function fetchLatestMatches\(/',
    "/**\n     * @return array<int, mixed>\n     */\n    public function fetchLatestMatches(",
    $content
);
$content = preg_replace(
    '/public function enrichLoLStats\(array \$stats, array \$matchData\)/',
    "/**\n     * @param array<string, mixed> \$stats\n     * @param array<int, mixed> \$matchData\n     * @return array<string, mixed>\n     */\n    public function enrichLoLStats(array \$stats, array \$matchData)",
    $content
);
// Fix nullCoalesce.expr (expression not nullable on left side of ??)
$content = str_replace('$data ?? []', '$data ?: []', $content);
file_put_contents($f, $content);
echo "Fixed: GamerCvService.php\n";

// ─── GeminiService.php ─────────────────────────────────────────────────────
$f = $base . 'GeminiService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function generateResponse\(string \$prompt, array \$history = \[\]\)/',
    "/**\n     * @param array<int, array<string, string>> \$history\n     */\n    public function generateResponse(string \$prompt, array \$history = [])",
    $content
);
file_put_contents($f, $content);
echo "Fixed: GeminiService.php\n";

// ─── ImageModerationService.php ────────────────────────────────────────────
$f = $base . 'ImageModerationService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/private function failResult\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    private function failResult(",
    $content
);
$content = preg_replace(
    '/private function applyModerationRules\(array \$scores\)/',
    "/**\n     * @param array<string, float> \$scores\n     * @return array<string, mixed>\n     */\n    private function applyModerationRules(array \$scores)",
    $content
);
file_put_contents($f, $content);
echo "Fixed: ImageModerationService.php\n";

// ─── LeagueStatsService.php ────────────────────────────────────────────────
$f = $base . 'LeagueStatsService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function calculate\(array \$matches, array \$rankData = \[\]\)/',
    "/**\n     * @param array<int, array<string, mixed>> \$matches\n     * @param array<string, mixed> \$rankData\n     */\n    public function calculate(array \$matches, array \$rankData = [])",
    $content
);
file_put_contents($f, $content);
echo "Fixed: LeagueStatsService.php\n";

// ─── RiotApiService.php ────────────────────────────────────────────────────
$f = $base . 'RiotApiService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function getRequestOptions\(array \$extra = \[\]\): array/',
    "/**\n     * @param array<string, mixed> \$extra\n     * @return array<string, mixed>\n     */\n    public function getRequestOptions(array \$extra = []): array",
    $content
);
$content = preg_replace(
    '/public function getLeagueStats\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getLeagueStats(",
    $content
);
$content = preg_replace(
    '/public function getValorantStats\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getValorantStats(",
    $content
);
$content = preg_replace(
    '/public function getMatchIds\(/',
    "/**\n     * @return array<int, string>\n     */\n    public function getMatchIds(",
    $content
);
$content = preg_replace(
    '/public function getMatchDetails\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getMatchDetails(",
    $content
);
$content = preg_replace(
    '/public function getParticipantStats\(array \$matchData\)/',
    "/**\n     * @param array<string, mixed> \$matchData\n     * @return array<string, mixed>\n     */\n    public function getParticipantStats(array \$matchData)",
    $content
);
file_put_contents($f, $content);
echo "Fixed: RiotApiService.php\n";

// ─── SecurityScorer.php ────────────────────────────────────────────────────
$f = $base . 'SecurityScorer.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/private \$historyRepository;/',
    'private \App\Repository\LoginHistoryRepository $historyRepository;',
    $content
);
$content = preg_replace(
    '/public function getRiskScore\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getRiskScore(",
    $content
);
file_put_contents($f, $content);
echo "Fixed: SecurityScorer.php\n";

// ─── SmartMatchingService.php ──────────────────────────────────────────────
$f = $base . 'SmartMatchingService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function predictMatch\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function predictMatch(",
    $content
);
$content = preg_replace(
    '/private function computeTopPlayersLegacy\(array \$candidates\)/',
    "/**\n     * @param array<int, mixed> \$candidates\n     * @return array<int, mixed>\n     */\n    private function computeTopPlayersLegacy(array \$candidates)",
    $content
);
file_put_contents($f, $content);
echo "Fixed: SmartMatchingService.php\n";

// ─── SmartWritingService.php ───────────────────────────────────────────────
$f = $base . 'SmartWritingService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/private function findBestComment\(/',
    "/**\n     * @return array<string, mixed>|null\n     */\n    private function findBestComment(",
    $content
);
file_put_contents($f, $content);
echo "Fixed: SmartWritingService.php\n";

// ─── SpamDetectorService.php ───────────────────────────────────────────────
$f = $base . 'SpamDetectorService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function checkSpam\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function checkSpam(",
    $content
);
file_put_contents($f, $content);
echo "Fixed: SpamDetectorService.php\n";

// ─── SteamService.php ──────────────────────────────────────────────────────
$f = $base . 'SteamService.php';
$content = file_get_contents($f);
$content = preg_replace(
    '/public function getPlayerSummary\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getPlayerSummary(",
    $content
);
$content = preg_replace(
    '/public function getUserStats\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getUserStats(",
    $content
);
$content = preg_replace(
    '/private function parseCS2Stats\(array \$stats\)/',
    "/**\n     * @param array<string, mixed> \$stats\n     * @return array<string, mixed>\n     */\n    private function parseCS2Stats(array \$stats)",
    $content
);
$content = preg_replace(
    '/public function getCS2Profile\(/',
    "/**\n     * @return array<string, mixed>\n     */\n    public function getCS2Profile(",
    $content
);
file_put_contents($f, $content);
echo "Fixed: SteamService.php\n";

echo "\n✅ All Service files fixed!\n";
