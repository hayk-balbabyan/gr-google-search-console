<?php
/**
 * Plugin Name: GR Google Search Console
 * Description: A plugin for GR that adds a dropdown from Google Search Console properties and lists pages.
 * Version: 1.0
 * Author: Hayk Balbabyan
 */

$autoloader = __DIR__ . '/vendor/autoload.php';

if(file_exists($autoloader)) {
    require_once $autoloader;
} else {
    wp_die("Composer installation is missing! Try to install composer first");
}
$plugin_url = plugins_url('/', __FILE__); // Replace __FILE__ with the main file of your plugin
define('GSC_PLUGIN_URL', $plugin_url);
new GRGoogleSearchConsole\App();