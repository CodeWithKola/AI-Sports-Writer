<?php

namespace AiSportsWriter\Core;

use AiSportsWriter\Services\SportApiService;
use AiSportsWriter\Services\OpenAiService;
use AiSportsWriter\Utilities\Logger;

class ContentGenerator
{
    private const OPTION_POST_NAME = 'ai_sports_writer_post_settings';
    private const OPTION_API_NAME = 'ai_sports_writer_api_settings';

    private SportApiService $sport_api_service;
    private OpenAiService $openai_service;

    public function __construct(SportApiService $sport_api_service, OpenAiService $openai_service)
    {
        $this->sport_api_service = $sport_api_service;
        $this->openai_service = $openai_service;
        $this->setup_cron();
    }

    /**
     * Set up cron jobs for content generation.
     */
    private function setup_cron(): void
    {
        add_filter('cron_schedules', [$this, 'register_custom_intervals']);

        // Schedule events if not already scheduled
        if (!wp_next_scheduled('ai_sports_writer_fetch_cron')) {
            wp_schedule_event(time(), 'every_three_hours', 'ai_sports_writer_fetch_cron');
        }
        if (!wp_next_scheduled('ai_sports_writer_cron')) {
            wp_schedule_event(time(), 'ten_minutes_before_hour', 'ai_sports_writer_cron');
        }

        add_action('ai_sports_writer_fetch_cron', array($this, 'run_upcoming_games'));
        add_action('ai_sports_writer_cron', array($this, 'run_content_generation'));
    }

    /**
     * Register custom cron intervals.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Modified cron schedules.
     */
    public function register_custom_intervals(array $schedules): array
    {
        $schedules['every_three_hours'] = [
            'interval' => 3 * HOUR_IN_SECONDS,
            'display' => __('Every 3 Hours', 'ai-sports-writer')
        ];
        $schedules['ten_minutes_before_hour'] = [
            'interval' => 3600 - 600,  // 1 hour - 10 minutes
            'display' => __('10 Minutes Before Hour', 'ai-sports-writer')
        ];
        return $schedules;
    }


    public function run_upcoming_games(): void
    {
        $api_options = get_option(self::OPTION_API_NAME);
        $api_key = $api_options['sport_api_key'] ?? '';

        if (empty($api_key)) {
            Logger::log('Sport API key not found.', 'error');
            return;
        }

        $games = $this->sport_api_service->fetchUpcomingEndpoint($api_key);
        if ($games) {
            $this->insert_games_into_db($games);
        } else {
            Logger::log('Failed to fetch games data.', 'error');
        }
    }



    private function insert_games_into_db(array $games): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'football_games';
        $two_days_ago = gmdate('Y-m-d H:i:s', strtotime('-2 days'));

        //deleting old games
        $where = [
            'match_datetime' => $two_days_ago
        ];
        $deleted_count = $wpdb->delete($table_name, $where, ['%s']);

