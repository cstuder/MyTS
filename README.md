# MyTS

Simple PHP package for keeping time series data in relational databases.

"For if all you have is a MySQL instance, everything looks like relational data."

[![Project Status: WIP – Initial development is in progress, but there has not yet been a stable, usable release suitable for the public.](https://www.repostatus.org/badges/latest/wip.svg)](https://www.repostatus.org/) [![Automated tests](https://github.com/cstuder/MyTS/actions/workflows/test.yml/badge.svg)](https://github.com/cstuder/MyTS/actions/workflows/test.yml)

## Overview

This library is used to store meteorolgical time series measured at weather stations for the [api.existenz.ch](https://api.existenz.ch) project. It is a simple and lightweight solution for storing time series data in a relational database.

First create some locations with `createLocation('locationname')`, then some parameters with `createParameter('parametername', 'unit')`.

Insert values with `insertValue('locationname', 'parametername', $timestamp, $value)`.

Retrieve values with `getValues()` or `getLatestValues()` and you will get a `\cstuder\ParseValueholder\Row` filled with `\cstuder\ParseValueholder\Value` objects:

```php
$row->getValues();

->

[
    Value {
        location => 'locationname',
        parameter => 'parametername',
        timestamp => 1234567890,
        value => '1.23'
    }
]
```

### Setup database

The package uses a connection to a MySQL compatible database (using `MyTS::MyTSMySQLFactory` or `MyTS::MyTSFromDSNFactory`) and a timeseries name to create four tables:

- `myts_timeseriesname_loc` for locations and their metadata
- `myts_timeseriesname_par` for parameters, their unit and metadata
- `myts_timeseriesname_val` for values
- `myts_timeseriesname_latest` for latest values
- Optional: `myts_timeseriesname_view` with an aggregated view

The values default to to the type `FLOAT`, but can be changed to any other time at the time of creation with `createDatabaseTables()`.

First create some locations with `createLocation('locationname')`, then some parameters with `createParameter('parametername', 'unit')`. Both methods accept an optional key-value array which is serialized into the database.

To update a location or parameter, simply call `createLocation()` or `createParameter()` again with the same name and updated metadata. You cannot change the name of a location or parameter.

### Insert values

Insert values with `insertValue('locationname', 'parametername', $timestamp, $value)`. The timestamp is a Unix timestamp. Location and parameter names are case sensitive. The method will return `true` if successful. It will throw an exception if the insertion fails (I.e. if the location or parameter doesn't exist). Set the last parameter to `true` fail silently and just return `false`.

To update a previous values, the same `insertValue()` method can be used.

The convenience methods `insertValueObject()` and `insertValueRow()` can be used to insert values from a `Value` object or a `Row` object respectively.

### Retrieve values

Use `getValues()` to retrieve values from the timeseries. The method accepts optional start and end times. Filtering by locations and parameters is also available (By default unknown location or parameter names will be ignored, but this can by changed by setting the parameter `$failSilenty` to `false`).

If no location and/or parameter names are given, all locations and/or parameters respectively will be returned.

For quickly retrieving the latest values, use `getLatestValues()`. It accepts optional location and parameter names.

Data is returned as a `\cstuder\ParseValueholder\Row` which is filled by an array of `\cstuder\ParseValueholder\Value` objects:

```php
$row->getValues();

->

[
    Value {
        location => 'locationname',
        parameter => 'parametername',
        timestamp => 1234567890,
        value => '1.23'
    }
]
```

### Maintenance

The method `deleteValuesOlderThan()` indiscriminately deletes all values older than a given timestamp. Use with caution.

### Limitations

- Location and parameter names are case sensitive and can only be 128 characters long.
- There is no bulk insert (Which would be much faster when importing large datasets).
- It could work with other database engines (MariaDB etc.) too, but is not tested. (Uses the PDO library internally.)
- Databases typically return all fields as strings in PHP.

## Usage

Sample usage code (Runnable version in [`sample_usage.php`](docs/sample_usage.php))

```php
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

// Get subset of values
var_dump($myTS->getValues(null, null, 'here'));
var_dump($myTS->getValues(null, null, 'asbasdf')); // Unknown location, fails silently

// Get latest values
var_dump($myTS->getLatestValues());

// Get subset of latest values
var_dump($myTS->getLatestValues('there'));
var_dump($myTS->getLatestValues(null, 'bbb'));
var_dump($myTS->getLatestValues('asbasdf')); // Unknown location, fails silently
```

## Installation

`composer require cstuder/myts`

## Testing

`composer run test`

Requires a running MySQL compatible database on localhost/127.0.0.1 with the following credentials: `testuser`, `testpassword`, `testdb`.

Can be overwritten by a valid DSN including username and password in the environment variable `MYTS_DSN`, for example:

`mysql:host=localhost;dbname=testdb;user=testuser;password=testpassword;charset=utf8`

## Release

See [CHANGELOG.md](CHANGELOG.md) for the release history.

1. Add changes to the [changelog](CHANGELOG.md).
1. Create a new tag `vX.X.X`.
1. Push.

## License

MIT
