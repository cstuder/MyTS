<?php

use cstuder\MyTS\MyTS;
use cstuder\ParseValueholder\Row;
use cstuder\ParseValueholder\Value;
use PHPUnit\Framework\TestCase;

final class MyTSTest extends TestCase
{
    /**
     * Object under test
     * 
     * @var MyTS
     */
    private static $myTs;

    public static function setUpBeforeClass(): void
    {
        $timeseries = 'test';
        $dsn = getenv('MYTS_DSN');

        if($dsn !== false) {
            self::$myTs = MyTS::MyTSFromDSNFactory($timeseries, $dsn);
        } else {
            self::$myTs = MyTS::MyTSMySQLFactory(
                $timeseries,
                'localhost',
                'testuser',
                'testpassword',
                'testdb'
            );
        }
    }

    protected function setUp(): void
    {
        self::$myTs->createDatabaseTables('FLOAT', true);
    }

    protected function tearDown(): void
    {
        self::$myTs->dropDatabaseTables();
    }

    public function testConstructor(): void
    {
        $ts = new MyTS('Test of a time-series, name cleaned up! 123', new PDO('sqlite::memory:'));

        $this->assertEquals('Testofatime-seriesnamecleanedup123', $ts->getTimeseriesName());
    }

    public function testLocations(): void
    {
        $this->assertEmpty(self::$myTs->getAllLocations());

        // First location
        self::$myTs->createLocation('testlocation');

        $locations = self::$myTs->getAllLocations();
        $this->assertCount(1, $locations);
        $this->assertArrayHasKey('testlocation', $locations);
        $this->assertEmpty($locations['testlocation']->details);

        // Second location
        self::$myTs->createLocation('testlocation2', ['key' => 'value']);

        $locations = self::$myTs->getAllLocations();
        $this->assertCount(2, $locations);
        $this->assertArrayHasKey('testlocation2', $locations);
        $this->assertEquals('value', $locations['testlocation2']->details->key);

        // Update first location
        self::$myTs->createLocation('testlocation', ['key1' => 'value1']);

        $locations = self::$myTs->getAllLocations();
        $this->assertCount(2, $locations);
        $this->assertArrayHasKey('testlocation', $locations);
        $this->assertEquals('value1', $locations['testlocation']->details->key1);
    }

    public function testParameters(): void
    {
        $this->assertEmpty(self::$myTs->getAllParameters());

        // First parameter
        self::$myTs->createParameter('testparameter1', 'unit1');

        $parameters = self::$myTs->getAllParameters();
        $this->assertCount(1, $parameters);
        $this->assertArrayHasKey('testparameter1', $parameters);
        $this->assertEquals('unit1', $parameters['testparameter1']->unit);
        $this->assertEmpty($parameters['testparameter1']->details);

        // Second parameter
        self::$myTs->createParameter('testparameter2', 'unit2', ['key2' => 'value2']);

        $parameters = self::$myTs->getAllParameters();
        $this->assertCount(2, $parameters);
        $this->assertArrayHasKey('testparameter2', $parameters);
        $this->assertEquals('unit2', $parameters['testparameter2']->unit);
        $this->assertEquals('value2', $parameters['testparameter2']->details->key2);

        // Update first parameter
        self::$myTs->createParameter('testparameter1', 'unit1_updated', ['key1' => 'value1']);

        $parameters = self::$myTs->getAllParameters();
        $this->assertCount(2, $parameters);
        $this->assertArrayHasKey('testparameter1', $parameters);
        $this->assertEquals('unit1_updated', $parameters['testparameter1']->unit);
        $this->assertEquals('value1', $parameters['testparameter1']->details->key1);
    }

    public function testInsertingValuesAndRetrieving() : void 
    {
        $this->assertEmpty(self::$myTs->getValues()->getValues());

        // Setup
        $location = 'testlocation';
        $parameter = 'testparameter1';
        $timestamp = 1234567890;
        $value = 1.23;
        self::$myTs->createLocation($location);
        self::$myTs->createParameter($parameter);

        // Insert first value
        self::$myTs->insertValue($location, $parameter, $timestamp, $value);

        $values = self::$myTs->getValues();
        $this->assertCount(1, $values);
        $v = $values->getValues()[0];
        $this->assertEquals($location, $v->location);
        $this->assertEquals($parameter, $v->parameter);
        $this->assertEquals($timestamp, $v->timestamp);
        $this->assertEquals($value, $v->value);
        
        // Don't insert second value
        $success = self::$myTs->insertValue($location, 'unknownparameter', 1234567891, 2.34, true);

        $values = self::$myTs->getValues();
        $this->assertFalse($success);
        $this->assertCount(1, $values);

        // Really insert second value
        self::$myTs->insertValue($location, $parameter, $timestamp + 10, 3.45);

        $values = self::$myTs->getValues();
        $this->assertCount(2, $values);

        // Get latest value
        $latest = self::$myTs->getLatestValues();

        $this->assertCount(1, $latest);
        $v = $latest->getValues()[0];
        $this->assertEquals($location, $v->location);
        $this->assertEquals($parameter, $v->parameter);
        $this->assertEquals($timestamp + 10, $v->timestamp);
        $this->assertEquals(3.45, $v->value);

        // Delete some values
        self::$myTs->deleteValuesOlderThan($timestamp + 5);

        $values = self::$myTs->getValues();
        $this->assertCount(1, $values);
        $v = $latest->getValues()[0];
        $this->assertEquals($location, $v->location);
        $this->assertEquals($parameter, $v->parameter);
        $this->assertEquals($timestamp + 10, $v->timestamp);
        $this->assertEquals(3.45, $v->value);

        // Update some values
        self::$myTs->insertValue($location, $parameter, $timestamp + 10, 4.56);

        $latest = self::$myTs->getLatestValues();

        $this->assertCount(1, $latest);
        $v = $latest->getValues()[0];
        $this->assertEquals($location, $v->location);
        $this->assertEquals($parameter, $v->parameter);
        $this->assertEquals($timestamp + 10, $v->timestamp);
        $this->assertEquals(4.56, $v->value);
    }

