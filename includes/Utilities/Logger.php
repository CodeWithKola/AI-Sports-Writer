<?php

namespace AiSportsWriter\Utilities;

/**
 * Class Logger
 *
 * Handles logging for the AI Sports Writer plugin.
 *
 * @package AiSportsWriter\Utilities
 */
class Logger
{
    /**
     * Log a message with a specific level
     *
     * @param string $message The message to log
     * @param string $level The log level (e.g., 'INFO', 'ERROR', 'WARNING')
     */
    public static function log(string $message, string $level = 'INFO'): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        $timestamp = current_time('mysql');
        $log_entry = sprintf('[%s] %s: %s', $timestamp, $level, $message);

        // Only echo if WP_DEBUG_DISPLAY is true, not an AJAX request, and not running from CLI
        if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY && !self::is_ajax_or_cli_request()) {
            echo esc_html($log_entry) . '<br>';
        }
    }

    private static function is_ajax_or_cli_request(): bool
    {
        return (function_exists('wp_doing_ajax') && wp_doing_ajax()) || php_sapi_name() === 'cli';
    }
}