<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024   Nicolas Frouin          <frouinnicolas@gmail.com>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

dol_include_once('/connect/class/traits/authaccount.trait.php');
dol_include_once('/connect/class/traits/session.trait.php');
dol_include_once('/connect/class/traits/verificationtoken.trait.php');

trait OAuth
{
	use TSession;
	use TAuthAccount;
	use TVerificationToken;

	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * @url POST /oauth/register
	 */
	public function oAuthRegister($userData = [])
	{
		dol_include_once("/connect/class/lib/override/user/api_users.class.php");

		if (empty($userData["email"]) || empty($userData["name"])) {
			return ReturnObject::error("ERR_MISSING_PARAMETER", "Missing parameters 'email' or 'name'", 422)->send();
		}

		$registerData = [];

		$names = explode(" ", htmlspecialchars($userData["name"], ENT_COMPAT | ENT_HTML401));
		$lastname = implode(" ", array_slice($names, 1));

		// Fill the lastname first
		$registerData["lastname"] = !empty($lastname) ? $lastname : $names[0];
		$registerData["firstname"] = $registerData["lastname"] == $names[0] ? "" : $names[0];

		$registerData["email"] = $userData["email"];

		// '@' is not allowed in login
		$registerData["login"] = str_replace("@", "[at]", $userData["email"]);

		$user = new CustomUser($this->db);

		$return = $user->register($registerData, DolibarrApiAccess::$user, 0, true)->send();

		return CustomApiUsers::getOnlyOAuthData($return);
	}

	/**
	 * @url GET /oauth/users/{id}
	 */
	public function oAuthGetUser($id)
	{
		dol_include_once("/connect/class/lib/override/user/api_users.class.php");

		return (new CustomApiUsers())->oAuthGet($id);
	}

	/**
	 * @url GET /oauth/users/email/{email}
	 */
	public function oAuthGetUserByEmail($email)
	{
		dol_include_once("/connect/class/lib/override/user/api_users.class.php");

		return (new CustomApiUsers())->oAuthGetByEmail($email);
	}

	/**
	 * @url PUT /oauth/users/{id}
	 */
	public function oAuthUpdateUser($id, $data)
	{
		dol_include_once("/connect/class/lib/override/user/api_users.class.php");

		return (new CustomApiUsers())->oAuthUpdate($id, $data);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 */
	protected function _cleanObjectDatas($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->rowid);
		unset($object->canvas);

		// If object has lines, remove $db property
		if (isset($object->lines) && is_array($object->lines) && count($object->lines) > 0) {
			$nboflines = count($object->lines);
			for ($i = 0; $i < $nboflines; $i++) {
				$this->_cleanObjectDatas($object->lines[$i]);

				unset($object->lines[$i]->lines);
				unset($object->lines[$i]->note);
			}
		}

		return $object;
	}
}
