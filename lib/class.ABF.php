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
		 * Short hand
		 * @var MySQL
		 */
		private $db = Symphony::Database;

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
		private function __construct() {}


		/**
		 * Public methods
		 */

		public function isCurrentlyBanned() {

			return true;
		}

		public function registerFailure() {

		}

		public function unregisterFailure() {

		}

		public function removeExpiredEntries() {

		}


		/**
		 * Database Data Definition Queries
		 */

		public function install() {

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

			return true;
		}

	}