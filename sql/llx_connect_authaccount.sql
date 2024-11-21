-- Copyright (C) 2024 SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_connect_authaccount(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	userId integer, 
	type varchar(255), 
	provider varchar(255), 
	providerAccountId varchar(255), 
	refresh_token varchar(255), 
	access_token varchar(255), 
	expires_at integer, 
	token_type varchar(255), 
	scope varchar(255), 
	id_token varchar(255), 
	session_state varchar(255)
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
