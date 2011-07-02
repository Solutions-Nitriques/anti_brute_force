<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	require_once(TOOLKIT . '/class.administrationpage.php');

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT

	Based on: https://github.com/eKoeS/edui/
	*/

	class contentExtensionAnti_brute_forceBanned_ips extends AdministrationPage {

		private $_cols = null;
		private $_hasData = false;

		public function __construct(&$parent) {
			parent::__construct($parent);

			$this->_cols = array(
				'IP' => __('IP Address'),
				'Username' => __('Username'),
				'LastAttempt' => __('Last Attempt'),
				'FailedCount' => __('Failed Count'),
				'UA' => __('User Agent')
			);
		}

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
				'selectable' // id
			);

			$this->Form->appendChild($table);

			if ($this->_hasData) {
				$this->addActions();
			}
		}

		private function buildTableHeader() {
			$a = array();
			foreach ($this->_cols as $key => $value) {
				$label = Widget::Label($value);
				$a[] = array($label, 'col');
			}
			return $a;
		}

		private function buildTableBody() {
			$a = array();
			$data = ABF::instance()->getFailures('IP ASC');

			// update flag
			$this->_hasData = $data != null &&  $data->length() > 0;

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

				$datarow = null;

				while ($datarow = $data->current()) {

					$datarow = get_object_vars($datarow);
					$cols = array();

					foreach ($this->_cols as $key => $value) {
						$val = $datarow[$key];
						$css = 'col';

						if (strlen($val) == 0) {
							$val = __('None');
							$css = 'inactive';
						}

						$td = Widget::TableData($val, $css);
						array_push($cols, $td);
					}

					array_push($a, Widget::TableRow($cols));

				}
			}

			return $a;
		}

		private function addActions() {
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
				array('delete', false, __('Delete'), 'confirm'),
			);

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($tableActions);
		}

		public function __actionIndex() {
			// if actions were launch
			if (isset($_POST['action']) && is_array($_POST['action'])) {

				var_dump($_POST['action']);
				die();

				// for each action
				foreach ($_POST['action'] as $key => $action) {

				}

			}

		}

	}