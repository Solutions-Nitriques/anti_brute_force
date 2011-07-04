<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT
	*/

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
			if (!isset($length) || !isset($failedCount)) {
				return false; // no preference, how can we know...
			}
			$results = $this->getFailureByIp(null, "
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
			$ip = strlen($ip) < 8 ? $this->getIP() : $ip;
			$username = MySQL::cleanValue($username);
			$source = MySQL::cleanValue($source);
			$ua = MySQL::cleanValue($this->getUA());
			$results = $this->getFailureByIp($ip);
			$ret = false;

			if ($results != null && count($results) > 0) {
				// UPDATE
				$ret = Symphony::Database()->query("
					UPDATE $this->tbl
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
					INSERT INTO $this->tbl
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
		 * @param string $length - length of block in minutes
		 * @throws SymphonyErrorPage
		 */
		public function throwBannedException($length) {
			// banned throw exception
			throw new SymphonyErrorPage(
				__('Your IP address is currently banned, due to typing too many wrong usernames/passwords')
				. '<br/><br/>' .
				__('You can ask your administrator to unlock your account or wait %s minutes', array($length)),
				__('Banned IP address')
			);
		}

		/**
		 *
		 * Unregister IP from the banned table - even if max failed count is not reach
		 * @param string $filter @optional will take current user's ip
		 * can be the IP address or the hash value
		 */
		public function unregisterFailure($filter='') {
			// ip is at least 8 char
			// hash is 36 char
			$filter = strlen($filter) < 8 ? $this->getIP() : $filter;
			return Symphony::Database()->delete($this->tbl, "IP = '$filter' OR Hash = '$filter'");
		}

		/**
		 *
		 * Enter description here ...
		 * @param string/int $length
		 */
		public function removeExpiredEntries($length) {
			return Symphony::Database()->delete($this->tbl, "UNIX_TIMESTAMP(LastAttempt) + (60 * $length) < UNIX_TIMESTAMP()");
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
			$ip = strlen($ip) < 8 ? $this->getIP() : $ip;
			$where = "IP = '$ip'";
			if (strlen($additionalWhere) > 0) {
				$where .= $additionalWhere;
			}
			$sql ="
				SELECT * FROM $this->tbl WHERE $where LIMIT 1
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
				SELECT * FROM $this->tbl $order
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
					`UA` VARCHAR( 1024 ) NULL,
					`Username` VARCHAR( 100 ) NULL,
					`Source` VARCHAR( 100 ) NULL,
					`Hash` CHAR( 36 ) NOT NULL,
					PRIMARY KEY (  `IP` )
				) ENGINE = MYISAM
			";

			return Symphony::Database()->query($sql);
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

			return Symphony::Database()->query($sql);
		}

	}