<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT

	*/

	class ViewFactory {

		/**
		 *
		 * Utility method that build the header of the table element
		 */
		public static function buildTableHeader(Array $cols) {
			$a = array();
			foreach ($cols as $key => $value) {
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
		public static function buildTableBody(Array $cols, Array $data) {
			$a = array();

			// update flag
			$_hasData = $data != null && count($data) > 0;

			if (!$_hasData) {
				// no data
				// add a table row with only one cell
				$a = array(
					Widget::TableRow(
						array(
							Widget::TableData(
								__('None found.'), // text
								'inactive', // class
								NULL, // id
								count($cols)  // span
							)
						),'odd'
					)
				);
			} else {

				$index = 0;

				foreach ($data as $datarow) {

					$tds = array();

					$datarow = get_object_vars($datarow);

					foreach ($cols as $key => $value) {
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

						array_push($tds, $td);
					}

					array_push($a, Widget::TableRow($tds, $index % 2 == 0 ? 'even' : 'odd'));

					$index++;
				}
			}

			return $a;
		}

		/**
		 * Utility method that generates the 'action' panel
		 */
		public static function buildActions($hasData, Array $additionalActions = null) {
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			if ($hasData == true) {

				$options = array(
					array(NULL, false, __('With Selected...')),
					array('delete', false, __('Delete'), 'confirm'),
				);

				if ($additionalActions != null) {
					array_push($options, $additionalActions);
				}

				$tableActions->appendChild(Widget::Select('with-selected', $options));
				$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			}

			return $tableActions;
		}


		/**
		 * Utility method that generates a 'select' menu
		 */
		public static function buildSelectMenu(Array $options, $actionkey, $actionTextKey) {
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$tableActions->appendChild(Widget::Select('with-' . $actionkey, $options));
			$tableActions->appendChild(Widget::Input("action[$actionkey]", __($actionTextKey), 'submit'));

			return $tableActions;
		}
	}