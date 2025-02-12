<?php

/**
 * Plugin Name: AI Sports Writer
 * Plugin URI: https://sendr.icu/ai-sports-writer
 * Description: An automated WordPress plugin that leverages AI and sports data to generate engaging match articles, featuring customizable content scheduling for bloggers.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Kolawole Yusuf
 * Author URI: https://sendr.icu/profile/kolawole-yusuf/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-sports-writer
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/Autoloader.php';

use AiSprtsW\Core\Plugin;

define('AISPRTSW_PLUGIN_FILE', __FILE__);
Plugin::init();
