<?php

if (!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

require_once TOOLKIT . '/class.administrationpage.php';
require_once EXTENSIONS . '/anti_brute_force/lib/class.ABF.php';
require_once EXTENSIONS . '/anti_brute_force/lib/class.ViewFactory.php';

/*
License: MIT

Based on content pages on https://github.com/eKoeS/edui/
*/

class contentExtensionAnti_brute_forceColored_Lists extends AdministrationPage
{
    private $_cols = null;
    private $_hasData = false;
    private $_data = null;
    private $_tables = array();
    private $_curColor = null;

    public function __construct()
    {
        parent::__construct();

        $this->_cols = array(
            'IP' => __('IP Address'),
            'FailedCount' => __('Failed Count'),
            'DateCreated' => __('Date Created'),
            'Source' => __('Source')
        );

        $this->_tables = array(
            // value, selected, label
            'black' => __('Black list'),
            'gray'  => __('Gray list'),
            'white' => __('White list')
        );

        $this->_curColor = (isset($_REQUEST['list']) && array_key_exists($_REQUEST['list'], $this->_tables)) ? $_REQUEST['list'] : 'black';

        $this->_data = array();
    }

    /**
     * Quick accessor and lazy load of the data
     */
    private function getData()
    {
        if (count($this->_data) == 0) {
            $this->_data = ABF::instance()->getListEntries($this->_curColor);
            $this->_hasData = count($this->_data) > 0;
        }

        return $this->_data;
    }

    /**
     * Builds the content view
     */
    public function __viewIndex()
    {
        $title = $this->_tables[$this->_curColor];

        $this->setPageType('table');

        $this->setTitle(sprintf('%1$s: %2$s &ndash; %3$s', extension_anti_brute_force::EXT_NAME, $title, __('Symphony')));

        $this->addStylesheetToHead(URL . '/extensions/anti_brute_force/assets/content.abf.css', 'screen', time() + 10);

        $this->appendSubheading(__($title));

        $cols = $this->getCurrentCols();

        // build header table
        $aTableHead = ViewFactory::buildTableHeader($cols);

        // build body table
        $aTableBody = ViewFactory::buildTableBody($cols, $this->getData());

        // build data table
        $table = Widget::Table(
            Widget::TableHead($aTableHead),
            NULL,
            Widget::TableBody($aTableBody),
            'selectable',
            null,
            array('role' => 'directory', 'aria-labelledby' => 'symphony-subheading', 'data-interactive' => 'data-interactive')
        );

        // build the color select box
        $this->Context->appendChild(
            ViewFactory::buildSubMenu($this->_tables, $this->_curColor, 'switch')
        );

        // append table
        $this->Form->appendChild($table);

        // insert form
        $insertLine = $this->buildInsertForm();

        // append actions
        $insertLine->appendChild(
            ViewFactory::buildActions($this->_hasData)
        );

        // append the insert line
        $this->Form->appendChild($insertLine);
    }

    /**
     *
     * Utility function that build the Insert Form UI
     */
    private function buildInsertForm()
    {
        $wrap = new XMLElement('fieldset');
        $wrap->setAttribute('class', 'insert');

        $label = Widget::Label();

        $iInput = Widget::Input(
            'insert[ip]',
            $this->_curColor == 'white' ? ABF::instance()->getIP() : '',
            'text',
            array(
                'placeholder'=> '0.0.0.0',
                'class' => 'input-ip'
            )
        );

        $iBut = Widget::Input(
            'action[insert]',
            __('Add'),
            'submit',
            array(
                'class' => 'input-submit'
            )
        );

        $label->appendChild($iInput);
        $label->appendChild($iBut);

        $wrap->appendChild($label);

        $wrap->appendChild(Widget::Input('list',$this->_curColor, 'hidden'));

        return $wrap;
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
                        $this->__actionApply();
                        break;

                    case 'insert':
                        $this->__actionInsert();
                        break;
                }
            }
        }
    }

    /**
     * Apply action
     */
    public function __actionApply()
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
     * Insert action
     */
    public function __actionInsert()
    {
        if (is_array($_POST['insert']) && isset($_POST['insert']['ip'])) {
            $ip = $_POST['insert']['ip'];

            // protection for not entering the users ip
            // since ip='' will become his ip
            if (ABF::instance()->isIPValid($ip)) {

                // temporary fix for getting the author
                $author = null;
                if (is_callable(array('Symphony', 'Author'))) {
                    $author = Symphony::Author();
                } else {
                    $author = Administration::instance()->Author;
                }

                try {
                    $author = $author->getFullName();
                    $ret = ABF::instance()->registerToList(
                        $this->_curColor,
                        "Manual entry made by $author",
                        $ip
                    );

                    if ($ret) {
                        $this->pageAlert(__('IP added successfuly.'), Alert::SUCCESS);
                    } else {
                        throw new Exception(__('Could not save IP address.'));
                    }
                } catch (Exception $e) {
                    $this->pageAlert(__('Error') . ': ' . $e->getMessage(), Alert::ERROR);
                }
            } elseif (strlen($ip) > 0) {
                $this->pageAlert(__('Error: The given IP address, `%s`, is not valid', array($ip)), Alert::ERROR);
            } else {
                $this->pageAlert(__('Error: No IP address submitted'), Alert::ERROR);
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
                    ABF::instance()->unregisterToList($this->_curColor, $ip);
                }

                $this->pageAlert(__('Entries remove successfuly'), Alert::SUCCESS);

            } catch (Exception $e) {
                $this->pageAlert(__('Error') . ': ' . $e->getMessage(), Alert::ERROR);
            }
        }
    }

    /**
     * Utility method that filters the columns names
     * based on the current color
     */
    private function getCurrentCols()
    {
        $cols = array();
        $cols = array_merge($cols, $this->_cols);

        // only gray list has failed count col
        if ($this->_curColor != 'gray') {
            array_splice($cols, 1, 1);
        }

        return $cols;
    }
}
