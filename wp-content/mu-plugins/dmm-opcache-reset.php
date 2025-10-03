<?php
/**
 * Plugin Name: DMM OPcache Reset (Temporary)
 */
add_action('init', function () {
    if (function_exists('opcache_reset')) {
        opcache_reset();
        error_log('DMM: opcache_reset() called');
    }
});
