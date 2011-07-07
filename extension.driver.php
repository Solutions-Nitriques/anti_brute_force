<?php
	/*
	Copyight: Solutions Nitriques 2011
	License: MIT, see the LICENCE file
	*/

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	// facade
	require_once(EXTENSIONS . '/anti_brute_force/lib/class.ABF.php');

	/**
	 *
	 * Anti Brute Force Decorator/Extension
	 * @author nicolasbrassard
	 *
	 */
	class extension_anti_brute_force extends Extension {

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
		 * private variable for holding the errors encountered when saving
		 * @var array
		 */
		protected $errors = array();

		/**
		 * Flag to check if ban check was done
		 * @var boolean
		 */
		protected $banCheckDone = false;

		/**
		 * Credits for the extension
		 */
		public function about() {
			return array(
				'name'			=> self::EXT_NAME,
				'version'		=> '1.1',
				'release-date'	=> '2011-07-xx',
				'author'		=> array(
					'name'			=> 'Solutions Nitriques',
					'website'		=> 'http://www.nitriques.com/open-source/',
					'email'			=> 'open-source (at) nitriques.com'
				),
				'description'	=> __('Secure your backend login page against brute force attacks'),
				'compatibility' => array(
					'2.2.1' => true,
					'2.2' => true
				)
	 		);
		}

		/**
		 *
		 * Symphony utility function that permits to
		 * implement the Observer/Observable pattern.
		 * We register here delegate that will be fired by Symphony
		 */
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/login/',
					'delegate' => 'AuthorLoginFailure',
					'callback' => 'authorLoginFailure'
				),
				array(
					'page' => '/login/',
					'delegate' => 'AuthorLoginSuccess',
					'callback' => 'authorLoginSuccess'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'addCustomPreferenceFieldsets'
				),
				array(
					'page'      => '/system/preferences/',
					'delegate'  => 'Save',
					'callback'  => 'save'
				),
				array(
					'page'      => '/backend/',
					'delegate'  => 'AdminPagePreGenerate',
					'callback'  => 'adminPagePreGenerate'
				),
				array(
					'page'      => '/backend/',
					'delegate'  => 'InitaliseAdminPageHead',
					'callback'  => 'initaliseAdminPageHead'
				)
			);
		}

		/**
		 * Delegate fired to add a link to the Banned IPs Administration page
		 */
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> __('System'),
					'name'	=> __('Banned IPs'),
					'link'	=> '/banned_ips/'
				),
				array(
					'location'	=> __('System'),
					'name'	=> __('Colored Lists'),
					'link'	=> '/colored_lists/'
				)
			);
		}

		/**
		 * Delegate fired when the HEAD section must be build
		 * @param array $context
		 */
		public function initaliseAdminPageHead($context) {
			// do it here since it is called before
			// processing $_POST['action']
			$this->doBanCheck();
		}

		/**
		 *
		 * Delegate fired when a login fails
		 * @param array $context
		 */
		public function authorLoginFailure($context) {
			// register failure in DB
			ABF::instance()->registerFailure($context['username'], self::EXT_NAME);
		}

		/**
		 *
		 * Delegate fired when a author is logged in correctly
		 * @param array $context
		 */
		public function authorLoginSuccess($context) {
			// unregister any result with current IP
			ABF::instance()->unregisterFailure();

			// Since user can still post data to the login page
			// we don't want them to be able to know they guessed it right.
			// So, if user is loggued in but still ban, we logout them
			if (Administration::instance()->isLoggedIn && $this->isCurrentlyBanned) {
				Administration::instance()->logout();
			}
		}

		/**
		 *
		 * Delegate fired when the body of the page must be build
		 * @param array $context
		 */
		public function adminPagePreGenerate($context) {
			$length = self::getConfigVal(self::SETTING_LENGTH);
			$oPage = $context['oPage'];
			$unBanViaEmail = self::getConfigVal(self::SETTING_AUTO_UNBAN);

			// clean on login page
			if ($oPage instanceof contentLogin) {
				// clean database before check
				ABF::instance()->removeExpiredEntries($length);
			}

			// N.B. We must still do it here
			// since initaliseAdminPageHead is not fired on some requests
			if (! 	($oPage instanceof contentExtensionAnti_brute_forceLogin) ||
					($oPage instanceof contentExtensionAnti_brute_forceLogin &&
					$unBanViaEmail != 'Yes' &&
					$unBanViaEmail != true) ) {
				$this->doBanCheck();
			}
		}

		/**
		 *
		 * Utility function that returns <code>>true</code>
		 * if the current IP address is banned.
		 */
		public function isCurrentlyBanned() {
			$length = self::getConfigVal(self::SETTING_LENGTH);
			return ABF::instance()->isCurrentlyBanned($length,self::getConfigVal(self::SETTING_FAILED_COUNT));
		}

		/**
		 * Do the actual ban check: throw exception if banned/black listed
		 * Can be called only once; wont do anything after that
		 */
		public function doBanCheck() {
			// if no already done...
			if (!$this->banCheckDone) {

				// check if not white listed
				if (!ABF::instance()->isWhiteListed()) {

					// check if blacklisted
					if (ABF::instance()->isBlackListed()) {
						// block access
						ABF::instance()->throwBlackListedException();
					}

					// check if banned
					if ($this->isCurrentlyBanned()) {
						$length = self::getConfigVal(self::SETTING_LENGTH);
						$unbanViaEmail = self::getConfigVal(self::SETTING_AUTO_UNBAN);

						// block access
						ABF::instance()->throwBannedException($length, $unbanViaEmail);
					}
				}

				$this->banCheckDone = true;
			}
		}

		/**
		 *
		 * Delegate fired when the extension is install
		 */
		public function install() {
			$intalled = ABF::instance()->install();

			if ($intalled) {
				// set default values
				$pseudo_context = array(
					'settings' => array (
						self::SETTING_GROUP => array (
							self::SETTING_LENGTH => 60,
							self::SETTING_FAILED_COUNT => 5,
							self::SETTING_AUTO_UNBAN => 'off',
							self::SETTING_GL_THRESHOLD => 5,
							self::SETTING_GL_DURATION => 30
						)
					)
				);
				$this->save($pseudo_context);
			}

			return $intalled;
		}

		/**
		 *
		 * Delegate fired when the extension is updated (when version changes)
		 * @param string $previousVersion
		 */
		public function update($previousVersion) {
			$about = $this->about();
			return ABF::instance()->update($previousVersion,$about['version']);
		}

		/**
		 *
		 * Delegate fired when the extension is uninstall
		 * Cleans settings and Database
		 */
		public function uninstall() {
			Symphony::Configuration()->remove(self::SETTING_GROUP, self::SETTING_GROUP);
			Administration::instance()->saveConfig();
			return ABF::instance()->uninstall();
		}

		/**
		 * Delegate handle that adds Custom Preference Fieldsets
		 * @param string $page
		 * @param array $context
		 */
		public function addCustomPreferenceFieldsets($context) {
			// creates the field set
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', self::EXT_NAME));

			// create a paragraph for short intructions
			$p = new XMLElement('p', __('Define here when and how IP are blocked'), array('class' => 'help'));

			// append intro paragraph
			$fieldset->appendChild($p);

			// outter wrapper
			$out_wrapper = new XMLElement('div');

			// create a wrapper
			$wrapper = new XMLElement('div');
			$wrapper->setAttribute('class', 'group');

			// append labels to field set
			$wrapper->appendChild($this->generateField(self::SETTING_FAILED_COUNT, 'Fail count limit'));
			$wrapper->appendChild($this->generateField(self::SETTING_LENGTH, 'Blocked length <em>in minutes</em>'));

			$out_wrapper->appendChild($wrapper);

			// create a new wrapper
			$wrapper = new XMLElement('div');
			$wrapper->setAttribute('class', 'group');
			$wrapper->appendChild($this->generateField(self::SETTING_GL_THRESHOLD, 'Grey list threshold'));
			$wrapper->appendChild($this->generateField(self::SETTING_GL_DURATION, 'Grey list duration <em>in days</em>'));

			$out_wrapper->appendChild($wrapper);

			// create a new wrapper
			$wrapper = new XMLElement('div');
			$wrapper->setAttribute('class', 'group');
			$wrapper->appendChild($this->generateField(self::SETTING_AUTO_UNBAN, 'Users can unban their IP via email', 'checkbox'));

			$out_wrapper->appendChild($wrapper);

			// wrapper into fieldset
			$fieldset->appendChild($out_wrapper);

			// adds the field set to the wrapper
			$context['wrapper']->appendChild($fieldset);
		}

		/**
		 * Quick utility function to make a input field+label
		 * @param string $settingName
		 * @param string $textKey
		 */
		public function generateField($settingName, $textKey, $type = 'text') {
			$inputText = self::getConfigVal($settingName);
			$inputAttr = array();

			switch ($type) {
				case 'checkbox':
					if ($inputText == 'on') {
						$inputAttr['checked'] = 'checked';
					}
					$inputText = '';
					break;
			}

			// create the label and the input field
			$wrap = new XMLElement('div');
			$label = Widget::Label();
			$input = Widget::Input(
						'settings[' . self::SETTING_GROUP . '][' . $settingName .']',
						$inputText,
						$type,
						$inputAttr
					);

			// set the input into the label
			$label->setValue(__($textKey). ' ' . $input->generate() . $err);

			$wrap->appendChild($label);

			// error management
			if ($this->hasErrors() && isset($this->errors[$settingName])) {
				// style
				$wrap->setAttribute('class', 'invalid');
				$wrap->setAttribute('id', 'error');
				// error message
				$err = new XMLElement('p', $this->errors[$settingName]);
				$wrap->appendChild($err);
			}

			return $wrap;
		}

		/**
		 *
		 * Utility method to know if there was any error
		 * while saving preferences
		 * @return boolean
		 */
		private function hasErrors() {
			return count($this->errors) > 0;
		}

		/**
		 *
		 * Utility function that returns settings from this extensions settings group
		 * @param string $key
		 */
		public static function getConfigVal($key) {
			return Symphony::Configuration()->get($key, self::SETTING_GROUP);
		}

		/**
		 * Delegate handle that saves the preferences
		 * Saves settings and cleans the database acconding to the new settings
		 * @param array $context
		 */
		public function save(&$context){
			$this->saveOne($context, self::SETTING_LENGTH, false);
			$this->saveOne($context, self::SETTING_FAILED_COUNT, false);
			$this->saveOne($context, self::SETTING_GL_DURATION, false);
			$this->saveOne($context, self::SETTING_GL_THRESHOLD, false);
			$this->saveOne($context, self::SETTING_FAILED_COUNT, false);
			$this->saveOne($context, self::SETTING_FAILED_COUNT, true, 'checkbox');

			if (!$this->hasErrors()) {
				// clean old entry after save, since this may affects some banned IP
				ABF::instance()->removeExpiredEntries(self::getConfigVal(self::SETTING_LENGTH));
			}
		}

		/**
		 *
		 * Save one parameter
		 * @param array $context
		 * @param string $key
		 * @param string $autoSave @optional
		 */
		public function saveOne(&$context, $key, $autoSave = true, $type = 'text'){
			// get the input
			$input = $context['settings'][self::SETTING_GROUP][$key];
			$iVal = intval($input);

			// verify it is a good domain
			if (strlen($input) > 0 && is_int($iVal) && $iVal > 0) {

				// set config                    (name, value, group)
				Symphony::Configuration()->set($key, $iVal, self::SETTING_GROUP);

				// save it
				if ($autoSave) {
					Administration::instance()->saveConfig();
				}

			} else {
				// don't save

				// set error message
				$error = __('"%s" is not a valid positive integer',  array($input));

				// append to local array
				$this->errors[$key] = $error;

				// add an error into the stack
				$context['errors'][self::SETTING_GROUP][$key] = $error;
			}
		}
	}