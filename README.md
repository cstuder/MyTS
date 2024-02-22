# MyTS

Simple PHP package for keeping time series data in relational databases.

"For if all you have is a MySQL instance, everything looks like relational data."

[![Project Status: WIP – Initial development is in progress, but there has not yet been a stable, usable release suitable for the public.](https://www.repostatus.org/badges/latest/wip.svg)](https://www.repostatus.org/) [![PHPUnit tests](https://github.com/cstuder/php-skeleton/actions/workflows/test.yml/badge.svg)](https://github.com/cstuder/php-skeleton/actions/workflows/test.yml)

## Overview

XXX

## Usage

Sample usage code:

```php
// Connection
$myTS = MyTS::MyTSMySQLFactory('test', 'localhost', 'testuser', 'testpassword', 'testdb');

// Create database tables (Only if they don't exist yet)
$myTS->createDatabaseTables('DECIMAL(8,2)', TRUE);

// Create locations
$myTS->createLocation('here');
$myTS->createLocation('there');

// Update locations
$myTS->createLocation('here', ['where' => 'not there']);

// Show locations
var_dump($myTS->getAllLocations());

// Create parameters
$myTS->createParameter('aaa');
$myTS->createParameter('bbb', 'potatoes');
$myTS->createParameter('ccc', NULL, ['si' => FALSE]);

// Update parameter
$myTS->createParameter('ccc', 'kg', ['si' => TRUE]);

// Show parameters
var_dump($myTS->getAllParameters());

// Insert values
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
var_dump($myTS->insertValue('xxyyzz', 'xxyyzz', strtotime('2018-01-01 00:00:00'), 1, TRUE));

// Insert value in unknown location/parameter noisily
try {
    $myTS->insertValue('xxyyzz', 'xxyyzz', strtotime('2018-01-01 00:00:00'), 1, FALSE);
} catch (Exception $e) {
    var_dump($e);
}

// Get all values
var_dump($myTS->getValues());

// Get all values as table
var_dump(MyTS::convertResultToTable($myTS->getValues()));

// Get subset of values
var_dump($myTS->getValues(NULL, NULL, 'asbasdf'));
var_dump($myTS->getValues(NULL, NULL, 'here'));

// Get latest values
var_dump($myTS->getLatestValues());

// Get subset of lastest values
var_dump($myTS->getLatestValues('asbasdf'));
var_dump($myTS->getLatestValues('there'));
var_dump($myTS->getLatestValues(NULL, 'bbb'));
```

## Limitations

- Location and parameter names are case sensitive and can only be 128 characters long.
- There is no bulk insert (Which would be much faster when importing large datasets).
- It could work with other database engines too, but is not tested. (Uses the PDO library internally.)
- Databases typically return all fields as strings in PHP.

## Installation

`composer install`

## Development

XXX

## Testing

`composer run test`

## License

MIT
