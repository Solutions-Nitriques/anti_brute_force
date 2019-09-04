<?php

define('DOCROOT', str_replace('/extensions/anti_brute_force/cron', '', rtrim(dirname(__FILE__), '\\/') ));

if (file_exists(DOCROOT . '/vendor/autoload.php')) {
	require_once(DOCROOT . '/vendor/autoload.php');
	require_once(DOCROOT . '/symphony/lib/boot/bundle.php');
} else {
	die('Failed to load symphony.');
}

// creates the DB
Administration::instance();

require_once(DOCROOT . '/extensions/anti_brute_force/extension.driver.php');

if (!ABF::instance()->removeExpiredEntries()) {
	die('Failed to delete');
}
