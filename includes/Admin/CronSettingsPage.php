<?php

namespace AiSportsWriter\Admin;

/**
 * Class CronSettingsPage
 * Handles the creation and functionality of the cron settings admin page.
 */
class CronSettingsPage
{
    /**
     * Registers the admin_init action to set up settings.
     */
    public function register(): void {}


    /**
     * Displays instructions for server-side cron setup and current cron status.
     * Ensures only authorized users can access this page.
     */
    public function renderPage(): void
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-sports-writer'));
        }

?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2><?php esc_html_e('Server-Side Cron Setup', 'ai-sports-writer'); ?></h2>
            <p><?php
                esc_html_e('Your website may be relying on visitors\' browsers to trigger cron jobs, which could lead to delays in execution.', 'ai-sports-writer');
                echo '<br />';
                esc_html_e('To ensure cron jobs run on time, we recommend setting up server-side cron jobs. Below are the instructions:', 'ai-sports-writer');
                ?></p>
            <pre>
* * * * * wget -q -O - <?php echo esc_url(get_site_url() . '/wp-cron.php'); ?>
            </pre>
            <p><?php esc_html_e('Add the above line and save. The cron job will run every minute, triggering the WordPress cron system.', 'ai-sports-writer'); ?>
            </p>

            <h2><?php esc_html_e('Cron Status', 'ai-sports-writer'); ?></h2>
            <?php
            foreach ($this->getCronHooks() as $hook => $config) {
                echo '<p>' . esc_html($this->checkCronStatus($hook, $config['interval'], $config['offset'])) . '</p>';
            }
            ?>
        </div>
<?php
    }

    /**
     * Retrieves the list of cron hooks and their configurations.
     *
     * @return array An associative array of cron hooks and their intervals and offsets.
     */
    private function getCronHooks(): array
    {
        return [
            'ai_sports_writer_cron' => ['interval' => 60 * 60, 'offset' => 10],
            'ai_sports_writer_fetch_cron' => ['interval' => 3 * 60 * 60, 'offset' => 0],
        ];
    }

    /**
     * Checks the status of a specific cron hook.
     *
     * @param string $hookName The name of the cron hook.
     * @param int $expectedInterval The expected execution interval in seconds.
     * @param int $offsetMinutes The offset in minutes for the cron execution.
     * @return string A message indicating the next scheduled run or lack of schedule.
     */
    private function checkCronStatus(string $hookName, int $expectedInterval, int $offsetMinutes): string
    {
        $nextRun = wp_next_scheduled($hookName);
        if ($nextRun) {
            return sprintf(
                // translators: %1$s is the cron hook name, %2$s is the next scheduled time
                __('Next run for %1$s is scheduled at %2$s.', 'ai-sports-writer'),
                $hookName,
                gmdate('Y-m-d H:i:s', $nextRun)
            );
        } else {
            return sprintf(
                // translators: %1$s is the cron hook name
                __('No scheduled run found for %1$s.', 'ai-sports-writer'),
                $hookName
            );
        }
    }
}
