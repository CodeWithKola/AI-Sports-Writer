<?php

namespace AiSprtsW\Services;

use AiSprtsW\Utilities\Logger;

/**
 * Service class for interacting with OpenAI API.
 */
class OpenAiService
{
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const OPTION_API_NAME = 'aisprtsw_api_settings';



    /**
     * Generates content using the OpenAI API.
     *
     * @param string $apiKey The OpenAI API key.
     * @param string $prompt The prompt for content generation.
     * @return string|null The generated content or null on failure.
     */
    public function generateContent(string $apiKey, string $prompt): ?string
    {
        $options = get_option(self::OPTION_API_NAME);
        $model = $options['openai_model'] ?? 'gpt-3.5-turbo';


        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional sports content writer.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ])
        ];

        $response = wp_remote_post(self::API_ENDPOINT, $args);

        if (is_wp_error($response)) {
            Logger::log('OpenAI API request failed: ' . $response->get_error_message(), 'error');
            return null;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        if ($httpCode !== 200) {
            Logger::log("OpenAI API request failed with HTTP code {$httpCode}.", 'error');
            return null;
        }

        $responseBody = wp_remote_retrieve_body($response);
        $responseData = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('Failed to decode JSON response from OpenAI API.', 'error');
            return null;
        }

        return $responseData['choices'][0]['message']['content'] ?? null;
    }



    /**
     * Generates a title for a blog post using the OpenAI API.
     *
     * @param string $content The blog post content.
     * @param string $apiKey The OpenAI API key.
     * @return string The generated title or an empty string on failure.
     */
    public function generateTitle(string $content, string $apiKey): string
    {
        if (empty($apiKey)) {
            Logger::log('OpenAI API key is missing for title generation.', 'error');
            return '';
        }

        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a title generator. Create a concise, engaging title for a blog post based on the given content.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Generate a compelling title for this blog post content:\n\n" . $content
                    ]
                ]
            ])
        ];

        $response = wp_remote_post(self::API_ENDPOINT, $args);


        if (is_wp_error($response)) {
            Logger::log('OpenAI title generation request failed: ' . $response->get_error_message(), 'error');
            return '';
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        if ($httpCode !== 200) {
            Logger::log('OpenAI title generation failed with HTTP code ' . $httpCode, 'error');
            return '';
        }


        $responseBody = wp_remote_retrieve_body($response);
        $responseData = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('Failed to parse JSON response from OpenAI for title generation.', 'error');
            return '';
        }


        $generatedTitle = $responseData['choices'][0]['message']['content'] ?? '';
        return trim($generatedTitle, '"'); // Remove any extra quotes
    }



    /**
     * Generate an image using DALL-E API.
     *
     * @param string $openai_api_key OpenAI API key.
     * @param array $game Game data (team names).
     * @return string|null URL of the generated image or null on failure.
     */
    public function generateDalleImage($openai_api_key, $game)
    {
        // Validate API key
        if (empty($openai_api_key)) {
            Logger::log('OpenAI API key is missing for DALL-E image generation', 'ERROR');
            return null;
        }

        // Get user settings for image generation
        $post_options = get_option('aisprtsw_post_settings');
        $image_size = $post_options['dalle_image_size'] ?? '1024x1024';
        $image_quality = $post_options['dalle_image_quality'] ?? 'standard';

        // Create image prompt based on size for better composition
        $imagePrompt = isset($game['home'], $game['away'])
            ? $this->createContextualPrompt($game, $image_size)
            : $this->createFallbackPrompt($image_size);

        $url = 'https://api.openai.com/v1/images/generations';

        // Build request body
        $request_body = [
            'model' => 'dall-e-3',
            'prompt' => $imagePrompt,
            'n' => 1,
            'size' => $image_size
        ];

        // Add quality parameter if HD is selected
        if ($image_quality === 'hd') {
            $request_body['quality'] = 'hd';
        }

        $args = [
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $openai_api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($request_body)
        ];

        $response = wp_remote_post($url, $args);

        // Detailed error handling
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            Logger::log('DALL-E Request Error: ' . $error_message, 'ERROR');
            return null;
        }

        // Check HTTP response code
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            Logger::log('DALL-E HTTP Error Code: ' . $http_code, 'ERROR');
            Logger::log('DALL-E Response Body: ' . $response_body);
            return null;
        }

        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        // Validate response data
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('JSON Decode Error: ' . json_last_error_msg(), 'ERROR');
            Logger::log('Raw Response: ' . $response_body);
            return null;
        }

        // Check for image URL
        if (isset($response_data['data'][0]['url'])) {
            return $response_data['data'][0]['url'];
        }

        // Log if no image URL found
        Logger::log('No image URL found in DALL-E response');
        return null;
    }

    /**
     * Create contextual prompt based on game data and image size
     *
     * @param array $game Game data
     * @param string $size Image size
     * @return string Optimized prompt
     */
    private function createContextualPrompt($game, $size)
    {
        $home_team = addslashes(sanitize_text_field($game['home']));
        $away_team = addslashes(sanitize_text_field($game['away']));

        switch ($size) {
            case '1792x1024': // Landscape - Stadium scenes
                return sprintf(
                    'Wide panoramic view of a football stadium during %s vs %s match, dynamic crowd atmosphere, team colors prominently displayed, professional sports photography style, vibrant lighting',
                    $home_team,
                    $away_team
                );
            
            case '1024x1792': // Portrait - Social media
                return sprintf(
                    'Vertical composition football match poster for %s vs %s, bold team logos, dynamic player silhouettes, modern graphic design, social media optimized layout',
                    $home_team,
                    $away_team
                );
            
            case '1024x1024': // Square - Classic format
            default:
                return sprintf(
                    'Dynamic football stadium scene with %s and %s jerseys, vibrant sports photography style, balanced composition',
                    $home_team,
                    $away_team
                );
        }
    }

    /**
     * Create fallback prompt when game data is not available
     *
     * @param string $size Image size
     * @return string Fallback prompt
     */
    private function createFallbackPrompt($size)
    {
        switch ($size) {
            case '1792x1024': // Landscape
                return 'Wide panoramic football stadium view with dramatic lighting, crowd atmosphere, professional sports photography';
            
            case '1024x1792': // Portrait
                return 'Vertical football match preview poster with dynamic design, modern sports graphics, social media format';
            
            case '1024x1024': // Square
            default:
                return 'Football match preview poster with stadium and players, balanced composition';
        }
    }
}
