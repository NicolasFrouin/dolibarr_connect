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

dol_include_once("/connect/class/traits/basicauth.trait.php");
dol_include_once("/connect/class/traits/oauth.trait.php");

/**
 * \file    htdocs/custom/connect/class/api_connect.class.php
 * \ingroup Connect
 * \brief   File for API management of Connect module.
 */

/**
 * API class for the `Connect` module
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Connect extends DolibarrApi
{
	use BasicAuth;
	use OAuth;

	public $authaccount;
	public $session;
	public $verificationtoken;

	public function __construct()
	{
		global $db;
		$this->db = $db;
		$this->authaccount = new AuthAccount($this->db);
		$this->session = new Session($this->db);
		$this->verificationtoken = new VerificationToken($this->db);
	}

	/**
	 * Send an email
	 * 
	 * The message can be HTML or plain text
	 *
	 * @param string $subject The subject of the email
	 * @param string $to The recipient of the email
	 * @param string $message The message, can be HTML or plain text
	 * @param bool $isHtml Whether to send the email as HTML or plain text
	 * 
	 * @return mixed
	 *
	 * @url POST /sendmail
	 */
	public function sendMail($subject, $to, $message, $isHtml = false)
	{
		if (!DolibarrApiAccess::$user->hasRight("connect", "mail", "send")) {
			throw new RestException(403);
		}

		dol_include_once("/connect/class/lib/override/mail/mail.class.php");
		dol_include_once("/connect/class/helper/env.class.php");

		$defaultEmailFrom = Env::get('DEFAULT_EMAIL_FROM');

		$mail = new MailCustom($subject, $to, $defaultEmailFrom, $message, [], [], [], "", "", 0, intval($isHtml));
		$sent = $mail->sendfile();

		dol_syslog("API Connect: sendMail: $subject, $to, sent : " . ($sent ? "true" : "false"), LOG_DEBUG);

		return $sent;
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
