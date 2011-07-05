<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	require_once(TOOLKIT . '/class.administrationpage.php');

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT

	Based on content pages on https://github.com/eKoeS/edui/
	*/

	class contentExtensionAnti_brute_forceBanned_ips extends AdministrationPage {

		private $_cols = null;
		private $_hasData = false;

		public function __construct(&$parent) {
			parent::__construct($parent);

			$this->_cols = array(
				'IP' => __('IP Address'),
				'Username' => __('Username'),
				'FailedCount' => __('Failed Count'),
				'LastAttempt' => __('Last Attempt'),
				'Source' => __('Source'),
				'UA' => __('User Agent (browser)')
			);
		}

		/**
		 * Builds the content view
		 */
		public function __viewIndex() {
			$title = __('Banned IPs');

			$this->setPageType('table');

			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $title)));

			$this->appendSubheading(__($title));

			// build header table
			$aTableHead = $this->buildTableHeader();

			// build body table
			$aTableBody = $this->buildTableBody();

			// build data table
			$table = Widget::Table(
				Widget::TableHead($aTableHead), // header
				NULL, // footer
				Widget::TableBody($aTableBody), // body
				'selectable' // class
				// id
				// attributes
			);

			$this->Form->appendChild($table);

			$this->addActions();
		}

		/**
		 *
		 * Utility method that build the header of the table element
		 */
		private function buildTableHeader() {
			$a = array();
			foreach ($this->_cols as $key => $value) {
				$label = Widget::Label($value);
				$a[] = array($label, 'col');
			}
			return $a;
		}

		/**
		 *
		 * Utility method that build the body of the table element
		 *
		 * ** I wish templating Admin Page was simplier !!!
		 */
		private function buildTableBody() {
			$a = array();
			$data = ABF::instance()->getFailures('IP ASC');

			// update flag
			$this->_hasData = $data != null && count($data) > 0;

			if (!$this->_hasData) {
				// no data
				// add a table row with only one cell
				$a = array(
					Widget::TableRow(
						array(
							Widget::TableData(
								__('None found.'), // text
								'inactive', // class
								NULL, // id
								count($this->_cols)  // span
							)
						),'odd'
					)
				);
			} else {

				$index = 0;

				foreach ($data as $datarow) {

					$cols = array();

					$datarow = get_object_vars($datarow);

					foreach ($this->_cols as $key => $value) {
						$val = $datarow[$key];
						$css = 'col';
						$hasValue = strlen($val) > 0;

						if (!$hasValue) {
							$val = __('None');
							$css = 'inactive';
						}

						$td = Widget::TableData($val, $css);

						// add the hidden checkbox for selectacle row
						if ($key == 'IP' && $hasValue) {
							$chk = Widget::Input(
								"ip[$val]", //name
								NULL,
								'checkbox'
							);
							$td->appendChild($chk);
						}

						array_push($cols, $td);
					}

					array_push($a, Widget::TableRow($cols, $index % 2 == 0 ? 'even' : 'odd'));

					$index++;
				}
			}

			return $a;
		}

		/**
		 * Utility method that generates the 'action' panel
		 */
		private function addActions() {
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			if ($this->_hasData) {

				$options = array(
					array(NULL, false, __('With Selected...')),
					array('delete', false, __('Delete'), 'confirm'),
				);

				$tableActions->appendChild(Widget::Select('with-selected', $options));
				$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			}

			$this->Form->appendChild($tableActions);
		}

		/**
		 * Method that handles user actions on the page
		 */
		public function __actionIndex() {
			// if actions were launch
			if (isset($_POST['action']) && is_array($_POST['action'])) {

				// for each action
				foreach ($_POST['action'] as $key => $action) {
					switch ($key) {
						case 'apply':
							$this->__actionApply($action);
							break;
					}
				}
			}
		}

		/**
		 * Apply action
		 * @param $action
		 */
		public function __actionApply($action) {
			if (isset($_POST['with-selected'])) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__delete();
						break;
				}
			}
		}

		/**
		 * Utility method that deletes selected failures
		 */
		private function __delete() {
			if (isset($_POST['ip']) && is_array($_POST['ip'])) {
				try {
					foreach ($_POST['ip'] as $ip => $value) {
						ABF::instance()->unregisterFailure($ip);
					}

					$this->pageAlert(__('Failures remove successfuly'), Alert::SUCCESS);

				} catch (Exception $e) {

					$this->pageAlert(__('Error') . ': ' . $e->getMessage(), Alert::ERROR);

				}
			}
		}

	}