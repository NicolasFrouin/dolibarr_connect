<?php

/**
 * Helper class for managing environment variables.
 */
abstract class Env
{
    private static $env = [];
    private static $isLoaded = false;

    /**
     * The path to the .env file.
     * 
     * Change this to the path of your .env file if it is not in the module root directory.
     */
    private const ENV_FILE = __DIR__ . "/../../.env";

    /**
     * Get all environment variables.
     * 
     * @return array An associative array of environment variables.
     */
    public static function getAll()
    {
        if (!self::$isLoaded) {
            self::load();
        }

        return self::$env;
    }

    /**
     * Get the value of an environment variable.
     *
     * @param string $key The key of the environment variable.
     * 
     * @return mixed|null The value of the environment variable, or null if not found.
     */
    public static function get($key)
    {
        if (!self::$isLoaded) {
            self::load();
        }

        return self::$env[$key] ?? null;
    }

    /**
     * Set the value of an environment variable.
     *
     * @param string $key The key of the environment variable.
     * @param mixed $value The value to set.
     */
    public static function set($key, $value)
    {
        self::$env[$key] = $value;
    }

    /**
     * Load environment variables from a .env file.
     *
     * @throws Exception If the .env file cannot be parsed.
     */
    public static function load()
    {
        if (self::$isLoaded) {
            return;
        }

        if (file_exists(self::ENV_FILE)) {
            $envData = parse_ini_file(self::ENV_FILE);
            if ($envData === false) {
                throw new Exception("Error parsing .env file");
            }
            self::$env = $envData;
            self::$isLoaded = true;
        }
    }
}
