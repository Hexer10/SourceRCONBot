<?php
/**
 * This code is free software; you can redistribute it and/or modify it under
 * the terms of the new BSD License.
 *
 * Copyright (c) 2010-2015, Sebastian Staudt
 *
 * @author  Sebastian Staudt
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package steam-condenser
 */

error_reporting(E_ERROR | E_WARNING | E_PARSE);

define('STEAM_CONDENSER_PATH', dirname(__FILE__) . '/');
define('STEAM_CONDENSER_VERSION', '1.3.10');

require_once STEAM_CONDENSER_PATH . 'steam/servers/GoldSrcServer.php';
require_once STEAM_CONDENSER_PATH . 'steam/servers/MasterServer.php';
require_once STEAM_CONDENSER_PATH . 'steam/servers/SourceServer.php';
require_once STEAM_CONDENSER_PATH . 'steam/community/SteamId.php';
