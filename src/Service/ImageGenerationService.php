<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageGenerationService
{
    // Using FLUX.1-schnell via the new Router endpoint - much more stable
    private const HF_API_URL = 'https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-schnell';
    private const TIMEOUT = 90; // Flux can take a bit longer for the first request if model is cold
    
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $huggingfaceApiKey,
        private LoggerInterface $logger,
        private string $uploadsRubriqueDir
    ) {
    }

    /**
     * Generate an image based on rubrique name and description
     * 
     * @param string $name Rubrique name
     * @param string|null $description Rubrique description
     * @return array{success: bool, filename: string|null, error: string|null}
     */
    public function generateImageForRubrique(string $name, ?string $description): array
    {
        try {
            // Create an optimized prompt from name and description
            $prompt = $this->createPrompt($name, $description);
            
            $this->logger->info('Generating image for rubrique', [
                'name' => $name,
                'prompt' => $prompt
            ]);

            // Call Hugging Face API
            $response = $this->httpClient->request('POST', self::HF_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingfaceApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $prompt,
                    'options' => [
                        'wait_for_model' => true,
                    ],
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                $this->logger->error('Hugging Face API error', [
                    'status' => $statusCode,
                    'response' => $response->getContent(false)
                ]);
                return [
                    'success' => false,
                    'filename' => null,
                    'error' => 'API returned status ' . $statusCode
                ];
            }

            // Get image binary data
            $imageData = $response->getContent();
            
            // Generate unique filename
            $filename = 'rubrique-' . uniqid() . '.png';
            $filepath = $this->uploadsRubriqueDir . DIRECTORY_SEPARATOR . $filename;

            // Ensure directory exists
            if (!is_dir($this->uploadsRubriqueDir)) {
                mkdir($this->uploadsRubriqueDir, 0777, true);
            }

            // Save image
            file_put_contents($filepath, $imageData);

            $this->logger->info('Image generated successfully', [
                'filename' => $filename,
                'size' => strlen($imageData)
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->logger->error('Image generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'filename' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create an optimized prompt for image generation
     */
    private function createPrompt(string $name, ?string $description): string
    {
        // Base prompt for professional logo/icon style
        $basePrompt = "Minimalist professional gaming logo, vector style, flat design, clean lines, mascot or iconic emblem, high contrast, solid background, web 3.0 aesthetic, centered, 8k resolution, high quality";
        
        // Combine name and description
        $content = $name;
        if ($description) {
            $content .= ': ' . $description;
        }
        
        // Create final prompt
        $prompt = "A professional logo for a forum section named: $content. Style: $basePrompt";
        
        // Limit prompt length (Flux can handle much longer prompts than old SD)
        if (strlen($prompt) > 500) {
            $prompt = substr($prompt, 0, 497) . '...';
        }
        
        return $prompt;
    }
}
