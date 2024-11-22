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

dol_include_once('/connect/class/session.class.php');
dol_include_once('/connect/class/lib/return.class.php');

trait TSession
{
    /** 
     * @var Session $session {@type Session} 
     */
    public $session;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->session = new Session($this->db);
    }

    /**
     * Get properties of a session object
     *
     * Return an array with session information
     *
     * @param	int		$id				ID of session
     * @return  Object					Object with cleaned properties
     *
     * @url	GET sessions/{id}
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 404 Not found
     */
    public function getSession($id)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'session', 'read')) {
            throw new RestException(403);
        }
        if (!DolibarrApi::_checkAccessToResource('session', $id, 'connect_session')) {
            throw new RestException(403, 'Access to instance id=' . $id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
        }

        $result = $this->session->fetch($id);
        if (!$result) {
            throw new RestException(404, 'Session not found');
        }

        return $this->_cleanObjectDatas($this->session);
    }

    /**
     * List sessions
     *
     * Get a list of sessions
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
     * @url	GET /sessions/
     */
    public function indexSession($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '', $properties = '')
    {
        $obj_ret = array();
        $tmpobject = new Session($this->db);

        if (!DolibarrApiAccess::$user->hasRight('connect', 'session', 'read')) {
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
                $tmp_object = new Session($this->db);
                if ($tmp_object->fetch($obj->rowid)) {
                    $obj_ret[] = $this->_filterObjectProperties($this->_cleanObjectDatas($tmp_object), $properties);
                }
                $i++;
            }
        } else {
            throw new RestException(503, 'Error when retrieving session list: ' . $this->db->lasterror());
        }

        return $obj_ret;
    }

    /**
     * Create session object
     *
     * @param array $request_data   Request datas
     * @return int  				ID of session
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 500 System error
     *
     * @url	POST sessions/
     */
    public function postSession($request_data = null)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'session', 'write')) {
            throw new RestException(403);
        }

        // Check mandatory fields
        $result = $this->_validateSession($request_data);

        foreach ($request_data as $field => $value) {
            if ($field === 'caller') {
                // Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
                $this->session->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
                continue;
            }

            if ($field == 'array_options' && is_array($value)) {
                foreach ($value as $index => $val) {
                    $this->session->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->session);
                }
                continue;
            }

            $this->session->$field = $this->_checkValForAPI($field, $value, $this->session);
        }

        // Clean data
        // $this->session->abc = sanitizeVal($this->session->abc, 'alphanohtml');

        if ($this->session->create(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, "Error creating Session", array_merge(array($this->session->error), $this->session->errors));
        }
        return $this->session->id;
    }

    /**
     * Update session
     *
     * @param 	int   		$id             Id of session to update
     * @param 	array 		$request_data   Datas
     * @return 	Object						Object after update
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 404 Not found
     * @throws RestException 500 System error
     *
     * @url	PUT sessions/{id}
     */
    public function putSession($id, $request_data = null)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'session', 'write')) {
            throw new RestException(403);
        }
        if (!DolibarrApi::_checkAccessToResource('session', $id, 'connect_session')) {
            throw new RestException(403, 'Access to instance id=' . $this->session->id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
        }

        $result = $this->session->fetch($id);
        if (!$result) {
            throw new RestException(404, 'Session not found');
        }

        foreach ($request_data as $field => $value) {
            if ($field == 'id') {
                continue;
            }
            if ($field === 'caller') {
                // Add a mention of caller so on trigger called after action, we can filter to avoid a loop if we try to sync back again with the caller
                $this->session->context['caller'] = sanitizeVal($request_data['caller'], 'aZ09');
                continue;
            }

            if ($field == 'array_options' && is_array($value)) {
                foreach ($value as $index => $val) {
                    $this->session->array_options[$index] = $this->_checkValForAPI('extrafields', $val, $this->session);
                }
                continue;
            }

            $this->session->$field = $this->_checkValForAPI($field, $value, $this->session);
        }

        // Clean data
        // $this->session->abc = sanitizeVal($this->session->abc, 'alphanohtml');

        if ($this->session->update(DolibarrApiAccess::$user, false) > 0) {
            return $this->getSession($id);
        } else {
            throw new RestException(500, $this->session->error);
        }
    }

    /**
     * Delete session
     *
     * @param   int     $id   Session ID
     * @return  array
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 404 Not found
     * @throws RestException 409 Nothing to do
     * @throws RestException 500 System error
     *
     * @url	DELETE sessions/{id}
     */
    public function deleteSession($id)
    {
        if (!DolibarrApiAccess::$user->hasRight('connect', 'session', 'delete')) {
            throw new RestException(403);
        }
        if (!DolibarrApi::_checkAccessToResource('session', $id, 'connect_session')) {
            throw new RestException(403, 'Access to instance id=' . $this->session->id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
        }

        $result = $this->session->fetch($id);
        if (!$result) {
            throw new RestException(404, 'Session not found');
        }

        if ($this->session->delete(DolibarrApiAccess::$user) == 0) {
            throw new RestException(409, 'Error when deleting Session : ' . $this->session->error);
        } elseif ($this->session->delete(DolibarrApiAccess::$user) < 0) {
            throw new RestException(500, 'Error when deleting Session : ' . $this->session->error);
        }

        return array(
            'success' => array(
                'code' => 200,
                'message' => 'Session deleted'
            )
        );
    }

    /**
     * Get a session and its user by their `$sessionToken`
     *
     * @param   string  $sessionToken   Session token
     * 
     * @return  array                   Array with session and user data as `{session: Session, user: User}`
     * 
     * @url GET /oauth/sessions/anduser/{sessionToken}
     */
    public function getSessionAndUser($sessionToken)
    {
        dol_include_once("/connect/class/lib/override/user/api_users.class.php");

        $return = new ReturnObject(["session" => null, "user" => null]);

        $session = new Session($this->db);
        $sessionRes = $session->fetchBySessionToken($sessionToken);

        if ($sessionRes <= 0) {
            $error = $sessionRes == 0 ? "ERR_SESSION_NOT_FOUND" : "ERR_SESSION_ERROR";
            $errorMessage = $sessionRes == 0 ? "Session not found" : "Error when fetching session";
            $errorCode = $sessionRes == 0 ? 404 : 500;
            return $return->setError($error, $errorMessage, $errorCode)->send();
        }

        $return->data["session"] = Session::getOnlyOAuthData($session);

        $user = new User($this->db);
        $userRes = $user->fetch($session->fk_user);

        if ($userRes <= 0) {
            $error = $userRes == 0 ? "ERR_USER_NOT_FOUND" : "ERR_USER_ERROR";
            $errorMessage = $userRes == 0 ? "User not found" : "Error when fetching user";
            $errorCode = $userRes == 0 ? 404 : 500;
            return $return->setError($error, $errorMessage, $errorCode)->send();
        }

        $return->data["user"] = CustomApiUsers::getOnlyOAuthData($user);

        return $return->send();
    }

    /**
     * Create a session and return only OAuth data
     * 
     * @param   array   $request_data   Request data
     * 
     * @return  array                   Array with session data
     * 
     * @url POST /oauth/sessions
     */
    public function createSession($request_data = null)
    {
        $createRes = $this->postSession($request_data);

        return Session::getOnlyOAuthData($this->getSession($createRes));
    }

    /**
     * Update a session and return only OAuth data
     * 
     * @param   string      $sessionToken   Session ID
     * @param   array       $request_data   Request data
     * 
     * @return  array                       Array with session data
     * 
     * @url PUT /oauth/sessions/{sessionToken}
     */
    public function updateSession($sessionToken, $request_data = null)
    {
        $session = new Session($this->db);
        $fetchRes = $session->fetchBySessionToken($sessionToken);

        if ($fetchRes <= 0) {
            $error = $fetchRes == 0 ? "ERR_SESSION_NOT_FOUND" : "ERR_SESSION_ERROR";
            $errorMessage = $fetchRes == 0 ? "Session not found" : "Error when fetching session";
            $errorCode = $fetchRes == 0 ? 404 : 500;
            return ReturnObject::error($error, $errorMessage, $errorCode)->send();
        }

        return Session::getOnlyOAuthData($this->putSession($session->id, $request_data));
    }

    /**
     * Validate fields before create or update object
     *
     * @param	array		$data   Array of data to validate
     * @return	array
     *
     * @throws	RestException
     */
    private function _validateSession($data)
    {
        $session = array();
        foreach ($this->session->fields as $field => $propfield) {
            if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) {
                continue; // Not a mandatory field
            }
            if (!isset($data[$field])) {
                throw new RestException(400, "$field field missing");
            }
            $session[$field] = $data[$field];
        }
        return $session;
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
