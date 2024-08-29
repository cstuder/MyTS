<?php

require_once __DIR__ . '/../vendor/autoload.php';

use cstuder\MyTS\MyTS;

// Connection: Timeseries name, server, user, password, database
$myTS = MyTS::MyTSMySQLFactory('test', 'localhost', 'testuser', 'testpassword', 'testdb');

// Create database tables (Only if they don't exist yet), plus an additional info view
$myTS->createDatabaseTables('DECIMAL(8,2)', true);

// Create locations with optional metadata
$myTS->createLocation('here');
$myTS->createLocation('there', ['where' => 'exactly there']);

// Update locations with metadata
$myTS->createLocation('here', ['where' => 'not there']);

// Show locations
var_dump($myTS->getAllLocations());

// Create parameters with optional units and optional metadata
$myTS->createParameter('aaa');
$myTS->createParameter('bbb', 'potatoes');
$myTS->createParameter('ccc', NULL, ['si' => FALSE]);

// Update parameter
$myTS->createParameter('ccc', 'kg', ['si' => TRUE]);

// Show parameters
var_dump($myTS->getAllParameters());

// Insert values (Location and parameters are case sensitive)
$myTS->insertValue('here', 'aaa', strtotime('2018-01-01 00:00:00'), 1);
$myTS->insertValue('here', 'aaa', strtotime('2018-01-01 00:04:00'), -3);
$myTS->insertValue('here', 'aaa', strtotime('2018-01-01 00:02:00'), 1.567);
$myTS->insertValue('here', 'aaa', strtotime('2018-01-01 00:03:00'), 1.56789);
$myTS->insertValue('here', 'aaa', strtotime('2018-01-01 00:01:00'), 1.5);
$myTS->insertValue('there', 'bbb', strtotime('2018-01-01 00:01:00'), 1.51);
$myTS->insertValue('there', 'aaa', strtotime('2018-01-01 00:01:00'), 1.49);

// Update values
$myTS->insertValue('here', 'aaa', strtotime('2018-01-01 00:00:00'), -1);

// Insert value in unknown location/parameter silently
var_dump($myTS->insertValue('xxyyzz', 'xxyyzz', strtotime('2018-01-01 00:00:00'), 1, true));

// Insert value in unknown location/parameter noisily
try {
    $myTS->insertValue('xxyyzz', 'xxyyzz', strtotime('2018-01-01 00:00:00'), 1, false);
} catch (Exception $e) {
    var_dump($e);
}

// Get all values in this timeseries
var_dump($myTS->getValues());

// Get all values as table
var_dump(MyTS::convertResultToTable($myTS->getValues()));

// Get subset of values
var_dump($myTS->getValues(null, null, 'here'));
var_dump($myTS->getValues(null, null, 'asbasdf')); // Unknown location, fails silently

// Get latest values
var_dump($myTS->getLatestValues());

// Get subset of latest values
var_dump($myTS->getLatestValues('there'));
var_dump($myTS->getLatestValues(null, 'bbb'));
var_dump($myTS->getLatestValues('asbasdf')); // Unknown location, fails silently