<?php

/**
 * Plugin Name: AI Sports Writer
 * Plugin URI: https://github.com/CodeWithKola/AI-Sports-Writer
 * Description: An automated WordPress plugin that leverages AI and sports data to generate engaging match articles, featuring customizable content scheduling for bloggers.
 * Version: 1.0.0
 * Author: Kolawole Yusuf
 * Author URI: https://github.com/CodeWithKola
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-sports-writer
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/Autoloader.php';

use AiSportsWriter\Core\Plugin;

define('AI_SPORTS_WRITER_PLUGIN_FILE', __FILE__);
Plugin::init();
