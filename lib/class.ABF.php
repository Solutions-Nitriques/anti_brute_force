<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT
	*/

	require_once (EXTENSIONS . '/asdc/lib/class.asdc.php');

	/**
	 *
	 * Symphony CMS leaverage the Decorator pattern with their Extension class.
	 * This class is a Facade that implements <code>Singleton</code> and the methods
	 * needed by the Decorator. It offers it's methods via the instance() satic function
	 * @author nicolasbrassard
	 *
	 */
	class ABF implements Singleton {

		/**
		 * Short hand fot the table name
		 * @var unknown_type
		 */
		private $tbl = 'tbl_anti_brute_force';

		/**
		 * Singleton implementation
		 */
		// singleton instance
		private static $I = null;

		// single ton method
		public static function instance() {
			if (self::$I == null) {
				self::$I = new self();
			}
			return self::$I;
		}

		// do not allow external creation
		private function __construct(){}


		/**
		 * Public methods
		 */

		/**
		 *
		 * Check to see if the current user IP address is banned,
		 * based on the parameters passed to the method
		 * @param int/string $length
		 * @param int/string $failedCount
		 */
		public function isCurrentlyBanned($length, $failedCount) {

			$results = $this->getFailureByIp(null, "
				AND UNIX_TIMESTAMP(LastAttempt) + (60 * $length) > UNIX_TIMESTAMP()
				AND FailedCount >= $failedCount");

			if ($results != null) {
				return $results->length() > 0;
			}

			return true;
		}

		/**
		 *
		 * Register a failure - insert or update - for a IP
		 * @param string $username
		 * @param string $ip @optional - will take current user's ip
		 */
		public function registerFailure($username, $ip='') {
			$ip = strlen($ip) < 8 ? $this->getIP() : $ip;
			$username = ASDCLoader::instance()->escape($username);
			$ua = ASDCLoader::instance()->escape($this->getUA());
			$results = $this->getFailureByIp($ip);

			if ($results != null && $results->length() > 0) {
				// UPDATE
				ASDCLoader::instance()->query("
					UPDATE $this->tbl
						SET `LastAttempt` = NOW(),
						    `FailedCount` = `FailedCount` + 1,
						    `Username` = '$username',
						    `UA` = '$ua'
						WHERE IP = '$ip'
						LIMIT 1
				");

			} else {
				// INSERT
				ASDCLoader::instance()->query("
					INSERT INTO $this->tbl
						(`IP`, `LastAttempt`, `Username`, `FailedCount`, `UA`)
						VALUES
						('$ip', NOW(), '$username', 1, '$ua')
				");
			}
		}

		/**
		 *
		 * Utility function that throw a properly formatted SymphonyErrorPage Exception
		 * @param string $length - length of block in minutes
		 * @throws SymphonyErrorPage
		 */
		public function throwBannedException($length) {
			// banned throw exception
			throw new SymphonyErrorPage(
				__('Your IP address is currently banned, due to typing too many worng usernames/passwords')
				. '<br/><br/>' .
				__('You can ask your administrator to unlock your account or wait %s minutes', array($length)),
				__('Banned IP address')
			);
		}

		/**
		 *
		 * Unregister IP from the banned table - even if max failed count is not reach
		 * @param string $ip  - will take current user's ip
		 */
		public function unregisterFailure($ip='') {
			$ip = strlen($ip) < 8 ? $this->getIP() : $ip;
			ASDCLoader::instance()->delete($this->tbl, "IP = '$ip'");
		}

		/**
		 *
		 * Enter description here ...
		 * @param string/int $length
		 */
		public function removeExpiredEntries($length) {
			ASDCLoader::instance()->delete($this->tbl, "UNIX_TIMESTAMP(LastAttempt) + (60 * $length) < UNIX_TIMESTAMP()");
		}

		/**
		 * Database Data queries
		 */

		/**
		 *
		 * Method that returns failures based on IP address and other filters
		 * @param string $ip the ip in the select query
		 * @param string $additionalWhere @optional additional SQL filters
		 */
		public function getFailureByIp($ip='', $additionalWhere='') {
			$ip = strlen($ip) >= 8? $ip: $this->getIP();
			$where = "IP = '$ip'";
			if (strlen($additionalWhere) > 0) {
				$where .= $additionalWhere;
			}
			$sql ="
				SELECT * FROM $this->tbl WHERE $where LIMIT 1
			" ;

			return ASDCLoader::instance()->query($sql);
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
				SELECT * FROM $this->tbl $order
			" ;

			return ASDCLoader::instance()->query($sql);
		}

		/**
		 * Utilities
		 */
		private function getIP() {
			return $_SERVER["REMOTE_ADDR"];
		}

		private function getUA() {
			return $_ENV["HTTP_USER_AGENT"];
		}


		/**
		 * Database Data Definition Queries
		 */

		public function install() {
			$sql = "
				CREATE TABLE IF NOT EXISTS $this->tbl (
					`IP` VARCHAR( 16 ) NOT NULL ,
					`LastAttempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
					`FailedCount` INT( 5 ) NOT NULL DEFAULT  '1',
					`UA` VARCHAR( 1024 ) NULL ,
					`Username` VARCHAR( 100 ) NULL ,
					PRIMARY KEY (  `IP` )
				) ENGINE = MYISAM
			";

			$results = ASDCLoader::instance()->query($sql);

			return true;
		}

		public function update($previousVersion, $currentVersion) {
			switch ($previousVersion) {
				case $currentVersion:
					break;
				case '1.0':
					break;
				default:
					return $this->install();
			}
			return false;
		}

		public function uninstall() {
			$sql = "
				DROP TABLE IF EXISTS $this->tbl
			";

			$results = ASDCLoader::instance()->query($sql);

			return true;
		}

	}