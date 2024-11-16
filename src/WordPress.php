<?php

namespace RabbitLoader\SDK;

class WordPress
{
    public static function isWp()
    {
        return defined('ABSPATH') && function_exists('apply_filters') && function_exists('get_option');
    }

    public static function &plugins()
    {
        $plugins = [];
        if (!self::isWp()) {
            return $plugins;
        }
        $activePlugins = apply_filters('active_plugins', get_option('active_plugins'));
        if (class_exists('WPSEO_Options') || in_array('wordpress-seo/wp-seo.php', $activePlugins)) {
            $plugins[] = "wordpress-seo";
        }
        return $plugins;
    }
}
