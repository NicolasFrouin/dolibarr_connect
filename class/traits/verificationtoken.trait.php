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

dol_include_once('/connect/class/verificationtoken.class.php');
dol_include_once('/connect/class/lib/return.class.php');

trait TVerificationToken
{
    /** 
     * @var VerificationToken $verificationtoken {@type VerificationToken}
     */
    public $verificationtoken;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->verificationtoken = new VerificationToken($this->db);
    }

    /**
     * Get properties of a verificationtoken object
     *
     * Return an array with verificationtoken information
     *
     * @param	int		$id				ID of verificationtoken
     * @return  Object					Object with cleaned properties
     *
     * @url	GET verificationtokens/{id}
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 404 Not found
     */
    public function getVerificationToken($id)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'verificationtoken', 'read')) {
            throw new RestException(403);
        }
        if (!DolibarrApi::_checkAccessToResource('verificationtoken', $id, 'connect_verificationtoken')) {
            throw new RestException(403, 'Access to instance id=' . $id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
        }

        $result = $this->verificationtoken->fetch($id);
        if (!$result) {
            throw new RestException(404, 'VerificationToken not found');
        }

        return $this->_cleanObjectDatas($this->verificationtoken);
    }

    /**
     * List verificationtokens
     *
     * Get a list of verificationtokens
     *
     * @param string		   $sortfield			Sort field
     * @param string		   $sortorder			Sort order
     * @param int			   $limit				Limit for list
     * @param int			   $page				Page number
     * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
     * @param string		   $properties			Restrict the data returned to these properties. Ignored if empty. Comma separated list of properties names
     * @return  array                               Array of order objects
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 503 System error
     *
     * @url	GET /verificationtokens/
     */
    public function indexVerificationToken($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
    {
        $obj_ret = array();
        $tmpobject = new VerificationToken($this->db);

        if (!DolibarrApiAccess::$user->hasRight('connect', 'verificationtoken', 'read')) {
            throw new RestException(403);
        }

        $socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : 0;

        $restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

        // If the internal user must only see his customers, force searching by him
        $search_sale = 0;
        if ($restrictonsocid && !DolibarrApiAccess::$user->hasRight('societe', 'client', 'voir') && !$socid) {
            $search_sale = DolibarrApiAccess::$user->id;
        }
        if (!isModEnabled('societe')) {
            $search_sale = 0; // If module thirdparty not enabled, sale representative is something that does not exists
        }

        $sql = "SELECT t.rowid";
        $sql .= " FROM " . MAIN_DB_PREFIX . $tmpobject->table_element . " AS t";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . $tmpobject->table_element . "_extrafields AS ef ON (ef.fk_object = t.rowid)"; // Modification VMR Global Solutions to include extrafields as search parameters in the API GET call, so we will be able to filter on extrafields
        $sql .= " WHERE 1 = 1";
        if ($tmpobject->ismultientitymanaged) {
            $sql .= ' AND t.entity IN (' . getEntity($tmpobject->element) . ')';
        }
        if ($restrictonsocid && $socid) {
            $sql .= " AND t.fk_soc = " . ((int) $socid);
        }
        // Search on sale representative
        if ($search_sale && $search_sale != '-1') {
            if ($search_sale == -2) {
                $sql .= " AND NOT EXISTS (SELECT sc.fk_soc FROM " . MAIN_DB_PREFIX . "societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc)";
            } elseif ($search_sale > 0) {
                $sql .= " AND EXISTS (SELECT sc.fk_soc FROM " . MAIN_DB_PREFIX . "societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user = " . ((int) $search_sale) . ")";
            }
        }
        if ($sqlfilters) {
            $errormessage = '';
            $sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
            if ($errormessage) {
                throw new RestException(400, 'Error when validating parameter sqlfilters -> ' . $errormessage);
            }
        }

        $sql .= $this->db->order($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql .= $this->db->plimit($limit + 1, $offset);
        }

        $result = $this->db->query($sql);
        $i = 0;
        if ($result) {
            $num = $this->db->num_rows($result);
            while ($i < $num) {
                $obj = $this->db->fetch_object($result);
                $tmp_object = new VerificationToken($this->db);
                if ($tmp_object->fetch($obj->rowid)) {
                    $obj_ret[] = $this->_filterObjectProperties($this->_cleanObjectDatas($tmp_object), $properties);
                }
                $i++;
            }
        } else {
            throw new RestException(503, 'Error when retrieving verificationtoken list: ' . $this->db->lasterror());
        }

        return $obj_ret;
    }

    /**
     * Create verificationtoken object
     *
     * @param array $request_data   Request datas
     * @return int  				ID of verificationtoken
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 500 System error
     *
     * @url	POST verificationtokens/
     */
    public function postVerificationToken($request_data = null)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'verificationtoken', 'write')) {
            throw new RestException(403);
        }

        // Check mandatory fields
        $result = $this->_validateVerificationToken($request_data);

        foreach ($request_data as $field => $value) {
            if ($field === 'caller') {
                // Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
                $this->verificationtoken->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
                continue;
            }

            if ($field == 'array_options' && is_array($value)) {
                foreach ($value as $index => $val) {
                    $this->verificationtoken->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->verificationtoken);
                }
                continue;
            }

            $this->verificationtoken->$field = $this->_checkValForAPI($field, $value, $this->verificationtoken);
        }

        // Clean data
        // $this->verificationtoken->abc = sanitizeVal($this->verificationtoken->abc, 'alphanohtml');

        if ($this->verificationtoken->create(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, "Error creating VerificationToken", array_merge(array($this->verificationtoken->error), $this->verificationtoken->errors));
        }
        return $this->verificationtoken->id;
    }

    /**
     * Update verificationtoken
     *
     * @param 	int   		$id             Id of verificationtoken to update
     * @param 	array 		$request_data   Datas
     * @return 	Object						Object after update
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 404 Not found
     * @throws RestException 500 System error
     *
     * @url	PUT verificationtokens/{id}
     */
    public function putVerificationToken($id, $request_data = null)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'verificationtoken', 'write')) {
            throw new RestException(403);
        }
        if (!DolibarrApi::_checkAccessToResource('verificationtoken', $id, 'connect_verificationtoken')) {
            throw new RestException(403, 'Access to instance id=' . $this->verificationtoken->id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
        }

        $result = $this->verificationtoken->fetch($id);
        if (!$result) {
            throw new RestException(404, 'VerificationToken not found');
        }

        foreach ($request_data as $field => $value) {
            if ($field == 'id') {
                continue;
            }
            if ($field === 'caller') {
                // Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
                $this->verificationtoken->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
                continue;
            }

            if ($field == 'array_options' && is_array($value)) {
                foreach ($value as $index => $val) {
                    $this->verificationtoken->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->verificationtoken);
                }
                continue;
            }

            $this->verificationtoken->$field = $this->_checkValForAPI($field, $value, $this->verificationtoken);
        }

        // Clean data
        // $this->verificationtoken->abc = sanitizeVal($this->verificationtoken->abc, 'alphanohtml');

        if ($this->verificationtoken->update(DolibarrApiAccess::$user, false) > 0) {
            return $this->getVerificationToken($id);
        } else {
            throw new RestException(500, $this->verificationtoken->error);
        }
    }

    /**
     * Delete verificationtoken
     *
     * @param   int     $id   VerificationToken ID
     * @return  array
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 404 Not found
     * @throws RestException 409 Nothing to do
     * @throws RestException 500 System error
     *
     * @url	DELETE verificationtokens/{id}
     */
    public function deleteVerificationToken($id)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'verificationtoken', 'delete')) {
            throw new RestException(403);
        }
        if (!DolibarrApi::_checkAccessToResource('verificationtoken', $id, 'connect_verificationtoken')) {
            throw new RestException(403, 'Access to instance id=' . $this->verificationtoken->id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
        }

        $result = $this->verificationtoken->fetch($id);
        if (!$result) {
            throw new RestException(404, 'VerificationToken not found');
        }

        if ($this->verificationtoken->delete(DolibarrApiAccess::$user) == 0) {
            throw new RestException(409, 'Error when deleting VerificationToken : ' . $this->verificationtoken->error);
        } elseif ($this->verificationtoken->delete(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, 'Error when deleting VerificationToken : ' . $this->verificationtoken->error);
        }

        return array(
            'success' => array(
                'code' => 200,
                'message' => 'VerificationToken deleted'
            )
        );
    }

    /**
     * Create a verification token and return only OAuth data
     * 
     * @param   array   $data   Data to create the verification token
     * 
     * @return  array           Array with OAuth data
     * 
     * @url POST /oauth/verificationtokens
     */
    public function createVerificationToken($data)
    {
        $verifTokenRes = $this->postVerificationToken($data);

        return VerificationToken::getOnlyOAuthData($this->getVerificationToken($verifTokenRes));
    }

    /**
     * Use a verification token
     * 
     * Delete the verification token and return only its OAuth data
     * 
     * @param   string  $identifier     Identifier of the verification token
     * 
     * @return  array                   Array with OAuth data
     * 
     * @url POST /connect/oauth/verificationtokens/use/{identifier}/{token}
     */
    public function useVerificationToken($identifier, $token)
    {
        $verifToken = new VerificationToken($this->db);

        $fetchRes = $verifToken->fetchByIdentifierAndToken($identifier, $token);

        if ($fetchRes <= 0) {
            return ReturnObject::error("ERR_VERIFICATION_TOKEN_NOT_FOUND", "Verification token not found", 404)->send();
        }

        $return = new ReturnObject(VerificationToken::getOnlyOAuthData($verifToken));

        $deleteRes = $verifToken->delete(DolibarrApiAccess::$user);

        if ($deleteRes < 0) {
            $return->setError("ERR_VERIFICATION_TOKEN_DELETE", "Error when deleting verification token", 500);
        }

        return $return->send();
    }

    /**
     * Validate fields before create or update object
     *
     * @param	array		$data   Array of data to validate
     * @return	array
     *
     * @throws	RestException
     */
    private function _validateVerificationToken($data)
    {
        $verificationtoken = array();
        foreach ($this->verificationtoken->fields as $field => $propfield) {
            if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) {
                continue; // Not a mandatory field
            }
            if (!isset($data[$field])) {
                throw new RestException(400, "$field field missing");
            }
            $verificationtoken[$field] = $data[$field];
        }
        return $verificationtoken;
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
