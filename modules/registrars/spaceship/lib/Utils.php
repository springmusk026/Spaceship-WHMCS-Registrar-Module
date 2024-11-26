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
    public static function log($message)
    {
        $logFile = __DIR__ . '/../logs/api.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
    
    
}
