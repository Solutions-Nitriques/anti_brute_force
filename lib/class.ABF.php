<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT
	*/
	
	require_once (TOOLKIT . '/class.extensionmanager.php');

	/**
	 *
	 * Symphony CMS leaverages the Decorator pattern with their <code>Extension</code> class.
	 * This class is a Facade that implements <code>Singleton</code> and the methods
	 * needed by the Decorator. It offers its methods via the <code>instance()</code> satic function
	 * @author nicolasbrassard
	 *
	 */
	class ABF implements Singleton {
		
		/**
		 * Name of the extension
		 * @var string
		 */
		const EXT_NAME = 'Anti Brute Force';

		/**
		 * Key of the length setting
		 * @var string
		 */
		const SETTING_LENGTH = 'length';

		/**
		 * Key of the failed count setting
		 * @var string
		 */
		const SETTING_FAILED_COUNT = 'failed-count';

		/**
		 * Key of the auto unband via email setting
		 * @var string
		 */
		const SETTING_AUTO_UNBAN = 'auto-unban';

		/**
		 * Key of the Grey list threshold setting
		 * @var string
		 */
		const SETTING_GL_THRESHOLD = 'gl-threshold';

		/**
		 * Key of the Grey list duration setting
		 * @var string
		 */
		const SETTING_GL_DURATION = 'gl-duration';

		/**
		 * Key of the group of setting
		 * @var string
		 */
		const SETTING_GROUP = 'anti-brute-force';

		/**
		 * Defaults settings values
		 * @var array->array
		 */
		private $DEFAULTS = array (
						ABF::SETTING_GROUP => array (
							ABF::SETTING_LENGTH => 60,
							ABF::SETTING_FAILED_COUNT => 5,
							ABF::SETTING_AUTO_UNBAN => 'off',
							ABF::SETTING_GL_THRESHOLD => 5,
							ABF::SETTING_GL_DURATION => 30
						)
					);

		/**
		 * Variable that holds the settings values
		 */
		private $_setings = array();

		/**
		 * Variable that hold true if the extension is installed
		 * @var boolean
		 */
		private $_isInstalled = false;

		/**
		 * Short hand for the tables name
		 * @var string
		 */
		private $TBL_ABF = 'tbl_anti_brute_force';
		private $TBL_ABF_WL = 'tbl_anti_brute_force_wl';
		private $TBL_ABF_GL = 'tbl_anti_brute_force_gl';
		private $TBL_ABF_BL = 'tbl_anti_brute_force_bl';

		/**
		 * All the different colors of the colored lists
		 * @var array
		 * @property
		 */
		public $COLORS = array('black', 'grey', 'white');

		/**
		 *
		 * Holds the path to the "send me unband link" page
		 * @var string
		 */
		const UNBAND_LINK =  '/extension/anti_brute_force/login/';

		/**
		 * Singleton implementation
		 */
		private static $I = null;

		/**
		 *
		 * Singleton method
		 * @return ABF
		 */
		public static function instance() {
			if (self::$I == null) {
				self::$I = new ABF();
			}
			return self::$I;
		}

		// do not allow external creation
		private function __construct(){
			$s = Symphony::Configuration()->get();
			$this->_setings = $s[ABF::SETTING_GROUP];
			unset($s);

			$status = Symphony::ExtensionManager()->fetchStatus(ABF::EXT_NAME);
			$this->_isInstalled = ($status == EXTENSION_ENABLED || $status == EXTENSION_REQUIRES_UPDATE);

			// only if already installed
			if ($this->_isInstalled) {
				// assure access to settings
				// fail is not settings, since this is a security software
				if (count($this->_setings) < 1) {
					throw new Exception('Can not load settings. Can not continue.');
				}
			}
		}


		/**
		 * FAILURES (BANNED IP) Public methods
		 */

		/**
		 * Do the actual ban check: throw exception if banned/black listed
		 */
		public function doBanCheck() {
			// check if not white listed
			if ($this->_isInstalled && !$this->isWhiteListed()) {

				// check if blacklisted
				if ($this->isBlackListed()) {
					// block access
					$this->throwBlackListedException();
				}

				// check if banned
				if ($this->isCurrentlyBanned()) {
					// block access
					$this->throwBannedException();
				}
			}
		}

		/**
		 *
		 * Check to see if the current user IP address is banned,
		 * based on the parameters set in Configuration
		 */
		public function isCurrentlyBanned($ip='') {
			$length = $this->_setings[ABF::SETTING_LENGTH];
			$failedCount = $this->_setings[ABF::SETTING_FAILED_COUNT];
			$results = $this->getFailureByIp($ip, "
				AND UNIX_TIMESTAMP(LastAttempt) + (60 * $length) > UNIX_TIMESTAMP()
				AND FailedCount >= $failedCount");

			return count($results) > 0;
		}

		/**
		 *
		 * Register a failure - insert or update - for a IP
		 * @param string $username - the username input
		 * @param string $source - the source of the ban, normally the name of the extension
		 * @param string $ip @optional - will take current user's ip
		 */
		public function registerFailure($username, $source, $ip='') {
			$ip = $this->getIP($ip);
			$username = MySQL::cleanValue($username);
			$source = MySQL::cleanValue($source);
			$ua = MySQL::cleanValue($this->getUA());
			$results = $this->getFailureByIp($ip);
			$ret = false;

			if ($results != null && count($results) > 0) {
				// UPDATE
				$ret = Symphony::Database()->query("
					UPDATE $this->TBL_ABF
						SET `LastAttempt` = NOW(),
						    `FailedCount` = `FailedCount` + 1,
						    `Username` = '$username',
						    `UA` = '$ua',
						    `Source` = '$source',
						    `Hash` = UUID()
						WHERE IP = '$ip'
						LIMIT 1
				");

			} else {
				// INSERT
				$ret = Symphony::Database()->query("
					INSERT INTO $this->TBL_ABF
						(`IP`, `LastAttempt`, `Username`, `FailedCount`, `UA`, `Source`, `Hash`)
						VALUES
						('$ip', NOW(),        '$username', 1,            '$ua','$source', UUID())
				");
			}

			return $ret;
		}

		/**
		 *
		 * Utility function that throw a properly formatted SymphonyErrorPage Exception
		 * @throws SymphonyErrorPage
		 */
		public function throwBannedException() {
			$length = $this->_setings[ABF::SETTING_LENGTH];
			$useUnbanViaEmail = $this->_setings[ABF::SETTING_AUTO_UNBAN];
			$msg =
				__('Your IP address is currently banned, due to typing too many wrong usernames/passwords')
				. '<br/><br/>' .
				__('You can ask your administrator to unlock your account or wait %s minutes', array($length));

			if ($useUnbanViaEmail == true || $useUnbanViaEmail == 'Yes') {
				$msg .= ('<br/><br/>' . __('Alternatively, you can <a href="%s">un-ban your IP by email</a>.', array(SYMPHONY_URL . self::UNBAND_LINK)));
			}

			// banned - throw exception
			throw new SymphonyErrorPage($msg, __('Banned IP address'));
		}

		/**
		 *
		 * Unregister IP from the banned table - even if max failed count is not reach
		 * @param string $filter @optional will take current user's ip
		 * can be the IP address or the hash value
		 */
		public function unregisterFailure($filter='') {
			$filter = MySQL::cleanValue($this->getIP($filter));
			return Symphony::Database()->delete($this->TBL_ABF, "IP = '$filter' OR Hash = '$filter'");
		}

		/**
		 *
		 * Delete expired entries
		 */
		public function removeExpiredEntries() {
			// in minutes
			if ($this->_isInstalled) {
				$length = $this->_setings[ABF::SETTING_LENGTH];
				return Symphony::Database()->delete($this->TBL_ABF, "UNIX_TIMESTAMP(LastAttempt) + (60 * $length) < UNIX_TIMESTAMP()");
			}
		}




		/**
		 * Database Data queries - COLORED (B/G/W) Public methods
		 */

		public function registerToBlackList($source, $ip='') {
			return $this->__registerToList($this->TBL_ABF_BL, $source, $ip);
		}
		public function registerToGreyList($source, $ip='') {
			return $this->__registerToList($this->TBL_ABF_GL, $source, $ip);
		}
		public function registerToWhiteList($source, $ip='') {
			return $this->__registerToList($this->TBL_ABF_WL, $source, $ip);
		}

		public function registerToList($color, $source, $ip='') {
			return $this->__registerToList($this->getTableName($color), $source, $ip);
		}

		private function __registerToList($tbl, $source, $ip='') {
			$ip = $this->getIP($ip);
			$results = $this->__isListed($tbl, $ip);
			$isGrey = $tbl == $this->TBL_ABF_GL;
			$ret = false;

			// do not re-register existing entries
			if ($results != null && count($results) > 0) {
				if ($isGrey) {
					$ret = $this->incrementGreyList($ip);
				}

			} else {
				// INSERT -- grey list will get the default values for others columns
				$ret = Symphony::Database()->query("
					INSERT INTO $tbl
						(`IP`, `DateCreated`, `Source`)
						VALUES
						('$ip', NOW(),        '$source')
				");
			}

			return $ret;
		}

		public function moveGreyToBlack($source, $ip='') {
			$grey = $this->getGreyListEntriesByIP($ip);
			if (is_array($grey) && !empty($grey)) {
				if ($grey[0]->FailedCount >= $this->_setings[ABF::SETTING_GL_THRESHOLD]) {
					$this->registerToBlackList($source, $ip);
				}
			}
		}

		private function incrementGreyList($ip) {
			$tbl = $this->TBL_ABF_GL;
			// UPDATE -- only Grey list
			return Symphony::Database()->query("
				UPDATE $tbl
					SET `FailedCount` = `FailedCount` + 1
					WHERE IP = '$ip'
					LIMIT 1
			");
		}

		public function isBlackListed($ip='') {
			return $this->__isListed($this->TBL_ABF_BL, $ip);
		}

		public function isGreyListed($ip='') {
			return $this->__isListed($this->TBL_ABF_GL, $ip);
		}

		public function isWhiteListed($ip='') {
			return $this->__isListed($this->TBL_ABF_WL, $ip);
		}

		public function isListed($color, $ip='') {
			return $this->__isListed($this->getTableName($color), $ip);
		}

		private function __isListed($tbl, $ip='') {
			$ip = $this->getIP($ip);
			return count($this->__getListEntriesByIp($tbl, $ip)) > 0;
		}

		public function unregisterToList($color, $ip='') {
			return $this->__unregisterToList($this->getTableName($color), $ip);
		}

		private function __unregisterToList($tbl, $ip='') {
			$filter = MySQL::cleanValue($this->getIP($ip));
			return Symphony::Database()->delete($tbl, "IP = '$filter'");
		}

		public function removeExpiredListEntries() {
			// in days
			$length = $this->_setings[ABF::SETTING_GL_DURATION];
			return Symphony::Database()->delete($this->TBL_ABF_GL, "UNIX_TIMESTAMP(DateCreated) + (60 * 60 * 24 * $length) < UNIX_TIMESTAMP()");
		}

		/**
		 *
		 * Utility function that throw a properly formatted SymphonyErrorPage Exception
		 * @param string $length - length of block in minutes
		 * @param boolean
		 * @throws SymphonyErrorPage
		 */
		public function throwBlackListedException() {
			$msg =
				__('Your IP address is currently <strong>black listed</strong>, due to too many bans.')
				. '<br/><br/>' .
				__('Ask your administrator to unlock your IP.');

			// banned - throw exception
			throw new SymphonyErrorPage($msg, __('Black listed IP address'));
		}



		/**
		 * Database Data queries - FAILURES
		 */

		/**
		 *
		 * Method that returns failures based on IP address and other filters
		 * @param string $ip the ip in the select query
		 * @param string $additionalWhere @optional additional SQL filters
		 */
		public function getFailureByIp($ip='', $additionalWhere='') {
			$ip = $this->getIP($ip);
			$where = "IP = '$ip'";
			if (strlen($additionalWhere) > 0) {
				$where .= $additionalWhere;
			}
			$sql ="
				SELECT * FROM $this->TBL_ABF WHERE $where LIMIT 1
			" ;

			$rets = array();

			if (Symphony::Database()->query($sql)) {
				$rets = Symphony::Database()->fetch();
			}

			return $rets;
		}

		/**
		 *
		 * Method that returns all failures, optionally ordered
		 * @param string $orderedBy @optional
		 */
		public function getFailures($orderedBy='') {
			$order = '';
			if (strlen($orderedBy) > 0) {
				$order .= (' ORDER BY ' . $orderedBy);
			}
			$sql ="
				SELECT * FROM $this->TBL_ABF $order
			" ;

			$rets = array();

			if (Symphony::Database()->query($sql)) {
				$rets = Symphony::Database()->fetch();
			}

			return $rets;
		}

		public function getBlackListEntriesByIP($ip='', $additionalWhere='') {
			return $this->__getListEntriesByIp($this->TBL_ABF_BL, $ip, $additionalWhere);
		}

		public function getGreyListEntriesByIP($ip='', $additionalWhere='') {
			return $this->__getListEntriesByIp($this->TBL_ABF_GL, $ip, $additionalWhere);
		}

		public function getWhiteListEntriesByIP($ip='', $additionalWhere='') {
			return $this->__getListEntriesByIp($this->TBL_ABF_WL, $ip, $additionalWhere);
		}

		public function getListEntriesByIP($color, $ip='', $additionalWhere='') {
			return $this->__getListEntriesByIp($this->getTableName($color), $ip, $additionalWhere);
		}

		private function __getListEntriesByIp($tbl, $ip='', $additionalWhere='') {
			$ip = $this->getIP($ip);
			$where = "IP = '$ip'";
			if (strlen($additionalWhere) > 0) {
				$where .= $additionalWhere;
			}
			$sql ="
				SELECT * FROM $tbl WHERE $where LIMIT 1
			" ;

			$rets = array();

			if (Symphony::Database()->query($sql)) {
				$rets = Symphony::Database()->fetch();
			}

			return $rets;
		}

		public function getListEntries($color) {
			return $this->__getListEntries($this->getTableName($color));
		}

		private function __getListEntries($tbl, $where='', $order='IP ASC') {
			if (strlen($where) > 0) {
				$where = 'WHERE ' . $where;
			}
			$sql ="
				SELECT * FROM $tbl $where ORDER BY $order
			" ;

			$rets = array();

			if (Symphony::Database()->query($sql)) {
				$rets = Symphony::Database()->fetch();
			}

			return $rets;
		}


		/**
		 * Utilities
		 */
		private function getIP($ip='') {
			// ip is at least 8 char
			// hash is 36 char
			return strlen($ip) < 8 ? $_SERVER["REMOTE_ADDR"]: $ip;
		}

		private function getUA() {
			return $_ENV["HTTP_USER_AGENT"];
		}

		private function getTableName($color) {
			$tbl = '';
			switch ($color) {
				case $this->COLORS[0]:
					$tbl = $this->TBL_ABF_BL;
					break;
				case $this->COLORS[1]:
					$tbl = $this->TBL_ABF_GL;
					break;
				case $this->COLORS[2]:
					$tbl = $this->TBL_ABF_WL;
					break;
				default:
					throw new Exception(vsprintf("'%s' is not a know color", $color == null ? 'NULL' : $color));
			}
			return $tbl;
		}




		/**
		 * SETTINGS
		 */

		/**
		 *
		 * Utility function that returns settings from this extensions settings group
		 * @param string $key
		 */
		public function getConfigVal($key) {
			return $this->_setings[$key];
		}

		/**
		 *
		 * Save one parameter, passed in the $context array
		 * @param array $context
		 * @param array $errors
		 * @param string $key
		 * @param string $autoSave @optional
		 */
		public function setConfigVal(&$context, &$errors, $key, $autoSave = true, $type = 'text'){
			// get the input
			$input = $context['settings'][ABF::SETTING_GROUP][$key];
			$iVal = intval($input);

			// verify it is a good domain
			if (strlen($input) > 0 && is_int($iVal) && $iVal > 0) {

				// set config                    (name, value, group)
				Symphony::Configuration()->set($key, $iVal, ABF::SETTING_GROUP);

				// save it
				if ($autoSave) {
					Administration::instance()->saveConfig();
				}

			} else {
				// don't save

				// set error message
				$error = __('"%s" is not a valid positive integer',  array($input));

				// append to local array
				$errors[$key] = $error;

				// add an error into the stack
				$context['errors'][ABF::SETTING_GROUP][$key] = $error;
			}
		}


		/**
		 * Database Data Definition Queries
		 */

		/**
		 *
		 * This method will install the plugin
		 */
		public function install(&$ext_driver) {
			$ret = $this->install_v1_0() && $this->install_v1_1();
			if ( $ret ) {
				// set default values
				$pseudo_context = array(
					'settings' => $this->DEFAULTS
				);

				// *** load settings in memory
				// Even if we just installed the ext, a ban check will be done,
				// so we need settings: use defaults
				$this->_setings = empty($this->_setings) ? $this->DEFAULTS : $this->_setings;

				$ext_driver->save($pseudo_context);
			}
			return $ret;
		}

		private function install_v1_0() {
			$sql = "
				CREATE TABLE IF NOT EXISTS $this->TBL_ABF(
					`IP` VARCHAR( 16 ) NOT NULL ,
					`LastAttempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
					`FailedCount` INT( 5 ) unsigned NOT NULL DEFAULT  '1',
					`UA` VARCHAR( 1024 ) NULL,
					`Username` VARCHAR( 100 ) NULL,
					`Source` VARCHAR( 100 ) NULL,
					`Hash` CHAR( 36 ) NOT NULL,
					PRIMARY KEY (  `IP` )
				) ENGINE = MYISAM
			";

			return Symphony::Database()->query($sql);
		}

		private function install_v1_1() {
			// GREY
			$sql = "
				CREATE TABLE IF NOT EXISTS $this->TBL_ABF_GL (
					`IP` VARCHAR( 16 ) NOT NULL ,
					`DateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
					`FailedCount` INT( 5 ) unsigned NOT NULL DEFAULT  '1',
					`Source` VARCHAR( 100 ) NULL,
					PRIMARY KEY (  `IP` )
				) ENGINE = MYISAM
			";

			$retGL = Symphony::Database()->query($sql);

			//BLACK
			$sql = "
				CREATE TABLE IF NOT EXISTS $this->TBL_ABF_BL (
					`IP` VARCHAR( 16 ) NOT NULL ,
					`DateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
					`Source` VARCHAR( 100 ) NULL,
					PRIMARY KEY (  `IP` )
				) ENGINE = MYISAM
			";

			$retBL = Symphony::Database()->query($sql);

			// WHITE
			$sql = "
				CREATE TABLE IF NOT EXISTS $this->TBL_ABF_WL (
					`IP` VARCHAR( 16 ) NOT NULL ,
					`DateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
					`Source` VARCHAR( 100 ) NULL,
					PRIMARY KEY (  `IP` )
				) ENGINE = MYISAM
			";

			$retWL = Symphony::Database()->query($sql);

			return $retGL && $retBL && $retWL;
		}

		/**
		 *
		 * This methode will update the extension according to the
		 * previous and current version parameters.
		 * @param string $previousVersion
		 * @param string $currentVersion
		 */
		public function update($previousVersion, $currentVersion) {
			switch ($previousVersion) {
				case $currentVersion:
					break;
				case '1.1':
					break;
				case '1.0':
					$this->install_v1_1();
					break;
				default:
					return $this->install();
			}
			return false;
		}

		/**
		 *
		 * This method will uninstall the extension
		 */
		public function uninstall() {
			// Banned IPs
			$sql = "
				DROP TABLE IF EXISTS $this->TBL_ABF
			";

			$retABF = Symphony::Database()->query($sql);

			// Black
			$sql = "
				DROP TABLE IF EXISTS $this->TBL_ABF_BL
			";

			$retABF_BL = Symphony::Database()->query($sql);

			// Grey
			$sql = "
				DROP TABLE IF EXISTS $this->TBL_ABF_GL
			";

			$retABF_GL = Symphony::Database()->query($sql);

			// White
			$sql = "
				DROP TABLE IF EXISTS $this->TBL_ABF_WL
			";

			$retABF_WL = Symphony::Database()->query($sql);

			Symphony::Configuration()->remove(ABF::SETTING_GROUP, ABF::SETTING_GROUP);
			Administration::instance()->saveConfig();

			$this->_isInstalled = false;

			return $retABF && $retABF_BL && $retABF_GL && $retABF_WL;
		}

	}