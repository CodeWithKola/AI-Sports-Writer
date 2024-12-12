<?php

namespace AiSportsWriter;

class Autoloader
{
    /**
     * Registers the autoloader function for the AiSportsWriter namespace.
     *
     * This method sets up an autoloader that automatically includes the appropriate
     * PHP files for classes within the AiSportsWriter namespace. It uses the SPL
     * autoload register function to add a custom autoloader.
     *
     * The autoloader function performs the following steps:
     * 1. Checks if the class belongs to the AiSportsWriter namespace.
     * 2. If it does, it calculates the file path based on the class name.
     * 3. If the file exists, it includes the file.
     *
     * @return void
     */
    public static function register()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'AiSportsWriter\\';
            $base_dir = __DIR__ . '/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }
}

Autoloader::register();
