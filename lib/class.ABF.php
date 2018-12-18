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
        $validStatuses = Extension::EXTENSION_ENABLED;
        $status = Symphony::ExtensionManager()->fetchStatus(array('handle' => 'anti_brute_force'));
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
                    // Symphony::Database()
                    //     ->delete($this->TBL_ABF_BL)
                    //     ->all()
                    //     ->finalize()
                    //     ->execute()
                    //     ->success();
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
            $where = array();
            $where['LastAttempt'] = ['>' => time() - (60 * $length)];
            $where['FailedCount'] = ['>=' => $failedCount];
            $results = $this->getFailureByIp($ip, $where);

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
        $ip = $this->getIP($ip);
        $username = $username;
        $source = $source;
        $ua = $this->getUA();
        $rawip = $this->getRawClientIP();
        $results = $this->getFailureByIp($ip);
        $ret = false;

        if ($results != null && count($results) > 0) {
            // UPDATE
            $ret = Symphony::Database()
                ->update($this->TBL_ABF)
                ->set([
                    'RawIP' => $rawip,
                    'FailedCount' => '$FailedCount + 1',
                    'Username' => $username,
                    'UA' => $ua,
                    'Source' => $source,
                    'Hash' => 'UUID()',
                ])
                ->where(['IP' => $ip])
                ->execute()
                ->success();
        } else {
            // INSERT
            $ret = Symphony::Database()
                ->insert($this->TBL_ABF)
                ->values([
                    'IP' => $ip,
                    'RawIP' => $rawip,
                    'Username' => $username,
                    'FailedCount' => 1,
                    'UA' => $ua,
                    'Source' => $source,
                    'Hash' => 'UUID()',
                ])
                ->execute()
                ->success();
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
            . '<br/>' .
            __('You can ask your administrator to unlock your account or wait %s minutes.', array($length));

        if ($useUnbanViaEmail == 'on') {
            $msg .= ('<br/>' . __('Alternatively, you can <a href="%s">un-ban your IP by email</a>.', array(SYMPHONY_URL . self::UNBAND_LINK)));
        }

        // banned - throw exception
        throw new SymphonyException($msg, __('Banned IP address'));
    }

    /**
     *
     * Unregister IP from the banned table - even if max failed count is not reach
     * @param string $filter @optional will take current user's ip
     * can be the IP address or the hash value
     */
    public function unregisterFailure($filter='')
    {
        $filter = $this->getIP($filter);

        return Symphony::Database()
            ->delete($this->TBL_ABF)
            ->where(['or' => [
                ['IP' => $filter],
                ['Hash' => $filter],
            ]])
            ->execute()
            ->success();
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
        // do not do anything if ip is white listed
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

            return Symphony::Database()
                ->delete($this->TBL_ABF)
                ->where(['LastAttempt' => ['<' => time() - (60 * $length)]])
                ->execute()
                ->success();
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
        $source = $source;
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
            $ret = Symphony::Database()
                ->insert($tbl)
                ->values([
                    'IP' => $ip,
                    'Source' => $source,
                ])
                ->execute()
                ->success();
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
        return Symphony::Database()
            ->update($this->TBL_ABF_GL)
            ->set([
                'FailedCount' => '$FailedCount + 1',
            ])
            ->where(['IP' => $ip])
            ->execute()
            ->success();
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

        // var_dump('est');

        return count($this->__getListEntriesByIp($tbl, $ip)) > 0;
    }

    public function unregisterToList($color, $ip='')
    {
        return $this->__unregisterToList($this->getTableName($color), $ip);
    }

    private function __unregisterToList($tbl, $ip='')
    {
        $filter = $this->getIP($ip);

        return Symphony::Database()
            ->delete($tbl)
            ->where(['IP' => $filter])
            ->execute()
            ->success();
    }

    public function removeExpiredListEntries()
    {
        // in days
        $length = $this->getConfigVal(ABF::SETTING_GL_DURATION);

        return Symphony::Database()
            ->delete($this->TBL_ABF_GL)
            ->where(['DateCreated' => ['<' => time() - (60 * 60 * 24 * $length)]])
            ->execute()
            ->success();
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
            . '<br/>' .
            __('Ask your administrator to unlock your IP.');

        // banned - throw exception
        throw new SymphonyException($msg, __('Black listed IP address'));
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
    public function getFailureByIp($ip = '', $additionalWhere = array())
    {
        $ip = $this->getIP($ip);
        $q = Symphony::Database()
            ->select(['*'])
            ->from($this->TBL_ABF)
            ->where(['IP' => $ip]);

        foreach ($additionalWhere as $key => $value) {
            $q->where([$key => $value]);
        }

        return $q
            ->limit(1)
            ->execute()
            ->rows();
    }

    /**
     *
     * Method that returns all failures, optionally ordered
     * @param string $order @optional
     * @param string $orderOP @optional
     */
    public function getFailures($order = '', $orderOP = 'ASC')
    {
        $q = Symphony::Database()
            ->select(['*'])
            ->from($this->TBL_ABF);

        if (strlen($order) > 0) {
            $q->orderBy($order, $orderOP);
        }

        return $q
            ->execute()
            ->rows();
    }

    public function getBlackListEntriesByIP($ip = '', $additionalWhere = array())
    {
        return $this->__getListEntriesByIp($this->TBL_ABF_BL, $ip, $additionalWhere);
    }

    public function getGrayListEntriesByIP($ip = '', $additionalWhere = array())
    {
        return $this->__getListEntriesByIp($this->TBL_ABF_GL, $ip, $additionalWhere);
    }

    public function getWhiteListEntriesByIP($ip = '', $additionalWhere = array())
    {
        return $this->__getListEntriesByIp($this->TBL_ABF_WL, $ip, $additionalWhere);
    }

    public function getListEntriesByIP($color, $ip = '', $additionalWhere = array())
    {
        return $this->__getListEntriesByIp($this->getTableName($color), $ip, $additionalWhere);
    }

    private function __getListEntriesByIp($tbl, $ip = '', $additionalWhere = array())
    {
        $ip = $this->getIP($ip);
        $q = Symphony::Database()
            ->select(['*'])
            ->from($tbl)
            ->where(['IP' => $ip]);

        foreach ($additionalWhere as $key => $value) {
            $q->where([$key => $value]);
        }

        return $q
            ->limit(1)
            ->execute()
            ->rows();
    }

    public function getListEntries($color)
    {
        return $this->__getListEntries($this->getTableName($color));
    }

    private function __getListEntries($tbl, $where = array(), $order = 'IP', $orderOP = 'ASC')
    {
        $q = Symphony::Database()
            ->select(['*'])
            ->from($tbl);

        foreach ($where as $key => $value) {
            $q->where([$key => $value]);
        }

        return $q
            ->orderBy($order, $orderOP)
            ->execute()
            ->rows();
    }


    /**
     * Utilities
     */

    /**
     * @return boolean - validation for IP addresses
     */
    public function isIPValid($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP);
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
        $list = explode(',', $clientip);
        $clientip = trim(end($list));

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
            'developer' : null;
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
            // set config(name, value, group)
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
            $ret = $this->install_v2_0_2();
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

            // purge op cache to prevent errors
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate(CONFIG, true);
            }
        }

        return $ret;
    }

    private function install_v1_0()
    {
        return Symphony::Database()
            ->create($this->TBL_ABF)
            ->ifNotExists()
            ->fields([
                'IP' => 'varchar(16)',
                'LastAttempt' => 'timestamp',
                'FailedCount' => [
                    'type' => 'int(5)',
                    'default' => 1,
                ],
                'UA' => [
                    'type' => 'varchar(1024)',
                    'null' => true,
                ],
                'Username' => [
                    'type' => 'varchar(100)',
                    'null' => true,
                ],
                'Source' => [
                    'type' => 'varchar(100)',
                    'null' => true,
                ],
                'Hash' => 'char(36)',
            ])
            ->keys([
                'IP' => 'primary',
            ])
            ->execute()
            ->success();
    }

    private function install_v1_1()
    {
        // GRAY
        $retGL = Symphony::Database()
            ->create($this->TBL_ABF_GL)
            ->ifNotExists()
            ->fields([
                'IP' => 'varchar(16)',
                'DateCreated' => 'timestamp',
                'FailedCount' => [
                    'type' => 'int(5)',
                    'default' => 1,
                ],
                'Source' => [
                    'type' => 'varchar(100)',
                    'null' => true,
                ],
            ])
            ->keys([
                'IP' => 'primary',
            ])
            ->execute()
            ->success();

        //BLACK
        $retBL = Symphony::Database()
            ->create($this->TBL_ABF_BL)
            ->ifNotExists()
            ->fields([
                'IP' => 'varchar(16)',
                'DateCreated' => 'timestamp',
                'Source' => [
                    'type' => 'varchar(100)',
                    'null' => true,
                ],
            ])
            ->keys([
                'IP' => 'primary',
            ])
            ->execute()
            ->success();

        // WHITE
        $retWL = Symphony::Database()
            ->create($this->TBL_ABF_WL)
            ->ifNotExists()
            ->fields([
                'IP' => 'varchar(16)',
                'DateCreated' => 'timestamp',
                'Source' => [
                    'type' => 'varchar(100)',
                    'null' => true,
                ],
            ])
            ->keys([
                'IP' => 'primary',
            ])
            ->execute()
            ->success();

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
        return Symphony::Database()
            ->alter($this->TBL_ABF)
            ->add([
                'RawIP' => 'varchar(1024)',
            ])
            ->after('IP')
            ->execute()
            ->success();
    }

    private function install_v2_0_2()
    {
        $rets[] = Symphony::Database()
            ->alter($this->TBL_ABF)
            ->modify([
                'IP' => 'varchar(45)',
            ])
            ->execute()
            ->success();
        $rets[] = Symphony::Database()
            ->alter($this->TBL_ABF_WL)
            ->modify([
                'IP' => 'varchar(45)',
            ])
            ->execute()
            ->success();
        $rets[] = Symphony::Database()
            ->alter($this->TBL_ABF_GL)
            ->modify([
                'IP' => 'varchar(45)',
            ])
            ->execute()
            ->success();
        $rets[] = Symphony::Database()
            ->alter($this->TBL_ABF_BL)
            ->modify([
                'IP' => 'varchar(45)',
            ])
            ->execute()
            ->success();

        $rets[] = Symphony::Database()
            ->optimize($this->TBL_ABF)
            ->execute()
            ->success();
        $rets[] = Symphony::Database()
            ->optimize($this->TBL_ABF_WL)
            ->execute()
            ->success();
        $rets[] = Symphony::Database()
            ->optimize($this->TBL_ABF_GL)
            ->execute()
            ->success();
        $rets[] = Symphony::Database()
            ->optimize($this->TBL_ABF_BL)
            ->execute()
            ->success();

        if(in_array(false, $rets, true)) {
            return false;
        }
        else {
            return true;
        }
    }

    /**
     *
     * This method will update the extension according to the
     * previous and current version parameters.
     * @param string $previousVersion
     */
    public function update($previousVersion)
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

        // less than 2.0.2
        if ($ret && version_compare($previousVersion, '2.0.2') == -1) {
            $ret = $this->install_v2_0_2();
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
        $retABF = Symphony::Database()
            ->drop($this->TBL_ABF)
            ->ifExists()
                ->execute()
                ->success();

        // Black
        $retABF_BL = Symphony::Database()
            ->drop($this->TBL_ABF_BL)
            ->ifExists()
                ->execute()
                ->success();

        // Gray
        $retABF_GL = Symphony::Database()
            ->drop($this->TBL_ABF_GL)
            ->ifExists()
                ->execute()
                ->success();

        // White
        $retABF_WL = Symphony::Database()
            ->drop($this->TBL_ABF_WL)
            ->ifExists()
                ->execute()
                ->success();

        Symphony::Configuration()->remove(ABF::SETTING_GROUP, ABF::SETTING_GROUP);
        Symphony::Configuration()->write();

        $this->_isInstalled = false;

        return $retABF && $retABF_BL && $retABF_GL && $retABF_WL;
    }
}
