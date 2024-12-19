<?php

dol_include_once("/user/class/user.class.php");
dol_include_once("/societe/class/api_thirdparties.class.php");
dol_include_once("/connect/class/lib/return.class.php");

class CustomUser extends User
{
	public $token;

	/**
	 * Register a new user by creating a client, contact and user
	 * 
	 * @param	array			$userData User data, needs at least the following fields:
	 * 									- email
	 * 									- login
	 * 									- password (if not generated)
	 * 									- firstname
	 * 									- lastname
	 * @param	User			$user User creating the new user
	 * @param	int				$notrigger
	 * @param	bool			$generatePassword Whether to generate a password or not
	 * 
	 * @return	ReturnObject
	 */
	public function register($userData, User $user, $notrigger = 0, $generatePassword = false)
	{
		dol_include_once("/societe/class/societe.class.php");
		dol_include_once("/contact/class/contact.class.php");
		dol_include_once("/connect/class/lib/env.class.php");

		global $langs;

		dol_syslog(get_class($this) . "::register", LOG_DEBUG);

		if ($generatePassword) {
			$userData["password"] = uniqid("oauth_", true);
		}

		// <$userData::$field> => <$thirdparty::$field>
		$clientOnlyData = [
			// "name" => "name", // built from firstname & lastname
			"name_alias" => "name_alias",
			"client_ref_ext" => "ref_ext",
			"url" => "url",
			"parent" => "parent",
			"client_note_private" => "note_private",
			"client_note_public" => "note_public",
			"siren" => "idprof1",
			"siret" => "idprof2",
			"ape" => "idprof3",
			"idprof4" => "idprof4",
			"idprof5" => "idprof5",
			"idprof6" => "idprof6",
			"tva_assuj" => "tva_assuj",
			"tva_intra" => "tva_intra",
			"status" => "status",
			"localtax1_assuj" => "localtax1_assuj",
			"localtax2_assuj" => "localtax2_assuj",
			"localtax1_value" => "localtax1_value",
			"localtax2_value" => "localtax2_value",
			"capital" => "capital",
			"prefix_comm" => "prefix_comm",
			"effectif_id" => "effectif_id",
			"stcomm_id" => "stcomm_id",
			"typent_id" => "typent_id",
			"forme_juridique_code" => "forme_juridique_code",
			"mode_reglement" => "mode_reglement",
			"cond_reglement" => "cond_reglement",
			"deposit_percent" => "deposit_percent",
			"transport_mode_id" => "transport_mode_id",
			"mode_reglement_supplier_id" => "mode_reglement_supplier_id",
			"cond_reglement_supplier_id" => "cond_reglement_supplier_id",
			"transport_mode_supplier_id" => "transport_mode_supplier_id",
			"shipping_method_id" => "shipping_method_id",
			"client" => "client",
			"fournisseur" => "fournisseur",
			"barcode" => "barcode",
			"default_lang" => "default_lang",
			"logo" => "logo",
			"logo_squarred" => "logo_squarred",
			"outstanding_limit" => "outstanding_limit",
			"order_min_amount" => "order_min_amount",
			"supplier_order_min_amount" => "supplier_order_min_amount",
			"fk_prospectlevel" => "fk_prospectlevel",
			"accountancy_code_buy" => "accountancy_code_buy",
			"accountancy_code_sell" => "accountancy_code_sell",
			"code_compta_client" => "code_compta_client",
			"code_compta_fournisseur" => "code_compta_fournisseur",
			"webservices_url" => "webservices_url",
			"webservices_key" => "webservices_key",
			"fk_incoterms" => "fk_incoterms",
			"location_incoterms" => "location_incoterms",
			"code_client" => "code_client",
			"code_fournisseur" => "code_fournisseur",
			"fk_multicurrency" => "fk_multicurrency",
			"multicurrency_code" => "multicurrency_code",
			"model_pdf" => "model_pdf",
		];

		// <$userData::$field> => <$contact::$field>
		$contactOnlyData = [
			"firstname" => "firstname",
			"lastname" => "lastname",
			"gender" => "gender",
			"civility" => "civility",
			"contact_ref_ext" => "ref_ext",
			"poste" => "poste",
			"photo" => "photo",
			"birthday" => "birthday",
			"contact_note_private" => "note_private",
			"contact_note_public" => "note_public",
			"phone_perso" => "phone_perso",
			"phone_mobile" => "phone_mobile",
			"priv" => "priv",
			"contact_prospectlevel_id" => "fk_prospectlevel",
			"contact_stcomm_id" => "stcomm_id",
			"contact_statut" => "status",
			"contact_default_lang" => "default_lang",
		];

		// <$userData::$field> => <$thirdparty::$field & $contact::$field>
		$bothData = [
			"zip" => "zip",
			"town" => "town",
			"address" => "address",
			"state_id" => "state_id",
			"country_id" => "country_id",
			"phone" => "phone",
			"fax" => "fax",
			"email" => "email",
			"socialnetworks" => "socialnetworks",
		];

		// <$userData::$field>
		$mandatoryFields = [
			"email",
			"login",
			"password",
			"firstname",
			"lastname",
			"entity",
		];

		if (empty($userData["entity"])) {
			$userData["entity"] = 1;
		}

		foreach ($mandatoryFields as $field) {
			if (!isset($userData[$field])) {
				return ReturnObject::error("ERR_MISSING_PARAMETER", "Missing parameter: " . $field, 400);
			}
		}

		/**
		 * * Roadmap
		 * create thirdparty
		 * create client
		 * from thirdparty, create its main contact
		 * override thirdparty main contact with user data
		 * from contact, create user
		 * return all created objects
		 */

		$this->db->begin();

		/* Create thirdparty */

		$contact = new Contact($this->db);
		$contact->firstname = $userData["firstname"];
		$contact->lastname = $userData["lastname"];
		$contact->name = implode(" ", [$userData["firstname"], $userData["lastname"]]);
		foreach ($contactOnlyData as $userDataParam => $objectParam) {
			if (isset($userData[$userDataParam])) {
				$contact->$objectParam = $userData[$userDataParam];
			}
		}

		/* Create client */

		$client = new Societe($this->db);
		$client->firstname = $userData["firstname"];
		$client->lastname = $userData["lastname"];
		$client->name = implode(" ", [$userData["firstname"], $userData["lastname"]]);
		foreach ($clientOnlyData as $userDataParam => $objectParam) {
			if (isset($userData[$userDataParam])) {
				$client->$objectParam = $userData[$userDataParam];
			}
		}

		foreach ($bothData as $userDataParam => $objectParam) {
			if (isset($userData[$userDataParam])) {
				$contact->$objectParam = $userData[$userDataParam];
				$client->$objectParam = $userData[$userDataParam];
			}
		}

		if ($client->create($user) < 0) {
			$this->db->rollback();
			return ReturnObject::error("ERR_CLIENT_CREATION_FAILED", "Failed to create client", 500, $client->errors);
		}

		/* From thirdparty, create its main contact */

		$contactId = null;
		if (($contactId = $client->create_individual($user)) < 0) {
			$this->db->rollback();
			return ReturnObject::error("ERR_CONTACT_CREATION_FAILED", "Failed to create contact", 500);
		}

		/* Override thirdparty main contact with user data */

		$contact->fetch($contactId);
		$contact->firstname = $userData["firstname"];
		$contact->lastname = $userData["lastname"];
		$contact->name = implode(" ", [$userData["firstname"], $userData["lastname"]]);
		$contact->update($contactId, $user, $notrigger);

		/* From contact, create user */

		$this->pass = $userData["password"]; // let User::create_from_contact handle the password setter

		if (($res = $this->create_from_contact($contact, $userData["login"])) < 0) {
			$this->db->rollback();
			switch ($res) {
				case -4:
					$passwordTooShortError = $langs->trans("YourPasswordMustHaveAtLeastXChars", 2);
					if (preg_replace('/\d+/', '2', $this->error) === $passwordTooShortError) {
						return ReturnObject::error("ERR_PASSWORD_TOO_SHORT", "Password too short", 422, [$res, $this->error]);
					}
					// else, fall through
				case -1:
					// Base error
					return ReturnObject::error("ERR_USER_CREATION_FAILED", "Failed to create user", 500, [$res, $this->error]);
				case -5:
					// Problem with rights
					return ReturnObject::error("ERR_RIGHTS_PROBLEM", "Problem with rights", 500, [$res, $this->error]);
				case -6:
					// User already exists
					return ReturnObject::error("ERR_USER_ALREADY_EXISTS", "User already exists", 422);
				default:
					return ReturnObject::error("ERR_UNKNOWN", "Unknown error", 500, [$res, $this->error]);
			}
		}

		$this->setApiKey();
		$base_user_group = Env::get("BASE_USER_GROUP_ID");
		if (!empty($base_user_group) && is_numeric($base_user_group) && $base_user_group > 0) {
			$this->SetInGroup($base_user_group, $this->entity, $notrigger);
		}

		$this->array_options["option_origin"] = "cmonlab";
		$this->insertExtraFields("", $user);

		$this->fetch($this->id); // reload user data

		dol_syslog(get_class($this) . "::register: Triggering " . strtoupper($this->element) . "_CUSTOM_REGISTER", LOG_DEBUG);
		$result = $this->call_trigger(strtoupper($this->element) . '_CUSTOM_REGISTER', $user);

		if ($result < 0) {
			$this->db->rollback();
			return ReturnObject::error("ERR_TRIGGER_FAILED", "Failed to trigger", 500, $this->error);
		}

		$this->db->commit();

		dol_include_once("/connect/class/lib/override/user/api_users.class.php");

		return ReturnObject::success(CustomApiUsers::getOnlyOAuthData($this));
	}

	private function setApiKey()
	{
		global $conf;

		$token = dol_hash($this->login . uniqid() . (!getDolGlobalString('MAIN_API_KEY') ? '' : $conf->global->MAIN_API_KEY), 1);

		$sql = "UPDATE " . MAIN_DB_PREFIX . "user";
		$sql .= " SET api_key = '" . $this->db->escape(dolEncrypt($token, '', '', 'dolibarr')) . "'";
		$sql .= " WHERE login = '" . $this->db->escape($this->login) . "'";

		dol_syslog(get_class($this) . "::setApiKey", LOG_DEBUG);
		$result = $this->db->query($sql);
		if (!$result) {
			return ReturnObject::error("ERR_API_KEY_CREATION_FAILED", "Failed to create API key", 500, [$this->db->lasterror()])->send();
		}
		$this->token = $token;
		return ReturnObject::success($token)->send();
	}
}
