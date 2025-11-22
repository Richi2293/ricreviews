<?php
/**
 * Simple logger gated by the plugin setting.
 *
 * @package RicReviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RicReviews_Logger
 */
class RicReviews_Logger
{
    /**
     * Check whether logging is enabled through the plugin option.
     *
     * @return bool
     */
    public static function is_enabled()
    {
        return get_option('ricreviews_debug_logging', 'no') === 'yes';
    }

    /**
     * Write a message and optional context to the WordPress debug log.
     *
     * @param string $message Log message.
     * @param array  $context Optional structured context.
     * @return void
     */
    public static function log($message, $context = array())
    {
        if (!self::is_enabled()) {
            return;
        }

        if (!is_string($message)) {
            $message = wp_json_encode($message);
        }

        $entry = '[RicReviews] ' . $message;

        if (!empty($context)) {
            $context_string = wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($context_string !== false) {
                $entry .= ' | Context: ' . $context_string;
            }
        }

        error_log($entry);
    }
}

