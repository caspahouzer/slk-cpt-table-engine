<?php

/**
 * Logger class for CPT Table Engine.
 *
 * Provides structured logging functionality.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK_Cpt_Table_Engine\Helpers;

/**
 * Logger class.
 */
final class Logger
{
    /**
     * Log levels.
     */
    private const LEVEL_ERROR   = 'ERROR';
    private const LEVEL_WARNING = 'WARNING';
    private const LEVEL_INFO    = 'INFO';
    private const LEVEL_DEBUG   = 'DEBUG';

    /**
     * Log an error message.
     *
     * @param string $message The error message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message The info message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message The debug message.
     * @param array  $context Additional context data.
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Write a log entry.
     *
     * @param string $level   The log level.
     * @param string $message The log message.
     * @param array  $context Additional context data.
     * @return void
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        if (! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $context_str = ! empty($context) ? ' | Context: ' . wp_json_encode($context) : '';

        $log_message = sprintf(
            '[%s] [CPT Table Engine] [%s] %s%s',
            $timestamp,
            $level,
            $message,
            $context_str
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($log_message);
    }
}
