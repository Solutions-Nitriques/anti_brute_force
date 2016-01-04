<?php

if (!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

require_once TOOLKIT . '/class.administrationpage.php';
require_once EXTENSIONS . '/anti_brute_force/lib/class.ABF.php';
require_once EXTENSIONS . '/anti_brute_force/lib/class.ViewFactory.php';

/*
Copyight: Solutions Nitriques 2011
License: MIT

Based on content pages on https://github.com/eKoeS/edui/
*/

class contentExtensionAnti_brute_forceBanned_ips extends AdministrationPage
{
    private $_cols = null;
    private $_hasData = false;
    private $_data = null;

    public function __construct()
    {
        parent::__construct();

        $this->_cols = array(
            'IP' => __('IP Address'),
            'RawIP' => __('Raw IP value'),
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
    public function __viewIndex()
    {
        // Get data
        $this->_data = ABF::instance()->getFailures('IP ASC');
        $this->_hasData = is_array($this->_data) && count($this->_data) > 0;

        // Build the page
        $title = __('Banned IPs');

        $this->setPageType('table');

        $this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), $title)));

        $this->appendSubheading(__($title));

        // build header table
        $aTableHead = ViewFactory::buildTableHeader($this->_cols);

        // build body table
        $aTableBody = ViewFactory::buildTableBody($this->_cols, $this->_data);

        // build data table
        $table = Widget::Table(
            Widget::TableHead($aTableHead),
            NULL,
            Widget::TableBody($aTableBody),
            'selectable',
            null,
            array('role' => 'directory', 'aria-labelledby' => 'symphony-subheading', 'data-interactive' => 'data-interactive')
        );
        $this->Form->appendChild($table);

        $this->Form->appendChild(
            ViewFactory::buildActions($this->_hasData)
        );
    }

    /**
     * Method that handles user actions on the page
     */
    public function __actionIndex()
    {
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
    public function __actionApply($action)
    {
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
    private function __delete()
    {
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