    public function testInsertingValuesAchronatically() : void 
    {
        $timestamp = 1234567890;
        self::$myTs->createLocation('testlocation');
        self::$myTs->createParameter('testparameter1');

        self::$myTs->insertValue('testlocation', 'testparameter1', $timestamp, 1);
        self::$myTs->insertValue('testlocation', 'testparameter1', $timestamp - 1, -1);

        $values = self::$myTs->getLatestValues();

        $this->assertCount(1, $values);
        $v = $values->getValues()[0];
        $this->assertEquals($timestamp, $v->timestamp);
        $this->assertEquals(1, $v->value);
    }

    public function testInsertingValuesAtUnknownLocation() : void 
    {
        $this->expectException(RuntimeException::class);

        self::$myTs->createParameter('testparameter1', 'unit1');
        self::$myTs->insertValue('unknownlocation', 'testparameter1', 1234567890, 1.23);
    }

    public function testInsertingValuesWithUnknownParameter() : void 
    {
        $this->expectException(RuntimeException::class);

        self::$myTs->createLocation('testlocation');
        self::$myTs->insertValue('testlocation', 'unknownparameter', 1234567890, 1.23);
    }

    public function testInsertingValuesAndFilteredRetrieving(): void 
    {
        $timestamp = 1234567890;
        
        self::$myTs->createLocation('testlocation1');
        self::$myTs->createLocation('testlocation2');       
        self::$myTs->createParameter('testparameter1');
        self::$myTs->createParameter('testparameter2');

        self::$myTs->insertValue('testlocation1', 'testparameter1', $timestamp, 1.23);
        self::$myTs->insertValue('testlocation1', 'testparameter2', $timestamp + 1, 2.34);
        self::$myTs->insertValue('testlocation2', 'testparameter1', $timestamp + 2, 3.45);
        self::$myTs->insertValue('testlocation2', 'testparameter2', $timestamp + 3, 4.56);

        // Retrieve
        $values = self::$myTs->getValues(null, null, 'testlocation1', 'testparameter1');
        $this->assertCount(1, $values);
        $this->assertEquals(1.23, $values->getValues()[0]->value);

        $values = self::$myTs->getValues(null, null, 'testlocation1');
        $this->assertCount(2, $values);
        $this->assertEquals(1.23, $values->getValues()[0]->value);
        $this->assertEquals(2.34, $values->getValues()[1]->value);

        $values = self::$myTs->getValues(null, null, null, 'testparameter2');
        $this->assertCount(2, $values);
        $this->assertEquals(2.34, $values->getValues()[0]->value);
        $this->assertEquals(4.56, $values->getValues()[1]->value);

        $values = self::$myTs->getValues($timestamp - 5, $timestamp - 1);
        $this->assertEmpty($values->getValues());

        $values = self::$myTs->getValues($timestamp, $timestamp);
        $this->assertCount(1, $values);
        $this->assertEquals(1.23, $values->getValues()[0]->value);

        $values = self::$myTs->getValues($timestamp + 3, null);
        $this->assertCount(1, $values);
        $this->assertEquals(4.56, $values->getValues()[0]->value);

        $values = self::$myTs->getValues(null, $timestamp + 1, null, 'testparameter1');
        $this->assertCount(1, $values);

        // Latest values
        $values = self::$myTs->getLatestValues('testlocation1', 'testparameter1');
        $this->assertCount(1, $values);
        $this->assertEquals(1.23, $values->getValues()[0]->value);

        $values = self::$myTs->getLatestValues(['testlocation1', 'testlocation2'], 'testparameter1');
        $this->assertCount(2, $values);

        $values = self::$myTs->getLatestValues(null, ['testparameter1']);
        $this->assertCount(2, $values);
    }

    public function testInsertingRows(): void {
        $timestamp = 1234567890;
        
        self::$myTs->createLocation('testlocation1');
        self::$myTs->createParameter('testparameter1');

        $row = new Row();
        
        $row->append(new Value($timestamp, 'testlocation1', 'testparameter1', 1.23));
        $row->append(new Value($timestamp + 1, 'testlocation1', 'testparameter1', 2.34));
        $row->append(new Value($timestamp + 2, 'testlocation1', 'testparameter1', 3.45));

        self::$myTs->insertValueRow($row);

        $values = self::$myTs->getValues();
        $this->assertCount(3, $values);
        $this->assertEquals(1.23, $values->getValues()[0]->value);
        $this->assertEquals(2.34, $values->getValues()[1]->value);
        $this->assertEquals(3.45, $values->getValues()[2]->value);
    }
}
