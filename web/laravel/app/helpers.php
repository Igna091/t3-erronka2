<?php

if (! function_exists('env_secret')) {
    /**
     * Read a setting that may hold a secret: the environment variable $name if it is set,
     * otherwise the contents of the file named by {$name}_FILE (a Docker secret such as
     * DB_PASSWORD_FILE=/run/secrets/db_app_password), so secrets never sit in the environment.
     */
    function env_secret(string $name, ?string $default = null): ?string
    {
        $value = env($name);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $file = env($name.'_FILE');
        if (is_string($file) && is_readable($file)) {
            return trim((string) file_get_contents($file));
        }

        return $default;
    }
}
