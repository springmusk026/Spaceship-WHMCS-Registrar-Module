<?php
/*
 * Author: Spring Musk
 * GitHub: https://github.com/springmusk026
 * Website: https://basantasapkota026.com.np
 * Telegram: https://t.me/springmusk
 * 
 * This file is part of the Spaceship Registrar Module for WHMCS.
 * All rights reserved © 2024.
 */

namespace Spaceship;

class Utils
{
    private static $logFile = __DIR__ . '/../logs/api.log';
    public static $IsDebugMode = false;
    /**
     * Logs messages with different levels (INFO, ERROR, DEBUG)
     * 
     * @param string $message The log message
     * @param string $level The log level (INFO, ERROR, DEBUG)
     */
    public static function log($message, $level = 'INFO')
    {
        try {
            $logEntry = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => strtoupper($level),
                'message' => $message
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            file_put_contents(self::$logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log('Logging failed: ' . $e->getMessage());
        }
    }
}