<?php

if (!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

/*
License: MIT
*/

require_once TOOLKIT . '/class.extensionmanager.php';

/**
 *
 * Symphony CMS leaverages the Decorator pattern with their <code>Extension</code> class.
 * This class is a Facade that implements <code>Singleton</code> and the methods
 * needed by the Decorator. It offers its methods via the <code>instance()</code> satic function
 * @author nicolasbrassard
 *
 */
class ABF implements Singleton
{
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
     * Key of the Gray list threshold setting
     * @var string
     */
    const SETTING_GL_THRESHOLD = 'gl-threshold';

    /**
     * Key of the Gray list duration setting
     * @var string
     */
    const SETTING_GL_DURATION = 'gl-duration';

    /**
     * Key of the restrict access setting
     * @var string
     */
    const SETTING_RESTRICT_ACCESS = 'restrict-access';

    /**
     * Key of the IP (REMOTE_ADDR) server field
     * @var string
     */
    const SETTING_REMOTE_ADDR = 'remote-addr-key';

    /**
     * Key of the group of setting
     * @var string
     */
    const SETTING_GROUP = 'anti-brute-force';

    /**
     * Defaults settings values
     * @var array->array
     */
    private $DEFAULTS = array (
        ABF::SETTING_GROUP => array (
            ABF::SETTING_LENGTH => 60,
            ABF::SETTING_FAILED_COUNT => 5,
            ABF::SETTING_AUTO_UNBAN => 'off',
            ABF::SETTING_GL_THRESHOLD => 5,
            ABF::SETTING_GL_DURATION => 30,
            ABF::SETTING_RESTRICT_ACCESS => 'off',
            ABF::SETTING_REMOTE_ADDR => 'REMOTE_ADDR'
        )
    );

    /**
     * Variable that holds the settings values
     */
    private $_settings = array();

    /**
     * Variable that hold true if the extension is installed
     * @var boolean
     */
    private $_isInstalled = false;

    /**
     * Short hand for the tables name
     * @var string
     */
    private $TBL_ABF = 'tbl_anti_brute_force';
    private $TBL_ABF_WL = 'tbl_anti_brute_force_wl';
    private $TBL_ABF_GL = 'tbl_anti_brute_force_gl';
    private $TBL_ABF_BL = 'tbl_anti_brute_force_bl';

    /**
     * All the different colors of the colored lists
     * @var array
     * @property
     */
    public $COLORS = array('black', 'gray', 'white');

    /**
     *
     * Holds the path to the "send me unband link" page
     * @var string
     */
    const UNBAND_LINK =  '/extension/anti_brute_force/login/';

    /**
     * Singleton implementation
     */
    private static $I = null;

    /**
     *
     * Singleton method
     * @return ABF
     */
    public static function instance()
    {
        if (self::$I == null) {
            self::$I = new ABF();
        }

        return self::$I;
    }

    // do not allow external creation
    private function __construct()
    {
        $s = Symphony::Configuration()->get();
        $this->_settings = $s[ABF::SETTING_GROUP];
        unset($s);

        // now an array
        $validStatuses = EXTENSION_ENABLED;
        $about = ExtensionManager::about('anti_brute_force');
        $status = ExtensionManager::fetchStatus($about);
        $this->_isInstalled = in_array($validStatuses, $status);

        // only if already installed
        if ($this->_isInstalled) {
            // assure access to settings
            // fail is not settings, since this is a security software
            if (count($this->_settings) < 1) {
                throw new Exception('Can not load settings. Can not continue.');
            }
        }
    }

    /**
     * FAILURES (BANNED IP) Public methods
     */

    /**
     * Do the actual ban check: throw exception if banned/black listed
     *
     */
    public function doBanCheck()
    {
        // check if not white listed
        if ($this->_isInstalled) {

            if (!$this->isWhiteListed()) {

                // check if blacklisted
                if ($this->isBlackListed()) {
                    // block access
                    $this->throwBlackListedException();
                }

                // check if banned
                if ($this->isCurrentlyBanned()) {
                    // block access
                    $this->throwBannedException();
                }
            }
        } else {
            // display alert about not being able to work
            if (Administration::instance()->Page instanceof AdministrationPage) {
                Administration::instance()->Page->pageAlert(
                    __("%s is not installed properly and won't work until this is fixed. Ensure latest version is installed.", array(extension_anti_brute_force::EXT_NAME)),
                    Alert::ERROR
                );
            }
        }
    }

    /**
     *
     * Check to see if the current user IP address is banned,
     * based on the parameters set in Configuration
     */
    public function isCurrentlyBanned($ip='')
    {
        if ($this->_isInstalled) {
            $length = $this->getConfigVal(ABF::SETTING_LENGTH);
            $failedCount = $this->getConfigVal(ABF::SETTING_FAILED_COUNT);
            $results = $this->getFailureByIp($ip, "
                AND UNIX_TIMESTAMP(LastAttempt) + (60 * $length) > UNIX_TIMESTAMP()
                AND FailedCount >= $failedCount");

            return count($results) > 0;
        }

        return false;
    }

    /**
     *
     * Register a failure - insert or update - for a IP
     * @param string $username - the username input
     * @param string $source - the source of the ban, normally the name of the extension
     * @param string $ip @optional - will take current user's ip
     */
    public function registerFailure($username, $source, $ip='')
    {
        $ip = MySQL::cleanValue($this->getIP($ip));
        $username = MySQL::cleanValue($username);
        $source = MySQL::cleanValue($source);
        $ua = MySQL::cleanValue($this->getUA());
        $rawip = MySQL::cleanValue($this->getRawClientIP());
        $results = $this->getFailureByIp($ip);
        $ret = false;

        if ($results != null && count($results) > 0) {
            // UPDATE
            $ret = Symphony::Database()->query("
                UPDATE $this->TBL_ABF
                    SET `RawIP` = '$rawip',
                        `LastAttempt` = NOW(),
                        `FailedCount` = `FailedCount` + 1,
                        `Username` = '$username',
                        `UA` = '$ua',
                        `Source` = '$source',
                        `Hash` = UUID()
                    WHERE IP = '$ip'
                    LIMIT 1
            ");

        } else {
            // INSERT
            $ret = Symphony::Database()->query("
                INSERT INTO $this->TBL_ABF (
                    `IP`,
                    `RawIP`,
                    `LastAttempt`,
                    `Username`,
                    `FailedCount`,
                    `UA`,
                    `Source`,
                    `Hash`
                ) VALUES (
                    '$ip',
                    '$rawip',
                    NOW(),
                    '$username',
                    1,
                    '$ua',
                    '$source',
                    UUID()
                )
            ");
        }

        return $ret;
    }

    /**
     *
     * Utility function that throw a properly formatted SymphonyErrorPage Exception
     * @throws SymphonyErrorPage
     */
    public function throwBannedException()
    {
        $length = $this->getConfigVal(ABF::SETTING_LENGTH);
        $useUnbanViaEmail = $this->getConfigVal(ABF::SETTING_AUTO_UNBAN);
        $msg =
            __('Your IP address is currently banned, due to typing too many wrong usernames/passwords.')
            . '<br/><br/>' .
            __('You can ask your administrator to unlock your account or wait %s minutes.', array($length));

        if ($useUnbanViaEmail == 'on') {
            $msg .= ('<br/><br/>' . __('Alternatively, you can <a href="%s">un-ban your IP by email</a>.', array(SYMPHONY_URL . self::UNBAND_LINK)));
        }

        // banned - throw exception
        throw new SymphonyErrorPage($msg, __('Banned IP address'));
    }

    /**
     *
     * Unregister IP from the banned table - even if max failed count is not reach
     * @param string $filter @optional will take current user's ip
     * can be the IP address or the hash value
     */
    public function unregisterFailure($filter='')
    {
        $filter = MySQL::cleanValue($this->getIP($filter));

        return Symphony::Database()->delete($this->TBL_ABF, "IP = '$filter' OR Hash = '$filter'");
    }

    /**
     * This method is a wrapper around multiple other methods.
     * It will:
     *   1. Check if the ip is whitelisted
     *   2. If not, will call `registerFailure`
     *   3. Check if the user is banned
     *   4. If so, register it into the gray list
     *      and move it to the black one if needed
     *
     * @since 1.4.6
     * @param string $username - the username input
     * @param string $source - the source of the ban, normally the name of the extension
     * @param string $ip @optional - will take current user's ip
     */
    public function authorLoginFailure($username, $source, $ip='')
    {
        // do not do anything is ip is white listed
        if (!$this->isWhiteListed($ip)) {

            // register failure in DB
            $this->registerFailure($username, $source, $ip);

            // if user is now banned
            if ($this->isCurrentlyBanned($ip)) {
                // register into gray list
                $this->registerToGrayList($source, $ip);
                // move to black list if necessary
                $this->moveGrayToBlack($source, $ip);
            }
        }
    }

    /**
     *
     * Delete expired entries
     */
    public function removeExpiredEntries()
    {
        // in minutes
        if ($this->_isInstalled) {
            $length = $this->getConfigVal(ABF::SETTING_LENGTH);

            return Symphony::Database()->delete($this->TBL_ABF, "UNIX_TIMESTAMP(LastAttempt) + (60 * $length) < UNIX_TIMESTAMP()");
        }
    }



    /**
     * Database Data queries - COLORED (B/G/W) Public methods
     */

    public function registerToBlackList($source, $ip='')
    {
        return $this->__registerToList($this->TBL_ABF_BL, $source, $ip);
    }
    public function registerToGrayList($source, $ip='')
    {
        return $this->__registerToList($this->TBL_ABF_GL, $source, $ip);
    }
    public function registerToWhiteList($source, $ip='')
    {
        return $this->__registerToList($this->TBL_ABF_WL, $source, $ip);
    }

    public function registerToList($color, $source, $ip='')
    {
        return $this->__registerToList($this->getTableName($color), $source, $ip);
    }

    private function __registerToList($tbl, $source, $ip='')
    {
        $ip = $this->getIP($ip);
        $results = $this->__isListed($tbl, $ip);
        $isGray = $tbl == $this->TBL_ABF_GL;
        $ret = false;

        // do not re-register existing entries
        if ($results != null && count($results) > 0) {
            if ($isGray) {
                $ret = $this->incrementGrayList($ip);
            }
        } else {
            // INSERT -- gray list will get the default values for others columns
            $ret = Symphony::Database()->query("
                INSERT INTO $tbl
                    (`IP`, `DateCreated`, `Source`)
                    VALUES
                    ('$ip', NOW(),        '$source')
            ");
        }

        return $ret;
    }

    public function moveGrayToBlack($source, $ip='')
    {
        $gray = $this->getGrayListEntriesByIP($ip);
        if (is_array($gray) && !empty($gray)) {
            if ($gray[0]->FailedCount >= $this->getConfigVal(ABF::SETTING_GL_THRESHOLD)) {
                $this->registerToBlackList($source, $ip);
            }
        }
    }

    private function incrementGrayList($ip)
    {
        $tbl = $this->TBL_ABF_GL;
        // UPDATE -- only Gray list
        return Symphony::Database()->query("
            UPDATE $tbl
                SET `FailedCount` = `FailedCount` + 1
                WHERE IP = '$ip'
                LIMIT 1
        ");
    }

    public function isBlackListed($ip='')
    {
        return $this->__isListed($this->TBL_ABF_BL, $ip);
    }

    public function isGrayListed($ip='')
    {
        return $this->__isListed($this->TBL_ABF_GL, $ip);
    }

    public function isWhiteListed($ip='')
    {
        return $this->__isListed($this->TBL_ABF_WL, $ip);
    }

    public function isListed($color, $ip='')
    {
        return $this->__isListed($this->getTableName($color), $ip);
    }

    private function __isListed($tbl, $ip='')
    {
        $ip = $this->getIP($ip);

        return count($this->__getListEntriesByIp($tbl, $ip, NULL, false)) > 0;
    }

    public function unregisterToList($color, $ip='')
    {
        return $this->__unregisterToList($this->getTableName($color), $ip);
    }

    private function __unregisterToList($tbl, $ip='')
    {
        $filter = MySQL::cleanValue($this->getIP($ip));

        return Symphony::Database()->delete($tbl, "IP = '$filter'");
    }

    public function removeExpiredListEntries()
    {
        // in days
        $length = $this->getConfigVal(ABF::SETTING_GL_DURATION);

        return Symphony::Database()->delete($this->TBL_ABF_GL, "UNIX_TIMESTAMP(DateCreated) + (60 * 60 * 24 * $length) < UNIX_TIMESTAMP()");
    }

    /**
     *
     * Utility function that throw a properly formatted SymphonyErrorPage Exception
     * @param string $length - length of block in minutes
     * @param boolean
     * @throws SymphonyErrorPage
     */
    public function throwBlackListedException()
    {
        $msg =
            __('Your IP address is currently <strong>black listed</strong>, due to too many bans.')
            . '<br/><br/>' .
            __('Ask your administrator to unlock your IP.');

        // banned - throw exception
        throw new SymphonyErrorPage($msg, __('Black listed IP address'));
    }



    /**
     * Database Data queries - FAILURES
     */

    /**
     *
     * Method that returns failures based on IP address and other filters
     * @param string $ip the ip in the select query
     * @param string $additionalWhere @optional additional SQL filters
     */
    public function getFailureByIp($ip='', $additionalWhere='')
    {
        $ip = $this->getIP($ip);
        $where = "IP = '$ip'";
        if (strlen($additionalWhere) > 0) {
            $where .= $additionalWhere;
        }
        $sql ="
            SELECT * FROM $this->TBL_ABF WHERE $where LIMIT 1
        " ;

        $rets = array();

        if (Symphony::Database()->query($sql)) {
            $rets = Symphony::Database()->fetch();
        }

        return $rets;
    }

    /**
     *
     * Method that returns all failures, optionally ordered
     * @param string $orderedBy @optional
     */
    public function getFailures($orderedBy='')
    {
        $order = '';
        if (strlen($orderedBy) > 0) {
            $order .= (' ORDER BY ' . $orderedBy);
        }
        $sql ="
            SELECT * FROM $this->TBL_ABF $order
        " ;

        $rets = array();

        if (Symphony::Database()->query($sql)) {
            $rets = Symphony::Database()->fetch();
        }

        return $rets;
    }

    public function getBlackListEntriesByIP($ip='', $additionalWhere='')
    {
        return $this->__getListEntriesByIp($this->TBL_ABF_BL, $ip, $additionalWhere);
    }

    public function getGrayListEntriesByIP($ip='', $additionalWhere='')
    {
        return $this->__getListEntriesByIp($this->TBL_ABF_GL, $ip, $additionalWhere);
    }

    public function getWhiteListEntriesByIP($ip='', $additionalWhere='')
    {
        return $this->__getListEntriesByIp($this->TBL_ABF_WL, $ip, $additionalWhere);
    }

    public function getListEntriesByIP($color, $ip='', $additionalWhere='')
    {
        return $this->__getListEntriesByIp($this->getTableName($color), $ip, $additionalWhere);
    }

    private function __getListEntriesByIp($tbl, $ip='', $additionalWhere='')
    {
        $ip = $this->getIP($ip);

        $where = "IP = '$ip'";
        if (strlen($additionalWhere) > 0) {
            $where .= $additionalWhere;
        }

        $sql ="
            SELECT * FROM $tbl WHERE $where LIMIT 1
        " ;

        $rets = array();

        if (Symphony::Database()->query($sql)) {
            $rets = Symphony::Database()->fetch();
        }

        return $rets;
    }

    public function getListEntries($color)
    {
        return $this->__getListEntries($this->getTableName($color));
    }

    private function __getListEntries($tbl, $where='', $order='IP ASC')
    {
        if (strlen($where) > 0) {
            $where = 'WHERE ' . $where;
        }
        $sql ="
            SELECT * FROM $tbl $where ORDER BY $order
        " ;

        $rets = array();

        if (Symphony::Database()->query($sql)) {
            $rets = Symphony::Database()->fetch();
        }

        return $rets;
    }


    /**
     * Utilities
     */

    /**
     * @return boolean - Really simple validation for IP Addresses
     */
    public function isIPValid($ip)
    {
        // ip v4 is at least 7 char max 15
        // hash is 36 char
        return strlen($ip) > 6 && strlen($ip) < 16;
    }

    /**
     * @return the $ip param if valid. If not, it returns the getenv(field) specified
     *   by the ABF::SETTING_REMOTE_ADDR setting or the REMOTE_ADDR
     */
    public function getIP($ip='')
    {
        if ($this->isIPValid($ip)) {
            return trim($ip);
        }

        // get the client ip
        $clientip = $this->getRawClientIP();

        // extract the last item from the list
        $clientip = trim(end(explode(',', $clientip)));

        return $clientip;
    }

    /**
     * @return the raw client IP env field value
     */
    public function getRawClientIP()
    {
        // Get the name of the field via settings
        $ipField = $this->_settings[ABF::SETTING_REMOTE_ADDR];
        $ipEnvValue = null;
        // If the setting is not empty
        if (!empty($ipField)) {
            // Get the value
            $ipEnvValue = @getenv($ipField);
        }
        // use user defined or fallback on Symphony's defined value
        $clientip = $ipEnvValue !== false && !empty($ipEnvValue) ?
            $ipEnvValue :
            REMOTE_ADDR;

        return $clientip;
    }

    private function getUA()
    {
        // Symphony's defined constant
        return HTTP_USER_AGENT;
    }

    private function getTableName($color)
    {
        $tbl = '';
        switch ($color) {
            case $this->COLORS[0]:
                $tbl = $this->TBL_ABF_BL;
                break;
            case $this->COLORS[1]:
                $tbl = $this->TBL_ABF_GL;
                break;
            case $this->COLORS[2]:
                $tbl = $this->TBL_ABF_WL;
                break;
            default:
                throw new Exception(vsprintf("'%s' is not a know color", $color == null ? 'NULL' : $color));
        }

        return $tbl;
    }

    /**
     * @return The email settings for the default Email Gateway
     */
    public function getEmailSettings()
    {
        $emailGateway = Symphony::Configuration()->get('default_gateway', 'email');
        $emailSettings = Symphony::Configuration()->get('email_'.$emailGateway);

        return is_array($emailSettings) ? $emailSettings : null;
    }




    /**
     * SETTINGS
     */

    public function getNaviguationGroup()
    {
        return $this->getConfigVal(ABF::SETTING_RESTRICT_ACCESS) == 'on' ?
            'developer' : NULL;
    }

    /**
     *
     * Utility function that returns settings from this extensions settings group
     * @param string $key
     */
    public function getConfigVal($key)
    {
        return $this->_settings[$key];
    }

    /**
     *
     * Save one parameter, passed in the $context array
     * @param array $context
     * @param array $errors
     * @param string $key
     * @param string $autoSave @optional
     */
    public function setConfigVal(&$context, &$errors, $key, $autoSave = true, $type = 'numeric')
    {
        // get the input
        $input = $context['settings'][ABF::SETTING_GROUP][$key];
        $iVal = intval($input);

        $valid = false;
        $error = __('An error occured');

        switch ($type) {
            case 'checkbox':
                $valid = true;
                $input = $input == 'on' ? 'on' : 'off';
                break;
            case 'numeric':
                $error = __('"%s" is not a valid positive integer',  array($input));
                $valid = strlen($input) > 0 && is_int($iVal) && $iVal > 0;
                $input = $iVal;
                break;
        }

        // verify it is a good domain
        if ($valid) {
            // set config                    (name, value, group)
            Symphony::Configuration()->set($key, $input, ABF::SETTING_GROUP);
            $this->_settings[$key] = $input;

            // save it
            if ($autoSave) {
                Symphony::Configuration()->write();
            }

        } else {
            // don't save

            // append to local array
            $errors[$key] = $error;

            // add an error into the stack
            $context['errors'][ABF::SETTING_GROUP][$key] = $error;
        }
    }

    /**
     * Database Data Definition Queries
     */

    /**
     *
     * This method will install the plugin
     */
    public function install(&$ext_driver)
    {
        $ret = $this->install_v1_0() && $this->install_v1_1();
        if ($ret) {
            $ret = $this->install_v1_3_1() && $this->install_v1_3_4();
        }
        if ($ret) {
            $ret = $this->install_v1_4_5();
        }
        if ($ret) {
            // set default values
            $pseudo_context = array(
                'settings' => $this->DEFAULTS
            );

            // *** load settings in memory
            // Even if we just installed the ext, a ban check will be done,
            // so we need settings: use defaults
            $this->_settings = empty($this->_settings) ? $this->DEFAULTS : $this->_settings;

            $ext_driver->save($pseudo_context);
        }

        return $ret;
    }

    private function install_v1_0()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS $this->TBL_ABF(
                `IP` VARCHAR( 16 ) NOT NULL ,
                `LastAttempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                `FailedCount` INT( 5 ) unsigned NOT NULL DEFAULT  '1',
                `UA` VARCHAR( 1024 ) NULL,
                `Username` VARCHAR( 100 ) NULL,
                `Source` VARCHAR( 100 ) NULL,
                `Hash` CHAR( 36 ) NOT NULL,
                PRIMARY KEY ( `IP` )
            ) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ";

        return Symphony::Database()->query($sql);
    }

    private function install_v1_1()
    {
        // GRAY
        $sql = "
            CREATE TABLE IF NOT EXISTS $this->TBL_ABF_GL (
                `IP` VARCHAR( 16 ) NOT NULL ,
                `DateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                `FailedCount` INT( 5 ) unsigned NOT NULL DEFAULT  '1',
                `Source` VARCHAR( 100 ) NULL,
                PRIMARY KEY (  `IP` )
            ) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ";

        $retGL = Symphony::Database()->query($sql);

        //BLACK
        $sql = "
            CREATE TABLE IF NOT EXISTS $this->TBL_ABF_BL (
                `IP` VARCHAR( 16 ) NOT NULL ,
                `DateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                `Source` VARCHAR( 100 ) NULL,
                PRIMARY KEY (  `IP` )
            ) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ";

        $retBL = Symphony::Database()->query($sql);

        // WHITE
        $sql = "
            CREATE TABLE IF NOT EXISTS $this->TBL_ABF_WL (
                `IP` VARCHAR( 16 ) NOT NULL ,
                `DateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                `Source` VARCHAR( 100 ) NULL,
                PRIMARY KEY (  `IP` )
            ) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ";

        $retWL = Symphony::Database()->query($sql);

        return $retGL && $retBL && $retWL;
    }

    private function install_v1_3_1()
    {
        Symphony::Configuration()->set(ABF::SETTING_RESTRICT_ACCESS, 'off', ABF::SETTING_GROUP);
        Symphony::Configuration()->write();

        return true;
    }

    private function install_v1_3_4()
    {
        Symphony::Configuration()->set(ABF::SETTING_REMOTE_ADDR, 'REMOTE_ADDR', ABF::SETTING_GROUP);
        Symphony::Configuration()->write();

        return true;
    }

    private function install_v1_4_5()
    {
        $sql = "
            ALTER TABLE $this->TBL_ABF
                ADD COLUMN `RawIP` VARCHAR( 1024 ) NOT NULL
                AFTER `IP`
        ";

        return Symphony::Database()->query($sql);
    }

    /**
     *
     * This method will update the extension according to the
     * previous and current version parameters.
     * @param string $previousVersion
     * @param string $currentVersion
     */
    public function update($previousVersion, $currentVersion)
    {
        $ret = true;

        // less than 1.1
        if ($ret && version_compare($previousVersion, '1.1') == -1) {
            $ret = $this->install_v1_1();
        }

        // less than 1.3.1
        if ($ret && version_compare($previousVersion, '1.3.1') == -1) {
            $ret = $this->install_v1_3_1();
        }

        // less than 1.3.4
        if ($ret && version_compare($previousVersion, '1.3.4') == -1) {
            $ret = $this->install_v1_3_4();
        }

        // less than 1.4.5
        if ($ret && version_compare($previousVersion, '1.4.5') == -1) {
            $ret = $this->install_v1_4_5();
        }

        return $ret;
    }

    /**
     *
     * This method will uninstall the extension
     */
    public function uninstall()
    {
        // Banned IPs
        $sql = "
            DROP TABLE IF EXISTS $this->TBL_ABF
        ";

        $retABF = Symphony::Database()->query($sql);

        // Black
        $sql = "
            DROP TABLE IF EXISTS $this->TBL_ABF_BL
        ";

        $retABF_BL = Symphony::Database()->query($sql);

        // Gray
        $sql = "
            DROP TABLE IF EXISTS $this->TBL_ABF_GL
        ";

        $retABF_GL = Symphony::Database()->query($sql);

        // White
        $sql = "
            DROP TABLE IF EXISTS $this->TBL_ABF_WL
        ";

        $retABF_WL = Symphony::Database()->query($sql);

        Symphony::Configuration()->remove(ABF::SETTING_GROUP, ABF::SETTING_GROUP);
        Symphony::Configuration()->write();

        $this->_isInstalled = false;

        return $retABF && $retABF_BL && $retABF_GL && $retABF_WL;
    }
}
