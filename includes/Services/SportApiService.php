<?php

namespace AiSportsWriter\Services;

use AiSportsWriter\Utilities\Logger;

class SportApiService
{
    private const API_BASE_URL = 'https://app.scalesp.com/api/v1/football';

    /**
     * Fetches football regions from the API.
     *
     * @param string $apiKey The API key used for authorization.
     * @return array The array of regions fetched from the API.
     */
    public function fetchFootballRegions(string $apiKey): array
    {
        return $this->makeApiRequest('/regions', $apiKey);
    }


    /**
     * Fetches game statistics for a specific match.
     *
     * @param string $apiKey The API key used for authorization.
     * @param string $matchCode The unique identifier for the match.
     * @return array|null An associative array containing the stats, or null if an error occurs.
     */
    public function fetchGameStatistics(string $apiKey, string $matchCode): ?array
    {
        $endpoints = [
            'home_matches' => "/stats/{$matchCode}/home-matches",
            'away_matches' => "/stats/{$matchCode}/away-matches",
            'head_to_head' => "/stats/{$matchCode}/head-to-head",
        ];

        $allStats = [];
        foreach ($endpoints as $key => $endpoint) {
            $response = $this->makeApiRequest($endpoint, $apiKey);
            if ($response === null || isset($response['error'])) {
                return null;
            }
            $allStats[$key] = $response['data'] ?? [];
        }

        return $allStats;
    }

    /**
     * Makes a request to the API and returns the response data.
     *
     * @param string $endpoint The API endpoint to fetch data from.
     * @param string $apiKey The API key used for authorization.
     * @return array|null The decoded JSON response from the API, or null if an error occurs.
     */
    private function makeApiRequest(string $endpoint, string $apiKey): ?array
    {
        $url = self::API_BASE_URL . $endpoint;

        $args = [
            'method'  => 'GET',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            Logger::log("API request failed for $endpoint: " . $response->get_error_message(), 'error');
            return null;
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        if ($httpCode !== 200) {
            Logger::log("API request failed for $endpoint with HTTP code $httpCode", 'error');
            return null;
        }

        $responseBody = wp_remote_retrieve_body($response);
        $decodedResponse = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log("Failed to decode JSON response for $endpoint", 'error');
            return null;
        }

        return $decodedResponse;
    }

    /**
     * Fetches the upcoming games from the API.
     *
     * @param string $apiKey The API key used for authorization.
     * @return array|null The array of upcoming games fetched from the API, or null if an error occurs.
     */
    public function fetchUpcomingEndpoint(string $apiKey): ?array
    {
        return $this->makeApiRequest('/games', $apiKey);
    }
}
