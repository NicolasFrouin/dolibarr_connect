<?php

dol_include_once("/connect/class/lib/return.class.php");

trait BasicAuth
{
    /**
     * Register a new user
     * 
     * Creates a new client, contact, and user linked and sharing the same email
     * 
     * @param mixed $userData
     * 
     * @url POST /register
     */
    public function register($userData = [])
    {
        dol_include_once("/connect/class/lib/override/user/api_users.class.php");

        if (empty($userData["email"])) {
            return ReturnObject::error("ERR_MISSING_PARAMETER", "Missing parameter email", 422)->send();
        }

        if ($userData["email"] === $userData["login"] || filter_var($userData["login"], FILTER_VALIDATE_EMAIL)) {
            unset($userData["login"]);
        }

        // If no login is provided, use the email
        if (empty($userData["login"])) {
            // '@' is not allowed in login
            $userData["login"] = strtr($userData["email"], ["@" => "[at]"]);
        }

        // Allow `name` to be used instead of `firstname` and `lastname`
        if (!empty($userData["name"]) && empty($userData["firstname"]) && empty($userData["lastname"])) {
            $names = explode(" ", htmlspecialchars($userData["name"], ENT_COMPAT | ENT_HTML401));
            $lastname = implode(" ", array_slice($names, 1));

            // Fill the lastname first
            $userData["lastname"] = !empty($lastname) ? $lastname : $names[0];
            $userData["firstname"] = $userData["lastname"] == $names[0] ? "" : $names[0];
        }

        // If there are still no names, use the email
        if (empty($userData["lastname"])) {
            $userData["lastname"] = explode("@", $userData["email"])[0];
        }

        $user = new CustomUser($this->db);

        $return = $user->register($userData, DolibarrApiAccess::$user)->send();

        return CustomApiUsers::getOnlyOAuthData($return);
    }

    /**
     * Log in a user
     * 
     * @url GET /login
     */
    public function loginGet($login, $password, $entity = '', $reset = 0)
    {
        dol_include_once("/connect/class/lib/override/user/api_users.class.php");

        return (new CustomApiUsers())->login($login, $password, $entity, $reset);
    }

    /**
     * Log in a user
     * 
     * @url POST /login
     */
    public function loginPost($login, $password, $entity = '', $reset = 0)
    {
        dol_include_once("/connect/class/lib/override/user/api_users.class.php");

        return (new CustomApiUsers())->login($login, $password, $entity, $reset);
    }

    /**
     * Reset a user's password and send them an email
     * 
     * @param string $login The user's login
     * @param string $password The new password, if empty a random one will be generated
     * @param bool $changeLater Whether the user should be prompted to change their password on next login
     * 
     * @url POST /resetpassword
     */
    public function resetPassword($login, $password = "", $changeLater = false)
    {
        dol_include_once("/connect/class/lib/override/user/api_users.class.php");

        return (new CustomApiUsers())->resetPassword($login, $password, $changeLater);
    }
}
