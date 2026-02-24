<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PostRecommendationService
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MODEL = 'openai/gpt-4o-mini';

    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
        private PostRepository $postRepository,
        #[Autowire(param: 'openrouter_api_key')]
        private string $apiKey,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return Post[]
     */
    public function getRecommendations(?User $user, int $limit = 4): array
    {
        if (!$user) {
            return $this->postRepository->findRecentPopularPosts($limit, $user);
        }

        $likedPosts = $this->postRepository->findLikedByUser($user);

        if (empty($likedPosts)) {
            return $this->postRepository->findRecentPopularPosts($limit, $user);
        }

        // Deep analysis: Fetch content and comments for liked posts
        $likedData = [];
        foreach ($likedPosts as $p) {
            $commentsText = "";
            $commentCount = 0;
            foreach ($p->getComments() as $comment) {
                if ($commentCount >= 5) break; // Only top 5 comments
                $commentsText .= strip_tags((string)$comment->getContenu()) . " | ";
                $commentCount++;
            }

            $likedData[] = [
                'id' => $p->getId(),
                'title' => $p->getTitre(),
                'category' => $p->getRubrique()?->getNomRubrique(),
                'content' => substr(strip_tags((string)$p->getContenu()), 0, 500), // First 500 chars
                'comments_snippet' => $commentsText
            ];
        }

        // Get candidate posts (e.g., 30 most recent posts not liked by user)
        $candidates = $this->postRepository->findCandidatesForRecommendation($user, 30);
        
        if (empty($candidates)) {
            return $this->postRepository->findRecentPopularPosts($limit, $user);
        }

        $candidateData = array_map(fn(Post $p) => [
            'id' => $p->getId(),
            'title' => $p->getTitre(),
            'category' => $p->getRubrique()?->getNomRubrique(),
            'snippet' => substr(strip_tags((string)$p->getContenu()), 0, 200)
        ], $candidates);

        $prompt = "You are a highly intelligent recommendation engine for a gaming forum.\n\n" .
                  "Task: Based on the user's liked posts (including their content and community comments), recommend exactly $limit posts from the candidate list that the user would find most interesting.\n\n" .
                  "User's Detailed Interests (Liked Posts):\n" . json_encode($likedData) . "\n\n" .
                  "Candidate Posts (Pool for Selection):\n" . json_encode($candidateData) . "\n\n" .
                  "Return ONLY a JSON array of post IDs, for example: [1, 5, 12]. No explanation, just the array.";

        try {
            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://teamcraft.com',
                    'X-Title' => 'TeamCraft Forum',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a professional Recommendation Engine. Analyze deep patterns in text, sentiment, and topics to match user interests. Output only JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 300,
                ],
            ]);

            $content = $response->toArray()['choices'][0]['message']['content'] ?? '[]';
            
            // Safe parsing of IDs
            if (preg_match('/\[(\s*\d+\s*,?)*\]/', $content, $matches)) {
                $recommendedIds = json_decode($matches[0], true);
            } else {
                $recommendedIds = [];
            }

            if (empty($recommendedIds)) {
                return $this->postRepository->findRecentPopularPosts($limit, $user);
            }

            // Ensure order is preserved as per AI suggestion
            $allPosts = $this->postRepository->findBy(['id' => $recommendedIds]);
            $indexedPosts = [];
            foreach ($allPosts as $p) {
                $indexedPosts[$p->getId()] = $p;
            }

            $sortedPosts = [];
            foreach ($recommendedIds as $id) {
                if (isset($indexedPosts[$id])) {
                    $sortedPosts[] = $indexedPosts[$id];
                }
            }

            return !empty($sortedPosts) ? $sortedPosts : $this->postRepository->findRecentPopularPosts($limit, $user);

        } catch (\Throwable $e) {
            $this->logger?->error('PostRecommendationService deep analysis error: ' . $e->getMessage());
            return $this->postRepository->findRecentPopularPosts($limit, $user);
        }
    }
}
