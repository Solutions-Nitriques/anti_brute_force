<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	require_once(CONTENT . '/content.login.php');

	/*
	Copyight: Solutions Nitriques 2011
	License: MIT

	*/

	/**
	 *
	 * N.B. : Page is named login in order for the Administration Class to
	 * pretend in the login page via <code>$this->_context['driver']</code>.
	 * Should certainly be named something else
	 * @author nicolasbrassard
	 *
	 */
	class contentExtensionAnti_brute_forceLogin extends contentLogin {

		private $_email_sent = null;

		/**
		 *
		 * Overrides the view method
		 */
		public function view(){
			// if not banned, redirect
			$banned = ABF::instance()->isCurrentlyBanned(
							extension_anti_brute_force::getConfigVal(extension_anti_brute_force::SETTING_LENGTH),
							extension_anti_brute_force::getConfigVal(extension_anti_brute_force::SETTING_FAILED_COUNT)
					);
			if ($banned) {
				redirect(SYMPHONY_URL);
			}

			$this->Form = Widget::Form('', 'post');

			$this->Form->appendChild(new XMLElement('h1', __('Symphony')));

			$this->__buildFormContent();

			$this->Body->appendChild($this->Form);
		}

		private function __buildFormContent() {
			$fieldset = new XMLElement('fieldset');
			$fieldset->appendChild(new XMLElement('p', __('Enter your email address to be sent a remote unban link with further instructions.')));

			$label = Widget::Label(__('Email Address'));
			$label->appendChild(Widget::Input('email', $_POST['email']));

			$this->Body->setAttribute('onload', 'document.forms[0].elements.email.focus()');

			if(isset($this->_email_sent) && !$this->_email_sent){
				$div = new XMLElement('div', NULL, array('class' => 'invalid'));
				$div->appendChild($label);
				$div->appendChild(new XMLElement('p', __('There was a problem locating your account. Please check that you are using the correct email address.')));
				$fieldset->appendChild($div);
			} else {
				$fieldset->appendChild($label);
			}

			$this->Form->appendChild($fieldset);

			$div = new XMLElement('div', NULL, array('class' => 'actions'));
			$div->appendChild(new XMLElement('button', __('Send Email'), array('name' => 'action[unban]', 'type' => 'submit')));
			$this->Form->appendChild($div);
		}

		/**
		 *
		 * Overrides the action method
		 */
		public function action(){

			if(isset($_POST['action'])){


			}

		}
	}