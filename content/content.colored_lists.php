<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/anti_brute_force/lib/class.ABF.php');
	require_once(EXTENSIONS . '/anti_brute_force/lib/class.ViewFactory.php');

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT

	Based on content pages on https://github.com/eKoeS/edui/
	*/

	class contentExtensionAnti_brute_forceColored_Lists extends AdministrationPage {

		private $_cols = null;
		private $_hasData = false;
		private $_data = null;
		private $_tables = array();
		private $_curColor = 'black';

		public function __construct(&$parent) {
			parent::__construct($parent);

			$this->_cols = array(
				'IP' => __('IP Address'),
				'FailedCount' => __('Failed Count'),
				'DateCreated' => __('Date Created'),
				'Source' => __('Source')
			);

			$this->_tables = array(
					// value, selected, label
				array('black', false, __('Black list')),
				array('grey', false, __('Grey list')),
				array('white', false, __('White list'))
			);

			$this->setSelected();

			$this->_data = array();
		}

		private function setSelected() {
			$x = 0;
			foreach ($this->_tables as $t) {
				$this->_tables[$x][1] = ($this->_curColor == $t[0]);
				$x++;
			}
		}

		/**
		 * Builds the content view
		 */
		public function __viewIndex() {
			$title = __('Colored Lists');

			$this->setPageType('table');

			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $title)));

			$this->appendSubheading(__($title));

			$cols = $this->getCurrentCols();

			// build header table
			$aTableHead = ViewFactory::buildTableHeader($cols);

			// build body table
			$aTableBody = ViewFactory::buildTableBody($cols, $this->_data);

			// build data table
			$table = Widget::Table(
				Widget::TableHead($aTableHead), // header
				NULL, // footer
				Widget::TableBody($aTableBody), // body
				'selectable' // class
				// id
				// attributes
			);

			// build the color select box
			$this->Form->appendChild(
				ViewFactory::buildSelectMenu($this->_tables, 'switch', 'Change')
			);

			$this->Form->appendChild($table);

			$this->Form->appendChild(
				ViewFactory::buildActions($this->_hasData)
			);
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
							$this->__actionApply();
							break;

						case 'switch':
							$this->__actionSwitch();
							break;
					}
				}
			}
		}

		/**
		 * Apply action
		 */
		public function __actionApply() {
			if (isset($_POST['with-selected'])) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__delete();
						break;
				}
			}
		}

		/**
		 * Switch action
		 */
		public function __actionSwitch() {
			if (isset($_POST['with-selected'])) {
				$this->_curColor = $_POST['with-selected'];

				$this->setSelected();
			}
		}

		/**
		 * Utility method that deletes selected failures
		 */
		private function __delete() {
			if (isset($_POST['ip']) && is_array($_POST['ip'])) {
				try {
					foreach ($_POST['ip'] as $ip => $value) {

						//ABF::instance()->u ($ip);

					}

					$this->pageAlert(__('Entries remove successfuly'), Alert::SUCCESS);

				} catch (Exception $e) {

					$this->pageAlert(__('Error') . ': ' . $e->getMessage(), Alert::ERROR);

				}
			}
		}

		private function getCurrentCols() {
			$cols = array();
			$cols = array_merge($cols, $this->_cols);

			// only grey list a failed count col
			if ($this->_curColor != 'grey') {
				array_splice($cols, 1, 1);
			}

			return $cols;
		}

	}