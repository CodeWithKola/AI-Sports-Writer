<?php

namespace AiSportsWriter\Admin;

/**
 * Class to handle the post configuration settings for the AI Sports Writer.
 */
class PostConfigPage
{
    // Constant to hold the option name for storing settings in the database
    private const OPTION_NAME = 'ai_sports_writer_post_settings';

    /**
     * Register necessary actions for the admin panel.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Registers the settings, sections, and fields in the admin page.
     */
    public function register_settings(): void
    {
        register_setting(
            self::OPTION_NAME,
            self::OPTION_NAME,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'max_games_per_day' => 5,
                    'max_games_per_hour' => 5,
                    'post_intervals' => 5,
                    'post_author' => get_current_user_id(),
                    'post_category' => 0,
                    'ai_content_prompt' => '',
                    'featured_image_url' => '',
                    'dalle_image_generation' => 0,
                    'openai_model' => 'gpt-3.5-turbo',
                ],
            ]
        );

        // Post Configuration Section
        add_settings_section(
            'post_settings',
            '',
            [$this, 'render_post_settings_section'],
            'ai-sports-writer-post'
        );

        add_settings_field(
            'max_games_per_day',
            'Maximum Games Per Day',
            [$this, 'render_max_games_per_day_field'],
            'ai-sports-writer-post',
            'post_settings'
        );

        add_settings_field(
            'max_games_per_hour',
            'Maximum Games Per Hour',
            [$this, 'render_max_games_per_hour_field'],
            'ai-sports-writer-post',
            'post_settings'
        );

        add_settings_field(
            'post_intervals',
            'Post Intervals (minutes)',
            [$this, 'render_post_intervals_field'],
            'ai-sports-writer-post',
            'post_settings'
        );

        add_settings_field(
            'post_author',
            'Default Post Author',
            [$this, 'render_post_author_field'],
            'ai-sports-writer-post',
            'post_settings'
        );

        add_settings_field(
            'post_category',
            'Default Post Category',
            [$this, 'render_post_category_field'],
            'ai-sports-writer-post',
            'post_settings'
        );


        // Prompt Configuration Section
        add_settings_section(
            'prompt_settings',
            'AI Prompt Configuration',
            [$this, 'render_prompt_settings_section'],
            'ai-sports-writer-post'
        );

        add_settings_field(
            'ai_content_prompt',
            'AI Content Generation Prompt',
            [$this, 'render_ai_content_prompt_field'],
            'ai-sports-writer-post',
            'prompt_settings'
        );



        // Featured Image Section
        add_settings_section(
            'image_settings',
            'Featured Image Settings',
            [$this, 'render_image_settings_section'],
            'ai-sports-writer-post'
        );

        add_settings_field(
            'featured_image_upload',
            'Featured Image',
            [$this, 'render_featured_image_upload_field'],
            'ai-sports-writer-post',
            'image_settings'
        );

        add_settings_field(
            'dalle_image_generation',
            'DALL-E Image Generation',
            [$this, 'render_dalle_image_generation_field'],
            'ai-sports-writer-post',
            'image_settings'
        );
        add_settings_field(
            'openai_model',
            'OpenAI Model',
            [$this, 'render_openai_model_field'],
            'ai-sports-writer-post',
            'api_settings'
        );
    }


    // Render callback for post settings
    public function render_post_settings_section(): void
    {
        echo '<p>' . esc_html__('Configure post generation settings such as the maximum number of posts per day/hour.', 'ai-sports-writer') . '</p>';
    }

    // Render field for maximum games per day
    public function render_max_games_per_day_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $value = $options['max_games_per_day'] ?? 5;
        echo '<input type="number" name="' . self::OPTION_NAME . '[max_games_per_day]" value="' . esc_attr($value) . '" />';
    }

    // Render field for maximum games per hour
    public function render_max_games_per_hour_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $value = $options['max_games_per_hour'] ?? 5;
        echo '<input type="number" name="' . self::OPTION_NAME . '[max_games_per_hour]" value="' . esc_attr($value) . '" />';
    }

    // Render field for post intervals (in minutes)
    public function render_post_intervals_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $value = $options['post_intervals'] ?? 5;
        echo '<input type="number" name="' . self::OPTION_NAME . '[post_intervals]" value="' . esc_attr($value) . '" min="1" max="30" />';
        echo '<p class="description">Interval in minutes between scheduled posts (1-30 minutes).</p>';
    }

    // Render field for default post author
    public function render_post_author_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $selected = $options['post_author'] ?? get_current_user_id();

        $users = get_users([
            'capability' => 'publish_posts',
            'fields' => ['ID', 'display_name'],
        ]);

        echo '<select name="' . self::OPTION_NAME . '[post_author]">';
        foreach ($users as $user) {
            $user_id = esc_attr($user->ID);
            $user_display_name = esc_html($user->display_name);
            $is_selected = selected($selected, $user_id, false);
            echo "<option value=\"{$user_id}\" {$is_selected}>{$user_display_name}</option>";
        }
        echo '</select>';
        echo '<p class="description">Select the default author for automatically generated posts.</p>';
    }


    // Render field for default post category
    public function render_post_category_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $selected = $options['post_category'] ?? 0;

        $categories = get_categories(['hide_empty' => false]);

        echo '<select name="' . self::OPTION_NAME . '[post_category]">';
        echo '<option value="0">Select a Category</option>';
        foreach ($categories as $category) {
            $cat_id = esc_attr($category->term_id);
            $cat_name = esc_html($category->name);
            $is_selected = selected($selected, $cat_id, false);
            echo "<option value=\"{$cat_id}\" {$is_selected}>{$cat_name}</option>";
        }
        echo '</select>';
        echo '<p class="description">Select the default category for automatically generated posts.</p>';
    }

    public function render_prompt_settings_section(): void {}

    //Render field for AI content generation prompt
    public function render_ai_content_prompt_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $value = $options['ai_content_prompt'] ??  'You are a passionate football blogger writing for fans who love deep match insights. Your goal is to create an engaging, narrative-driven preview that feels like a conversation with a knowledgeable friend at a sports bar.

        Writing Instructions:
        1. Write in a conversational, passionate tone as if discussing the match with a close friend
        2. Provide context beyond raw statistics - discuss team dynamics and potential match narratives
        3. Include a balanced, nuanced prediction that considers both statistical likelihood and the unpredictable nature of football
        4. Use engaging storytelling techniques to make the preview compelling
        5. Incorporate the betting odds context subtly, focusing on match analysis rather than pure gambling perspective
        6. Aim for 500-700 words
        7. End with a provocative question or intriguing prediction to spark reader engagement
        
        Special Requests:
        - Avoid generic sports clichés
        - Use vivid, descriptive language
        - Highlight potential match-defining moments
        - Create a sense of anticipation and excitement';

        echo '<textarea name="' . self::OPTION_NAME . '[ai_content_prompt]" rows="6" cols="80">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Customize the prompt sent to OpenAI for content generation.</p>';
    }


    // Render callback for image settings
    public function render_image_settings_section(): void
    {
        echo '<p>Set the configuration for featured images in posts.</p>';
    }
    // Render field for uploading featured image
    public function render_featured_image_upload_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $value = $options['featured_image_url'] ?? '';

        echo '<input type="text" id="featured_image_url" name="' . self::OPTION_NAME . '[featured_image_url]" value="' . esc_attr($value) . '" />';
        echo '<input type="button" id="upload_featured_image" class="button" value="Upload Image" />';

        if ($value) {
            echo '<div style="margin-top: 10px;">';
            echo '<img src="' . esc_url($value) . '" style="max-width: 300px; height: auto;" />';
            echo '</div>';
        }
    }

    // Render checkbox for DALL-E image generation
    public function render_dalle_image_generation_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $checked = checked(1, ($options['dalle_image_generation'] ?? 0), false);

        echo '<label>';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[dalle_image_generation]" value="1" ' . $checked . '/>';
        echo ' Enable DALL-E Image Generation';
        echo '</label>';
        echo '<p class="description">When checked, the plugin will attempt to generate a featured image using DALL-E for each post.</p>';
    }

    // Render field for selecting OpenAI model
    public function render_openai_model_field(): void
    {
        $options = get_option(self::OPTION_NAME);
        $selected = $options['openai_model'] ?? 'gpt-3.5-turbo';
        $allowed_models = [
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-16k',
            'gpt-4',
            'gpt-4-turbo',
            'gpt-4o'
        ];

        echo '<select name="' . self::OPTION_NAME . '[openai_model]">';
        foreach ($allowed_models as $model) {
            $is_selected = selected($selected, $model, false);
            echo "<option value=\"{$model}\" {$is_selected}>{$model}</option>";
        }

        echo '</select>';
    }

    // Sanitize input settings before saving
    public function sanitize_settings($input): array
    {
        $sanitized = [];

        // Max games per day validation
        if ($input['max_games_per_day'] < 1 || $input['max_games_per_day'] > 100) {
            add_settings_error(
                self::OPTION_NAME,
                'invalid_max_games_per_day',
                __('Maximum games per day should be between 1 and 100.', 'ai-sports-writer')
            );
            $sanitized['max_games_per_day'] = 5;
        } else {
            $sanitized['max_games_per_day'] = (int) $input['max_games_per_day'];
        }


        // Max games per hour validation
        if ($input['max_games_per_hour'] < 1 || $input['max_games_per_hour'] > 24) {
            add_settings_error(
                self::OPTION_NAME,
                'invalid_max_games_per_hour',
                __('Maximum games per hour should be between 1 and 24.', 'ai-sports-writer')
            );
            $sanitized['max_games_per_hour'] = 5;
        } else {
            $sanitized['max_games_per_hour'] = (int) $input['max_games_per_hour'];
        }

        // Post intervals validation
        $post_intervals = (int) ($input['post_intervals'] ?? 5);
        if ($post_intervals < 1 || $post_intervals > 30) {
            add_settings_error(
                self::OPTION_NAME,
                'invalid_post_intervals',
                __('Post intervals should be between 1 and 30 minutes.', 'ai-sports-writer')
            );
            $sanitized['post_intervals'] = 5;
        } else {
            $sanitized['post_intervals'] = $post_intervals;
        }

        // Featured image url validator
        $featured_image_url = esc_url_raw($input['featured_image_url'] ?? '');
        if (!empty($featured_image_url)) {
            $file_type = wp_check_filetype($featured_image_url);
            $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (!filter_var($featured_image_url, FILTER_VALIDATE_URL)) {
                add_settings_error(
                    self::OPTION_NAME,
                    'invalid_featured_image_url',
                    __('The featured image URL is not a valid URL.', 'ai-sports-writer')
                );
                $sanitized['featured_image_url'] = '';
            } elseif (!in_array($file_type['ext'], $allowed_image_types)) {
                add_settings_error(
                    self::OPTION_NAME,
                    'invalid_featured_image_type',
                    __('The featured image URL does not point to a valid image file (jpg, jpeg, png, or gif).', 'ai-sports-writer')
                );
                $sanitized['featured_image_url'] = '';
            } else {
                $sanitized['featured_image_url'] = $featured_image_url;
            }
        } else {
            $sanitized['featured_image_url'] = '';
        }

        $sanitized['dalle_image_generation'] = (int) ($input['dalle_image_generation'] ?? 0);
        $sanitized['post_author'] = (int) ($input['post_author'] ?? get_current_user_id());
        $sanitized['post_category'] = (int) ($input['post_category'] ?? 0);

        $allowed_html = [
            'p' => [],
            'br' => [],
            'strong' => [],
            'em' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
        ];

        $sanitized['ai_content_prompt'] = wp_kses($input['ai_content_prompt'] ?? '', $allowed_html);
        $allowed_models = [
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-16k',
            'gpt-4',
            'gpt-4-turbo',
            'gpt-4o'
        ];
        $sanitized['openai_model'] = in_array($input['openai_model'] ?? '', $allowed_models, true)
            ? $input['openai_model']
            : 'gpt-3.5-turbo';


        return $sanitized;
    }
}
