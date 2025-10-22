<?php
/**
 * Application Settings (key-value store)
 */

require_once __DIR__ . '/database.php';

class AppSettings {
    private static $cache = [];
    private static $initialized = false;

    private static function ensureTable(): void {
        if (self::$initialized) { return; }
        // Create table if not exists
        try {
            db()->execute(
                "CREATE TABLE IF NOT EXISTS app_settings (
                    `key` VARCHAR(191) PRIMARY KEY,
                    `value` TEXT NULL,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Exception $e) {
            // Table creation failure should not break the app; log and continue
            error_log('app_settings table init failed: ' . $e->getMessage());
        }
        self::$initialized = true;
    }

    public static function get(string $key, $default = null) {
        self::ensureTable();
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $row = db()->fetchOne('SELECT `value` FROM app_settings WHERE `key` = ?', [$key]);
        $val = $row && array_key_exists('value', $row) ? $row['value'] : $default;
        self::$cache[$key] = $val;
        return $val;
    }

    public static function set(string $key, $value): void {
        self::ensureTable();
        $exists = db()->fetchOne('SELECT `key` FROM app_settings WHERE `key` = ?', [$key]);
        if ($exists) {
            db()->execute('UPDATE app_settings SET `value` = ? WHERE `key` = ?', [$value, $key]);
        } else {
            db()->execute('INSERT INTO app_settings (`key`, `value`) VALUES (?, ?)', [$key, $value]);
        }
        self::$cache[$key] = $value;
    }
}

?>


