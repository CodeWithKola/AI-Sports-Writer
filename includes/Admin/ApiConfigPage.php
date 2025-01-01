<?php

namespace AiSportsWriter\Admin;

use AiSportsWriter\Utilities\Logger;
use WP_Error;

class ApiConfigPage
{

    /**
     * Allowed OpenAI models
     */
    private const ALLOWED_MODELS = [
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
        'gpt-4' => 'GPT-4',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-4o' => 'GPT-4o'
    ];

    /**
     * Register hooks and actions
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_fetch_regions', [$this, 'fetch_regions_ajax']);
        add_action('wp_ajax_save_content_regions', [$this, 'save_content_regions_ajax']);
        add_action('wp_ajax_test_sport_api', [$this, 'test_sport_api']);
    }

    /**
     * Register plugin settings
     */
    public function register_settings(): void
    {
        register_setting(
            'ai_sports_writer_api_settings',
            'ai_sports_writer_api_settings',
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'sport_api_key' => '',
                    'openai_api_key' => '',
                    'openai_model' => 'gpt-3.5-turbo',
                ],
            ],

        );

        // API Configuration Section
        add_settings_section(
            'api_settings',
            __('API Configuration', 'ai-sports-writer'),
            [$this, 'api_settings_section_callback'],
            'ai-sports-writer'
        );

        // Register settings fields
        $this->register_api_fields();
    }

    /**
     * Register individual API settings fields
     */
    private function register_api_fields(): void
    {
        $fields = [
            'sport_api_key' => __('Sport API Key', 'ai-sports-writer'),
            'openai_api_key' => __('OpenAI API Key', 'ai-sports-writer'),
            'openai_model' => __('OpenAI Model', 'ai-sports-writer'),
        ];

        foreach ($fields as $field_id => $field_label) {
            add_settings_field(
                $field_id,
                $field_label,
                [$this, $field_id . '_callback'],
                'ai-sports-writer',
                'api_settings'
            );
        }
    }

    /**
     * API settings section callback
     */
    public function api_settings_section_callback(): void
    {
        echo '<p>' . esc_html__('Configure your API settings here. Enter the required keys and test API connectivity.', 'ai-sports-writer') . '</p>';
    }

    /**
     * Sport API key input callback
     */
    public function sport_api_key_callback(): void
    {
        $options = get_option('ai_sports_writer_api_settings');
        $sport_api_key = $options['sport_api_key'] ?? '';

        printf(
            '<input type="text" name="%s[sport_api_key]" value="%s" class="regular-text" />
            <button id="test-sport-api" class="button-primary">%s</button>',
            esc_attr('ai_sports_writer_api_settings'),
            esc_attr($sport_api_key),
            esc_html__('Test API', 'ai-sports-writer')
        );
    }

    /**
     * OpenAI API key input callback
     */
    public function openai_api_key_callback(): void
    {
        $options = get_option('ai_sports_writer_api_settings');
        $openai_api_key = $options['openai_api_key'] ?? '';

        printf(
            '<input type="text" name="%s[openai_api_key]" value="%s" class="regular-text" />',
            esc_attr('ai_sports_writer_api_settings'),
            esc_attr($openai_api_key)
        );
    }

    /**
     * OpenAI model selection callback
     */
    public function openai_model_callback(): void
    {
        $options = get_option('ai_sports_writer_api_settings');
        $current_model = $options['openai_model'] ?? 'gpt-3.5-turbo';

        $model_options = array_map(function ($model_key, $model_name) use ($current_model) {
            return sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($model_key),
                selected($current_model, $model_key, false),
                esc_html($model_name)
            );
        }, array_keys(self::ALLOWED_MODELS), self::ALLOWED_MODELS);
        $model_options = implode('', $model_options);
        printf(
            '<select name="%s[openai_model]">%s</select>
            <p class="description">%s</p>',
            esc_attr('ai_sports_writer_api_settings'),
            $model_options,
            esc_html__('Select the OpenAI model to use for content generation.', 'ai-sports-writer')
        );
    }

    /**
     * Test Sport API Ajax handler
     */
    public function test_sport_api(): void
    {
        // Verify nonce for security
        check_ajax_referer('fcg_nonce', 'nonce');

        // Get the API key from options
        $options = get_option('ai_sports_writer_api_settings');
        $api_key = $options['sport_api_key'] ?? '';

        // Check if the API key is provided
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API Key is missing', 'ai-sports-writer')]);
            return;
        }

        try {
            // Initialize the SportApiService
            $sport_api_service = new \AiSportsWriter\Services\SportApiService();

            // Fetch regions
            $regions = $sport_api_service->fetchFootballRegions($api_key);

            // Insert regions into database
            $this->insert_regions_into_db($regions);

            // Send success response
            wp_send_json_success(['message' => __('API Connection Successful', 'ai-sports-writer')]);
        } catch (\Exception $e) {
            // Log the error and send failure response
            Logger::log('Sport API Test Error: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error(['message' => sprintf(
                // translators: %s is the error message returned from the exception
                __('Request failed: %s', 'ai-sports-writer'),
                $e->getMessage()
            )]);
        }
    }

    /**
     * Insert regions into database
     * 
     * @param array $regions Regions data to insert
     */
    private function insert_regions_into_db(array $regions): void
    {
        global $wpdb;
        $regions = $regions['data'] ?? [];
        $table_name = esc_sql($wpdb->prefix . 'football_regions');

        // Prepare and insert regions
        foreach ($regions as $region) {
            $name = sanitize_text_field($region['name']);
            $leagues = wp_json_encode($region['leagues']);

            // Check if region exists
            $existing_region = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE name = %s",
                    $name
                )
            );

            if ($existing_region) {
                continue; // Skip duplicate region
            }

            // Insert new region
            $wpdb->insert(
                $table_name,
                [
                    'name' => $name,
                    'leagues' => $leagues,
                ],
                ['%s', '%s']
            );
        }
    }



    /**
     * Fetch regions via Ajax
     */
    public function fetch_regions_ajax(): void
    {
        // Verify nonce
        check_ajax_referer('fcg_nonce', 'nonce');

        global $wpdb;

        $content_regions_table = esc_sql($wpdb->prefix . 'content_regions');
        $regions_table = esc_sql($wpdb->prefix . 'football_regions');

        // Fetch regions with selection status
        $regions = $wpdb->get_results(
            "
            SELECT r.*, 
            (SELECT COUNT(*) FROM {$content_regions_table} cr WHERE cr.region_id = r.id) > 0 as selected 
            FROM {$regions_table} r
            ",
            ARRAY_A
        );

        wp_send_json_success($regions);
    }



    /**
     * Save content regions via Ajax
     */
    public function save_content_regions_ajax(): void
    {
        // Verify nonce
        check_ajax_referer('fcg_nonce', 'nonce');

        global $wpdb;

        // Sanitize the table name
        $content_regions_table = esc_sql($wpdb->prefix . 'content_regions');

        // Sanitize and validate selected regions
        $selected_regions = isset($_POST['selected_regions'])
            ? array_map('intval', (array)$_POST['selected_regions'])
            : [];

        if (empty($selected_regions)) {
            wp_send_json_error(['message' => __('No regions selected.', 'ai-sports-writer')]);
            return;
        }

        // Clear existing selections
        $wpdb->query("TRUNCATE TABLE {$content_regions_table}");

        // Insert new selections
        foreach ($selected_regions as $region_id) {
            $wpdb->insert(
                $content_regions_table,
                ['region_id' => $region_id],
                ['%d']
            );
        }

        wp_send_json_success(['message' => __('Regions saved successfully.', 'ai-sports-writer')]);
    }


    /**
     * Sanitize and validate settings
     * 
     * @param array $input Unsanitized input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings(array $input): array
    {
        $sanitized = [];

        // Sanitize API keys
        $sanitized['sport_api_key'] = sanitize_text_field($input['sport_api_key'] ?? '');
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');

        // Validate OpenAI model
        if (isset($input['openai_model']) && array_key_exists($input['openai_model'], self::ALLOWED_MODELS)) {
            $sanitized['openai_model'] = $input['openai_model'];
        } else {
            $sanitized['openai_model'] = 'gpt-3.5-turbo';
            add_settings_error(
                'ai_sports_writer_api_settings',
                'invalid_openai_model',
                __('Invalid OpenAI model selected. Defaulting to GPT-3.5 Turbo.', 'ai-sports-writer')
            );
        }

        return $sanitized;
    }
}