<?php

if (!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

/*
License: MIT
*/

class ViewFactory
{
    /**
     *
     * Utility method that build the header of the table element
     */
    public static function buildTableHeader(Array $cols)
    {
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
    public static function buildTableBody(Array $cols, Array $data)
    {
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
                            null, // id
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
                    $val = General::sanitize($datarow[$key]);
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
                            null,
                            'checkbox'
                        );
                        $td->appendChild($chk);
                    }

                    $td->setAttribute('data-title', $cols[$key]);

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
    public static function buildActions($hasData, Array $additionalActions = null)
    {
        $tableActions = new XMLElement('div');
        $tableActions->setAttribute('class', 'actions');

        if ($hasData == true) {

            $options = array(
                array(null, false, __('With Selected...')),
                array('delete', false, __('Delete'), 'confirm'),
            );

            if ($additionalActions != null) {
                array_push($options, $additionalActions);
            }

            $tableActions->appendChild(Widget::Apply($options));
        }

        return $tableActions;
    }

    /**
     * Utility method that generates the 'sub' menu
     */
    public static function buildSubMenu(Array $options, $current, $actionkey)
    {
        $tableActions = new XMLElement('ul');
        $tableActions->setAttribute('class', 'actions no-pad');

        Widget::registerSVGIcon(
            'list',
            '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="22px" height="12px" viewBox="0 0 22 12"><path fill="currentColor" d="M21,12H1c-0.6,0-1-0.4-1-1s0.4-1,1-1h20c0.6,0,1,0.4,1,1S21.6,12,21,12z"/><path fill="currentColor" d="M21,7H1C0.4,7,0,6.6,0,6s0.4-1,1-1h20c0.6,0,1,0.4,1,1S21.6,7,21,7z"/><path fill="currentColor" d="M21,2H1C0.4,2,0,1.6,0,1s0.4-1,1-1h20c0.6,0,1,0.4,1,1S21.6,2,21,2z"/></svg>'
        );

        foreach ($options as $key => $o) {
            $button = new XMLElement(
                'a',
                Widget::SVGIcon('list') . '<span><span>' . __($o) . '</span></span>'
            );
            $button->setAttribute('class', 'button');
            $button->setAttribute('href', "?list=$key");

            if ($key == $current) {
                $button->setAttribute('class', 'button active selected');
            }

            $li = new XMLElement('li');
            $li->appendChild($button);

            $tableActions->appendChild($li);
        }

        return $tableActions;
    }

    /**
     * Quick utility function to make a input field+label
     * @param string $settingName
     * @param string $textKey
     */
    public static function generateField($settingName, $textKey, $hasErrors, $errors, $type = 'text')
    {
        $inputText = ABF::instance()->getConfigVal($settingName);
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
        $label = Widget::Label();
        $label->setAttribute('class', 'column');
        $input = Widget::Input(
            'settings[' . ABF::SETTING_GROUP . '][' . $settingName .']',
            General::sanitize((string) $inputText),
            $type,
            $inputAttr
        );

        // set the input into the label
        if ($type == 'checkbox') {
            // put input first
            $label->setValue($input->generate() . ' ' . __($textKey));
        } else {
            $label->setValue(__($textKey). ' ' . $input->generate());
        }

        // error management
        if ($hasErrors && isset($errors[$settingName])) {
            $label = Widget::Error($label, $errors[$settingName]);
        }

        return $label;
    }
}
