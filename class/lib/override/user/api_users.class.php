<?php

dol_include_once("/user/class/api_users.class.php");
dol_include_once("/connect/class/lib/override/user/user.class.php");
dol_include_once("/connect/class/lib/return.class.php");

class CustomApiUsers extends Users
{
	public $user;

	public function __construct()
	{
		parent::__construct();

		$this->user = new CustomUser($this->db);
	}

	/**
	 * Register a new user
	 * 
	 * @param	mixed	$userData
	 * 
	 * @url		POST	/register
	 */
	public function register($userData = [])
	{
		$return = $this->user->register($userData, DolibarrApiAccess::$user)->send();
		foreach ($return as $key => $value) {
			if (isset($value->id)) {
				$return[$key] = $this->_cleanObjectDatas($value);
			}
		}
		return $return;
	}

	/**
	 * Login a user
	 * 
	 * @param 	string	 	$login		The login of the user
	 * @param 	string	 	$password	The password of the user
	 * @param 	string	 	$entity		Entity (when multicompany module is used). '' means 1=first company
	 * @param 	int			$reset		Wether to reset the API key on login
	 * 
	 * @return	ReturnObject
	 * 
	 * @url		POST		/login
	 */
	public function login($login, $password, $entity = '', $reset = 0)
	{
		dol_include_once("/api/class/api_login.class.php");
		// dol_include_once("/societe/class/societe.class.php");
		// dol_include_once("/contact/class/contact.class.php");
		dol_include_once("/custom/connect/class/lib/override/user/user.class.php");
		dol_include_once("/custom/connect/class/lib/override/user/api_users.class.php");

		$cleaner = new CustomApiUsers();
		$loginClass = new Login();

		$loginRes = $loginClass->index($login, $password, $entity, $reset);

		if (!isset($loginRes["success"], $loginRes["success"]["token"])) {
			return ReturnObject::error("ERR_LOGIN_FAILED", "Login failed")->send();
		}

		$isLoginEmail = filter_var($login, FILTER_VALIDATE_EMAIL);

		$sql = "SELECT u.rowid FROM " . MAIN_DB_PREFIX . "user as u";
		$sql .= " WHERE u." . ($isLoginEmail !== false ? "email" : "login") . " = '" . $this->db->escape($login) . "'";
		$sql .= " AND u.api_key = '" . $this->db->escape(dolEncrypt($loginRes["success"]["token"], '', '', 'dolibarr')) . "';";

		$resql = $this->db->query($sql);
		if (!$resql) return ReturnObject::error("ERR_QUERY_FAILED", "Query failed")->send();

		$obj = $this->db->fetch_object($resql);
		if (!$obj) return ReturnObject::error("ERR_NO_QUERY_RESULT", "Query returned no result", 500)->send();

		$user = new CustomUser($this->db);
		if ($user->fetch($obj->rowid) <= 0) return ReturnObject::error("ERR_USER_NOT_FOUND", "User not found")->send();

		return CustomApiUsers::getOnlyOAuthData($user);

		// $user->token = $user->api_key;

		// $thirdparty = new Societe($this->db);
		// if ($thirdparty->fetch($user->socid) <= 0) {
		// 	$thirdparty = null;
		// }

		// $contact = new Contact($this->db);
		// if ($contact->fetch($user->contact_id) <= 0) {
		// 	$contact = null;
		// }

		// return ReturnObject::success([
		// 	"userId" => $user->id,
		// 	"user" => $cleaner->_cleanObjectDatas($user),
		// 	"thirdpartyId" => $thirdparty->id ?? null,
		// 	"thirdparty" => $cleaner->_cleanObjectDatas($thirdparty),
		// 	"contactId" => $contact->id ?? null,
		// 	"contact" => $cleaner->_cleanObjectDatas($contact),
		// ])->send();
	}

	/**
	 * Reset the password of a user
	 * 
	 * Only admin can use this method
	 * 
	 * @param	string			$login			The login of the user
	 * @param	string			$password		The new password
	 * @param	bool			$changeLater	Whether the user should change the password later
	 * 
	 * @return	ReturnObject
	 * 
	 * @url		POST			/reset-password
	 */
	public function resetPassword($login, $password = "", $changeLater = false)
	{
		dol_include_once("/custom/connect/class/lib/env.class.php");

		if (DolibarrApiAccess::$user->admin < 1) {
			return ReturnObject::error("ERR_PERMISSION_DENIED", "Permission denied", 403)->send();
		}

		$user = new CustomUser($this->db);
		if ($user->fetch(null, $login) <= 0) {
			return ReturnObject::error("ERR_USER_NOT_FOUND", "User not found", 404)->send();
		}

		$changePasswordRes = $user->setPassword(DolibarrApiAccess::$user, $password, $changeLater);

		if (is_numeric($changePasswordRes) && $changePasswordRes < 0) {
			if ($user->error) {
				return ReturnObject::error("ERR_CHANGE_PASSWORD_FAILED", $user->error, 400)->send();
			}
			return ReturnObject::error("ERR_CHANGE_PASSWORD_FAILED", "Change password failed")->send();
		}

		$userSenderId = Env::get("RESET_PASSWORD_SENDER_USER_ID");

		if (!empty($userSenderId) && is_numeric($userSenderId) && $userSenderId > 0) {
			$userSender = new CustomUser($this->db);
			if ($userSender->fetch($userSenderId) <= 0) {
				return ReturnObject::error("ERR_SENDER_USER_NOT_FOUND", "Sender user not found", 404)->send();
			}

			$passwordSendRes = $user->send_password($userSender, $user->pass);
		}

		return ReturnObject::success([
			"userId" => $user->id,
			"mail" => $passwordSendRes,
			"user" => $this->_cleanObjectDatas($user),
		])->send();
	}

	public function oAuthGet($id)
	{
		$fetchId = $this->user->fetch($id);

		if ($fetchId <= 0) {
			return ReturnObject::error("ERR_USER_NOT_FOUND", "User not found", 404)->send();
		}

		$return = self::getOnlyOAuthData($this->user);

		return ReturnObject::success($return)->send();
	}

	public function oAuthGetByEmail($email)
	{
		$userId = $this->user->findUserIdByEmail($email);

		if ($userId <= 0) {
			return ReturnObject::error("ERR_USER_NOT_FOUND", "User not found", 404)->send();
		}

		$this->user->fetch($userId);

		$return = self::getOnlyOAuthData($this->user);

		return ReturnObject::success($return)->send();
	}

	public function oAuthUpdate($id, $data)
	{
		$user = $this->get($id);

		return ReturnObject::success(self::getOnlyOAuthData($user))->send();
	}

	/**
	 * Get only the OAuth data
	 * 
	 * @param	object	$object
	 * 
	 * @return	array
	 */
	public static function getOnlyOAuthData($object)
	{
		if (!is_object($object)) return $object;

		$return = [
			"id" => "",
			"email" => "",
			"emailVerified" => null,
			"name" => "",
			"api_key" => "",
			"admin" => 0
		];

		$return["id"] = $object->id;
		$return["email"] = $object->email;
		$return["name"] = implode(" ", [$object->firstname, $object->lastname]);
		$return["api_key"] = $object->api_key;
		$return["admin"] = $object->admin;

		return $return;
	}
}
