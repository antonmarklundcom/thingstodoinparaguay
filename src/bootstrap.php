<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Minimal PSR-4 autoloader for the `Ttp\` namespace -> src/.
 * No Composer at runtime (plan §1.1).
 */
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Ttp\\')) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, 4));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/Vendor/Parsedown.php';