        $inserted_count = 0;
        foreach ($games['data'] as $game) {
            // Check for existing match
            $existing_match = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE match_code = %s",
                    $game['match_code']
                )
            );

            if ($existing_match > 0) {
                continue;
            }

            // Prepare data for insertion
            $data = [
                'match_code'    => $game['match_code'] ?? null,
                'region'        => $game['region'] ?? '',
                'team'          => $game['team'] ?? '',
                'home'          => $game['home'] ?? '',
                'away'          => $game['away'] ?? '',
                'match_datetime' => $game['match_datetime'] ?? null,
                'time_zone'     => $game['time_zone'] ?? '',
                'provider'      => $game['provider'] ?? '',
                'odds'          => wp_json_encode($game['odds'] ?? []),
            ];

            // Formats for each field to ensure proper sanitization
            $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

            // Insert the game data
            $result = $wpdb->insert($table_name, $data, $formats);
            if ($result) {
                $inserted_count++;
            }
        }

        Logger::log("Games processed: " . count($games['data']) . ", inserted: $inserted_count");
    }





    public function run_content_generation(): void
    {
        $api_options = get_option(self::OPTION_API_NAME);
        $post_options = get_option(self::OPTION_POST_NAME);


        $openai_api_key = $api_options['openai_api_key'] ?? '';
        $sport_api_key = $api_options['sport_api_key'] ?? '';

        if (empty($openai_api_key) || empty($sport_api_key)) {
            Logger::log('API keys not found.', 'error');
            return;
        }


        $max_games_per_day = (int)($post_options['max_games_per_day'] ?? 5);
        $max_games_per_hour = (int)($post_options['max_games_per_hour'] ?? 5);
        $post_interval = (int)($post_options['post_intervals'] ?? 5);
        $ai_content_prompt = $post_options['ai_content_prompt'] ?? '';

        global $wpdb;
        $table_name = $wpdb->prefix . 'football_games';
        $today = gmdate('Y-m-d');


        $games_processed_today = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}football_games` WHERE processed = 1 AND DATE(processed_started_at) = %s",
                $today
            )
        );

        if ($games_processed_today >= $max_games_per_day) {
            Logger::log("Max games per day reached ($games_processed_today).", 'warning');
            return;
        }



        $current_time = current_time('mysql');
        $games = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}football_games WHERE processed = 0 AND match_datetime > %s LIMIT %d",
                $current_time,
                $max_games_per_hour
            ),
            ARRAY_A
        );

        if (!$games) {
            Logger::log('No unprocessed games found.', 'notice');
            return;
        }


        foreach ($games as $index => $game) {
            try {
                $wpdb->update(
                    $table_name,
                    ['processed' => 1, 'processed_started_at' => current_time('mysql')],
                    ['id' => $game['id']]
                );

                $all_stats = $this->sport_api_service->fetchGameStatistics($sport_api_key, $game['match_code']);

                if ($all_stats === null) {
                    // Handle error, e.g., log, and skip to the next game.
                    Logger::log('Failed to fetch stats for match code: ' . $game['match_code'] . '.', 'error');

                    // Mark game processing as failed or skipped if needed.
                    $wpdb->update(
                        $table_name,
                        ['processed' => 2, 'processed_failed_at' => current_time('mysql')],
                        ['id' => $game['id']]
                    );
                    continue;
                }


                $prompt = $this->prepare_content_prompt($game, $all_stats, $ai_content_prompt);
                $ai_content = $this->openai_service->generateContent($openai_api_key, $prompt);

                $post_time = gmdate('Y-m-d H:i:s', strtotime("+1 hour +" . ($index * $post_interval) . " minutes"));

                if ($ai_content) {
                    $this->schedule_content_post($ai_content, $post_time, $game);
                } else {
                    Logger::log('Failed to generate content.', 'error');
                }
                $wpdb->update($table_name, ['process_completed_at' => current_time('mysql')], ['id' => $game['id']]);
            } catch (\Throwable $th) {
                Logger::log("An error occurred during game processing: " . $th->getMessage(), 'error');

                continue; // Skip to the next game
            }
        }
    }

    /**
     * Prepare content prompt with game details
     *
     * @param array $game Game data
     * @param array $all_stats Game statistics
     * @param string $base_prompt Initial content prompt
     * @return string Prepared content prompt
     */
    private function prepare_content_prompt(
        array $game,
        array $all_stats,
        string $base_prompt
    ): string {

        $home_recent_matches = "";
        $away_recent_matches = "";
        $head_to_head_matches = "";

        if (isset($all_stats["home_matches"]) && is_array($all_stats["home_matches"])) {
            $home_recent_matches = implode(
                "\n",
                array_map(
                    function ($match) {
                        $halfTime = isset($match["home_ht_score"], $match["away_ht_score"])
                            ? ". Half time: " . $match["home_ht_score"] . ":" . $match["away_ht_score"]
                            : "";
                        return "-" . $match["home_team_name"] . " vs " . $match["away_team_name"] .
                            $halfTime .
                            ". Full time: " . $match["home_ft_score"] . ":" . $match["away_ft_score"] .
                            ". Date: " . $match["match_date"];
                    },
                    array_reverse($all_stats["home_matches"]) //display recent history first
                )
            );
        }




        if (isset($all_stats["away_matches"]) && is_array($all_stats["away_matches"])) {
            $away_recent_matches = implode(
                "\n",
                array_map(
                    function ($match) {
                        $halfTime = isset($match["home_ht_score"], $match["away_ht_score"])
                            ? ". Half time: " . $match["home_ht_score"] . ":" . $match["away_ht_score"]
                            : "";
                        return "-" . $match["home_team_name"] . " vs " . $match["away_team_name"] .
                            $halfTime .
                            ". Full time: " . $match["home_ft_score"] . ":" . $match["away_ft_score"] .
                            ". Date: " . $match["match_date"];
                    },
                    array_reverse($all_stats["away_matches"]) //display recent history first
                )
            );
        }



        if (isset($all_stats["head_to_head"]) && is_array($all_stats["head_to_head"])) {
            $head_to_head_matches = implode(
                "\n",
                array_map(
                    function ($match) {
                        $halfTime = isset($match["home_ht_score"], $match["away_ht_score"])
                            ? ". Half time: " . $match["home_ht_score"] . ":" . $match["away_ht_score"]
                            : "";
                        return "-" . $match["home_team_name"] . " vs " . $match["away_team_name"] .
                            $halfTime .
                            ". Full time: " . $match["home_ft_score"] . ":" . $match["away_ft_score"] .
                            ". Date: " . $match["match_date"];
                    },
                    array_reverse($all_stats["head_to_head"]) //display recent history first
                )
            );
        }




        $oddsBreakdown = "";
        $game["odds"] = json_decode($game["odds"], true);

        if (!empty($game["odds"])) {
            $oddsBreakdown = implode("\n", [
                "\nBetting Odds Breakdown:",
                "- Home Win ({$game["home"]}): {$game["odds"]["1"]}",
                "- Away Win ({$game["away"]}): {$game["odds"]["2"]}",
                "- Either team to Win: {$game["odds"]["12"]}",
                "- Draw: {$game["odds"]["x"]}",
                "- Home win or draw: {$game["odds"]["1x"]}",
                "- Away win or draw: {$game["odds"]["x2"]}",
                "- Total goals, less than 3 goals: {$game["odds"]["u_2_5"]}",
                "- Total goals, 3 goals or more: {$game["odds"]["o_2_5"]}",
            ]);
        }


        $sections = [
            "\n\nMatch Details:",
            "- Upcoming Match: {$game["home"]} vs {$game["away"]}",
            "- Home team: {$game["home"]}",
            "- Away team: {$game["away"]}",
            "- Match Date: {$game["match_datetime"]}",
            "- Region: {$game["region"]}",
            "",
            $oddsBreakdown,
            "",
            !empty($home_recent_matches) ? "\nMatch History Analysis:\nHome Team Recent Performance:\n$home_recent_matches" : null,
            !empty($away_recent_matches) ? "\nAway Team Recent Performance:\n$away_recent_matches" : null,
            !empty($head_to_head_matches) ? "\nHead-to-Head History:\n$head_to_head_matches" : null,
        ];

        // Remove null or empty values
        $sections = array_filter($sections);

        // Create the final prompt
        $base_prompt .= implode("\n", $sections);

        return $base_prompt;
    }

    /**
     * Schedules a post with content, title, and featured image, and assigns it a future publish date.
     *
     * @param string $content    The content of the post to be scheduled.
     * @param string $post_time  The scheduled time for the post to be published.
     * @param object $game       Game data that may be used to generate a DALL-E image.
     * 
     * @return int|void          The post ID of the created post or void if an error occurs.
     */
    private function schedule_content_post($content, $post_time, $game)
    {
        $api_options = get_option(self::OPTION_API_NAME);
        $post_options = get_option(self::OPTION_POST_NAME);

        // Validate API keys
        $openai_api_key = $api_options['openai_api_key'] ?? '';
        $sport_api_key = $api_options['sport_api_key'] ?? '';

        if (empty($openai_api_key) || empty($sport_api_key)) {
            Logger::log('Missing API keys for content generation', 'ERROR');
            return;
        }

        $openai_title = $this->openai_service->generateTitle($content, $openai_api_key);

        // If OpenAI title is empty or returns an error, we use the fallback method
        $post_title = !empty($openai_title) ? $openai_title : $this->generate_post_title($content);

        $post_data = [
            'post_title'   => $post_title,
            'post_content' => $content,
            'post_status'  => 'future',
            'post_date'    => $post_time,
            'post_type'    => 'post',
            'post_author'  => isset($post_options['post_author']) ? $post_options['post_author'] : get_current_user_id(),
        ];

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            Logger::log('Failed to insert post', 'ERROR');
            return;
        }

        // Log the successful post creation
        // Logger::log("Post created successfully: ID {$post_id}", 'INFO');

        // Handle categories
        if (isset($post_options['post_category']) && $post_options['post_category'] > 0) {
            wp_set_post_categories($post_id, [$post_options['post_category']], false);
        }

        // Handle featured image
        if ($post_id) {
            $manual_image = isset($post_options['featured_image_url']) ? $post_options['featured_image_url'] : '';
            $dalle_enabled = isset($post_options['dalle_image_generation']) && $post_options['dalle_image_generation'] == 1;

            $featured_image_url = $dalle_image = null;
            if (!empty($manual_image)) {
                $featured_image_url = $manual_image;
            }

            if ($dalle_enabled) {
                $dalle_image = $this->openai_service->generateDalleImage($openai_api_key, $game);
                if ($dalle_image) {
                    $featured_image_url = $dalle_image;
                }
            }

            // Upload and set featured image if URL is available
            if ($featured_image_url) {
                global $wp_filesystem;

                // Initialize WP_Filesystem
                if (!function_exists('WP_Filesystem')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }

                if (!WP_Filesystem()) {
                    Logger::log("WP_Filesystem could not be initialized.", 'ERROR');
                    return;
                }

                $upload_dir = wp_upload_dir();

                // Fetch image data using wp_remote_get
                $response = wp_remote_get($featured_image_url);
                if (is_wp_error($response)) {
                    Logger::log("Failed to fetch image from URL: {$featured_image_url}. Error: " . $response->get_error_message(), 'ERROR');
                    return;
                }

                $image_data = wp_remote_retrieve_body($response);
                if (empty($image_data)) {
                    Logger::log("Image data is empty for URL: {$featured_image_url}", 'ERROR');
                    return;
                }

                $filename = basename($featured_image_url);
                $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
                if (empty($fileExtension)) {
                    $fileExtension = 'png';
                }

                $filename = md5(uniqid(wp_rand(), true)) . '.' . $fileExtension;

                $file = trailingslashit($upload_dir['path']) . $filename;

                // Use WP_Filesystem to write the image data to a file
                if (!$wp_filesystem->put_contents($file, $image_data, FS_CHMOD_FILE)) {
                    Logger::log("Failed to save image to {$file} using WP_Filesystem.", 'ERROR');
                    return;
                }

                $wp_filetype = wp_check_filetype($filename, null);
                $attachment = [
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit'
                ];
                $attach_id = wp_insert_attachment($attachment, $file, $post_id);
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $file);
                wp_update_attachment_metadata($attach_id, $attach_data);
                set_post_thumbnail($post_id, $attach_id);

                // Log the image upload success
                Logger::log("Featured image set successfully for post ID {$post_id}", 'INFO');
            }
        }

        return $post_id;
    }



    /**
     * Generate a basic post title from the given content.
     *
     * @param string $content The content from which to generate the title.
     * @return string The generated post title.
     */
    private function generate_post_title($content)
    {
        // Basic title generation from content
        $words = wp_trim_words($content, 6, '...');
        return $words;
    }
}