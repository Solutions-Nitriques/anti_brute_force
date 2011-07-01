<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT
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
		 * Key of the group of setting
		 * @var string
		 */
		const SETTING_GROUP = 'force-domain';

		/**
		 * private variable for holding the errors encountered when saving
		 * @var array
		 */
		protected $errors = array();

		/**
		 * Credits for the extension
		 */
		public function about() {
			return array(
				'name'			=> self::EXT_NAME,
				'version'		=> '1.0',
				'release-date'	=> '2011-07-01',
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
				)
			);
		}

		public function authorLoginFailure($context) {

		}

		public function authorLoginSuccess($context) {

		}

		public function install() {

			// set default values
			$default_values = array(
				'settings' => array (
					self::SETTING_GROUP => array (
						self::SETTING_LENGTH => 60,
						self::SETTING_FAILED_COUNT => 5
					)
				)
			);
			$this->save($default_values);
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

			// create a wrapper
			$wrapper = new XMLElement('div');
			$wrapper->setAttribute('class', 'group');

			// wrapper into fieldset
			$fieldset->appendChild($wrapper);

			// append labels to field set
			$wrapper->appendChild($this->generateField(self::SETTING_FAILED_COUNT, 'Fail count limit'));
			$wrapper->appendChild($this->generateField(self::SETTING_LENGTH, 'Blocked length <small>in minutes</small>'));

			// error management
			if (count($this->errors) > 0) {
				foreach ($this->errors as $error) {
					// set css and anchor
					$wrapper->setAttribute('id', 'error');
					$wrapper->setAttribute('class', 'invalid');

					// adds error message
					$err = new XMLElement('p', $error);

					// append to $wrapper
					$wrapper->appendChild($err);
				}
			}

			// adds the field set to the wrapper
			$context['wrapper']->appendChild($fieldset);
		}

		/**
		 * Quick utility function to make a input field+label
		 * @param string $settingName
		 * @param string $textKey
		 */
		public function generateField($settingName, $textKey) {
			// create the label and the input field
			$label = Widget::Label();
			$input = Widget::Input(
						'settings[' . self::SETTING_GROUP . '][' . $settingName .']',
						self::getConfigVal($settingName),
						'text'
					);

			// set the input into the label
			$label->setValue(__($textKey). ' ' . $input->generate());

			return $label;
		}
		private static function getConfigVal($key) {
			return Symphony::Configuration()->get($key, self::SETTING_GROUP);
		}

		/**
		 * Delegate handle that saves the preferences
		 * @param array $context
		 */
		public function save($context){
			$this->saveOne($context, self::SETTING_LENGTH);
			$this->saveOne($context, self::SETTING_FAILED_COUNT);
		}

		/**
		 *
		 * Save one parameter
		 * @param array $context
		 * @param string $key
		 */
		public function saveOne($context, $key){
			// get the input
			$input = $context['settings'][self::SETTING_GROUP][$key];

			// verify it is a good domain
			if (is_int($input)) {

				// set config                    (name, value, group)
				Symphony::Configuration()->set($key, $input, self::SETTING_GROUP);

				// save it
				Administration::instance()->saveConfig();

			} else {
				// don't save

				// set error message
				$error = __('"%s" is not a valid integer',  array($input));
			 	array_push( $this->errors, $error);

				//echo $error;die;

				// add an error into the stack
				$context['errors'][self::SETTING_GROUP][$key] = $error;
			}
		}

	}

?>