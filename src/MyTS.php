<?php
declare(strict_types=1);

namespace cstuder\MyTS;

/**
 * MySQL Time Series
 * 
 * Create, fill and query time series stored in a MySQL database.
 * 
 * "For if all you have is a MySQL instance, everything looks like relational data."
 * 
 * Nothing too exotic being done on the tables, this should be working with other database
 * engines too.
 * 
 * Note: Locations and parameters names are case sensitive! (Not on the database level, but on
 * the PHP level.)
 * 
 * @author Christian Studer <cstuder@existenz.ch>
 * @license MIT
 */
class MyTS
{
    /**
     * Database connection
     * 
     * @var \PDO
     */
    protected \PDO $db;

    /**
     * Time series name
     * 
     * @var string
     */
    protected string $ts;

    /**
     * Locations cache
     * 
     * @var array
     */
    protected array $locationsCache = [];

    /**
     * Parameters cache
     * 
     * @var array
     */
    protected array $parametersCache = [];

    /**
     * Value insert query cache
     * 
     * @var PDOStatement
     */
    protected ?\PDOStatement $valueInsertQueryCache = null;

    /**
     * Value insert latest query cache
     * 
     * @var PDOStatement
     */
    protected ?\PDOStatement $valueInsertLatestQueryCache = null;

    /**
     * Locations table name
     *
     * @var string
     */
    protected string $locationsTable = '';

    /**
     * Parameters table name
     *
     * @var string
     */
    protected string $parametersTable = '';

    /**
     * Values table name
     *
     * @var string
     */
    protected string $valuesTable = '';

    /**
     * Latest values table name
     * 
     * @var string
     */
    protected string $latestValuesTable = '';

    /**
     * Info view name
     * 
     * @param string
     */
    protected string $infoView = '';

    /**
     * Create new MyTS instance for a specific time series
     *
     * @param string $timeseriesName
     * @param \PDO $databaseConnection
     */
    public function __construct(string $timeseriesName, \PDO $databaseConnection)
    {
        $this->ts = $this->normalizeTimeseriesName($timeseriesName);
        $this->db = $databaseConnection;

        // We're creating the table names here so they could be overwritten later.
        // Use case: Have two different values tables with the same locations.
        $this->locationsTable = "myts_{$this->ts}_loc";
        $this->parametersTable = "myts_{$this->ts}_par";
        $this->valuesTable = "myts_{$this->ts}_val";
        $this->latestValuesTable = "myts_{$this->ts}_latest";
        $this->infoView = "myts_{$this->ts}_view";
    }

    /**
     * Get cleaned time series name
     * 
     * @return string
     */
    public function getTimeseriesName(): string
    {
        return $this->ts;
    }

    /**
     * Clean up timeseries name
     * 
     * Removes most special characters.
     * 
     * @param string $timeseriesName
     * @return string cleaned name
     */
    private function normalizeTimeseriesName(string $timeseriesName): string
    {
        return preg_replace('/[^A-Za-z0-9\-_]/', '', $timeseriesName);
    }

    /**
     * Create database tables for this time series
     * 
     * Choose a MySQL data type definition string to set
     * the time series value fields data type.
     * 
     * Examples:
     * - INT(11)
     * - UNSIGNED INT(11)
     * - DECIMAL(10,2)
     * - FLOAT
     * - VARCHAR(8)
     * 
     * Note: This string is passed to the database unescaped!
     * 
     * Optionally create an info view for manual debugging purposes.
     *
     * @param string $valueDataType
     * @param bool $createInfoView
     * @link https://dev.mysql.com/doc/refman/5.7/en/data-types.html
     * @throws \RuntimeException
     */
    public function createDatabaseTables(string $valueDataType = 'FLOAT', bool $createInfoView = true): void
    {
        // Create locations table
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->locationsTable}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(128) NOT NULL DEFAULT '',
            `details` text DEFAULT NULL COMMENT 'JSON-encoded',
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $result = $this->db->query($sql);
        if (!$result) throw new \RuntimeException("Unable to create locations table: " . $this->db->errorInfo()[2]);

        // Create parameters table
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->parametersTable}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(128) NOT NULL DEFAULT '',
            `unit` varchar(128) DEFAULT NULL,
            `details` text DEFAULT NULL COMMENT 'JSON-encoded',
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $result = $this->db->query($sql);
        if (!$result) throw new \RuntimeException("Unable to create parameters table: " . $this->db->errorInfo()[2]);

        // Create values table
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->valuesTable}` (
            `timestamp` int(11) NOT NULL,
            `loc` int(11) unsigned NOT NULL,
            `par` int(11) unsigned NOT NULL,
            `val` {$valueDataType} DEFAULT NULL,
             PRIMARY KEY (`timestamp`,`loc`,`par`),
            CONSTRAINT `fk_{$this->ts}_loc` FOREIGN KEY (`loc`) REFERENCES `{$this->locationsTable}` (`id`),
            CONSTRAINT `fk_{$this->ts}_par` FOREIGN KEY (`par`) REFERENCES `{$this->parametersTable}` (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $result = $this->db->query($sql);
        if (!$result) throw new \RuntimeException("Unable to create values table: " . $this->db->errorInfo()[2]);

        // Create latest values table
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->latestValuesTable}` (
            `timestamp` int(11) NOT NULL,
            `loc` int(11) unsigned NOT NULL,
            `par` int(11) unsigned NOT NULL,
            `val` {$valueDataType} DEFAULT NULL,
            PRIMARY KEY (`loc`,`par`),
            CONSTRAINT `fk_{$this->ts}_latest_loc` FOREIGN KEY (`loc`) REFERENCES `{$this->locationsTable}` (`id`),
            CONSTRAINT `fk_{$this->ts}_latest_par` FOREIGN KEY (`par`) REFERENCES `{$this->parametersTable}` (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        $result = $this->db->query($sql);
        if (!$result) throw new \RuntimeException("Unable to create latest values table: " . $this->db->errorInfo()[2]);

        // Create info view if required
        if ($createInfoView) {
            $sql = "CREATE OR REPLACE VIEW `{$this->infoView}` AS
                SELECT CONCAT(FROM_UNIXTIME(`{$this->valuesTable}`.timestamp, '%Y-%m-%d %H:%i:%s'), TIME_FORMAT(NOW()-UTC_TIMESTAMP(), ' UTC+%H')) AS `datetime`, `{$this->locationsTable}`.name AS loc, `{$this->parametersTable}`.name AS par, `{$this->valuesTable}`.val
            FROM `{$this->valuesTable}`
            JOIN `{$this->locationsTable}` ON `{$this->valuesTable}`.loc = `{$this->locationsTable}`.id
            JOIN `{$this->parametersTable}` ON `{$this->valuesTable}`.par = `{$this->parametersTable}`.id
            ORDER BY `{$this->valuesTable}`.timestamp DESC, loc ASC, par ASC
            ;";

            $result = $this->db->query($sql);
            if (!$result) throw new \RuntimeException("Unable to create info view: " . $this->db->errorInfo()[2]);
        }
    }

    /**
     * Create or update a new location for this time series
     * 
     * Name needs to be unique strings, cropped at 256 characters.
     * 
     * For arbitrary details about this location, use the freeform $details array.
     * 
     * @param string $name
     * @param array $details
     * @throws \RuntimeException
     */
    public function createLocation(string $name, array $details = []): void
    {
        $sql = "INSERT INTO `{$this->locationsTable}` (`name`, `details`)
                VALUES (:name, :details) ON DUPLICATE KEY UPDATE `name`=:name, `details`=:details";

        $query = $this->db->prepare($sql);
        $result = $query->execute([':name' => $name, ':details' => json_encode($details)]);

        if (!$result) throw new \RuntimeException("Unable to create location: " . $this->db->errorInfo()[2]);

        // Invalidate cache
        $this->locationsCache = [];
    }

    /**
     * Create or update a parameter for this time series
     * 
     * Name need to be unique strings, cropped at 256 characters.
     * 
     * For arbitrary details about this parameter, use the freeform $details array.
     * 
     * @param string $name
     * @param ?string $unit
     * @param array $details
     * @throws \RuntimeException
     */
    public function createParameter(string $name, ?string $unit = null, array $details = []): void
    {
        $sql = "INSERT INTO `{$this->parametersTable}` (`name`, `unit`, `details`)
                VALUES (:name, :unit, :details) ON DUPLICATE KEY UPDATE `name`=:name, `unit`=:unit, `details`=:details";

        $query = $this->db->prepare($sql);
        $result = $query->execute([':name' => $name, ':unit' => $unit, ':details' => json_encode($details)]);

        if (!$result) throw new \RuntimeException("Unable to create parameter: " . $this->db->errorInfo()[2]);

        // Invalidate cache
        $this->parametersCache = [];
    }

    /**
     * Get all locations from the database
     *
     * @return array with 'name' as key
     */
    public function getAllLocations(): array
    {
        $locations = $this->locationsCache;

        if (empty($locations)) {
            $locations = $this->getCompleteTable($this->locationsTable, 'name', ['details']);
        }

        return $this->locationsCache = $locations;
    }

    /**
     * Get all parameters from the database
     *
     * @return array with 'name' as key
     */
    public function getAllParameters(): array
    {
        $parameters = $this->parametersCache;

        if (empty($parameters)) {
            $parameters = $this->getCompleteTable($this->parametersTable, 'name', ['details']);
        }

        return $this->parametersCache = $parameters;
    }

    /**
     * Get a table completely into an array of objects
     *
     * $indexfield will be used as key for the array.
     * 
     * Columns in $jsonDecodingFields will be JSON decoded.
     * 
     * @param string $tablename
     * @param string $indexfield
     * @param array $jsonDecodingFields
     * @return array
     */
    protected function getCompleteTable(string $tablename, string $indexfield = 'id', array $jsonDecodingFields = []): array
    {
        $table = [];

        $sql = "SELECT * FROM `{$tablename}` ORDER BY `{$indexfield}`";

        foreach ($this->db->query($sql, \PDO::FETCH_OBJ) as $row) {
            foreach ($jsonDecodingFields as $field) {
                $row->$field = json_decode($row->$field);
            }

            $table[$row->$indexfield] = $row;
        }

        return $table;
    }

    /**
     * Insert or updates value into time series
     *
     * If $failSilently is set to false, this will throw \RuntimeExceptions on encountering
     * unknown parameters or locations.
     * 
     * Returns true on successfull insert.
     * 
     * @param string $location
     * @param string $parameter
     * @param int $timestamp
     * @param mixed $value
     * @param bool $failSilently
     * @return bool
     * @throws \RuntimeException
     */
    public function insertValue(string $location, string $parameter, int $timestamp, mixed $value, bool $failSilently = false): bool
    {
        // Validate location and parameter
        $locations = $this->getAllLocations();
        $parameters = $this->getAllParameters();

        $thisLocation = $locations[$location] ?? null;
        $thisParameter = $parameters[$parameter] ?? null;

        if (!$thisLocation) {
            if (!$failSilently) throw new \RuntimeException("Unable to insert value due to unknown location: '{$location}'.");

            return false;
        }

        if (!$thisParameter) {
            if (!$failSilently) throw new \RuntimeException("Unable to insert value due to unknown parameter: '{$parameter}'.");

            return false;
        }

        // Fetch query from cache or install it
        if (!$this->valueInsertQueryCache) {
            $sql = "INSERT INTO `{$this->valuesTable}` (`timestamp`, `loc`, `par`, `val`)
                    VALUES (:timestamp, :loc, :par, :val) ON DUPLICATE KEY UPDATE `timestamp`=:timestamp, `loc`=:loc, `par`=:par, `val`=:val";
            $this->valueInsertQueryCache = $this->db->prepare($sql);
        }

        // Execute query
        $result = $this->valueInsertQueryCache->execute([':timestamp' => $timestamp, ':loc' => $thisLocation->id, ':par' => $thisParameter->id, ':val' => $value]);

        if (!$result) throw new \RuntimeException("Unable to insert value: " . $this->db->errorInfo()[2]);

        // Insert latest value
        if(!$this->valueInsertLatestQueryCache) {
            $sql = "INSERT INTO `{$this->latestValuesTable}` (`timestamp`, `loc`, `par`, `val`)
                    VALUES (:timestamp, :loc, :par, :val) ON DUPLICATE KEY UPDATE
                    `val` = IF(VALUES(timestamp) >= timestamp, VALUES(`val`), `val`),
                    `timestamp` = IF(VALUES(timestamp) >= timestamp, VALUES(timestamp), timestamp)";
    
            $this->valueInsertLatestQueryCache = $this->db->prepare($sql);
        }

        // Execute query
        $result = $this->valueInsertLatestQueryCache->execute([':timestamp' => $timestamp, ':loc' => $thisLocation->id, ':par' => $thisParameter->id, ':val' => $value]);

        if (!$result) throw new \RuntimeException("Unable to insert latest value: " . $this->db->errorInfo()[2]);

        return true;
    }

    /**
     * Fetch values from the time series
     *
     * If you don't specify anything, will return the entire time series.
     * 
     * If you don't specify a start time, will return all values from the database
     * from the beginng until the end time.
     * 
     * If you don't specify an end time, will return all values from the database
     * from the start time until the end of the time series.
     * 
     * You can pass a single location as string, multiple locations as array or an empty
     * value (NULL/''/[]) for selecting all locations.
     * 
     * You can pass a single parameter as string, multiple parameters as array or an empty
     * value (NULL/''/[]) for selecting all parameters.
     * 
     * If $failSilently is set to TRUE, this will ignore unknown requested parameters or
     * locations. If set to FALSE, this would throw \RuntimeExceptions.
     * 
     * The result is an array of objects/dictionaries, with one value per object:
     * 
     * [
     *   {
     *      timestamp => 10000000,
     *      loc => 'aaa',
     *      par => 'bbb',
     *      val => 1.23
     *   },
     *  ...
     * ]
     * 
     * @param int $startTime
     * @param int $endTime
     * @param array|string $locations
     * @param array|string $parameters
     * @param bool $failSilently
     * @return array
     * @throws \RuntimeException
     */
    public function getValues(?int $startTime = null, ?int $endTime = null, mixed $locations = null, mixed $parameters = null, bool $failSilently = true): array
    {
        // Handle start and end
        $timestampCondition = '';
        if (empty($startTime) && empty($endTime)) {
            $timestampCondition = '1=1';
        } else if (empty($startTime) && $endTime) {
            $timestampCondition = 'timestamp <= ' . intval($endTime);
        } else if ($startTime && empty($endTime)) {
            $timestampCondition = 'timestamp >= ' . intval($startTime);
        } else {
            $timestampCondition = 'timestamp BETWEEN ' . intval($startTime) . ' AND ' . intval($endTime);
        }

        // Identify locations and parameters
        $allLocations = $this->getAllLocations();
        $allParameters = $this->getAllParameters();

        $locationsCondition = self::createSetString('loc', $locations, $allLocations, $failSilently);
        $parametersCondition = self::createSetString('par', $parameters, $allParameters, $failSilently);

        // Construct query
        $sql = "SELECT timestamp, `{$this->locationsTable}`.name AS loc, `{$this->parametersTable}`.name AS par, val
        FROM `{$this->valuesTable}`
        JOIN `{$this->locationsTable}` ON `{$this->valuesTable}`.loc = `{$this->locationsTable}`.id
        JOIN `{$this->parametersTable}` ON `{$this->valuesTable}`.par = `{$this->parametersTable}`.id
        WHERE {$timestampCondition} AND {$locationsCondition} AND {$parametersCondition}
        ORDER BY timestamp ASC, loc ASC, par ASC
        ;";

        // Execute query
        $query = $this->db->prepare($sql);
        $query->execute();

        $values = $query->fetchAll(\PDO::FETCH_OBJ);

        return $values;
    }

    /**
     * Fetch latest values for these stations and parameters
     * 
     * You can pass a single location as string, multiple locations as array or an empty
     * value (null/''/[]) for selecting all locations.
     * 
     * You can pass a single parameter as string, multiple parameters as array or an empty
     * value (null/''/[]) for selecting all parameters.
     * 
     * If $failSilently is set to true, this will ignore unknown requested parameters or
     * locations. If set to false, this would throw \RuntimeExceptions.
     * 
     * With $timestampLimit, this will only return newer values than the limit.
     * 
     * @param array|string $locations
     * @param array|string $parameters
     * @param bool $failSilently
     * @param int $timestampLimit
     * @return array
     * @throws \RuntimeException
     */
    public function getLatestValues(mixed $locations = null, mixed $parameters = null, bool $failSilently = true, $timestampLimit = null): array
    {
        $values = [];

        // Identify locations and parameters
        $allLocations = $this->getAllLocations();
        $allParameters = $this->getAllParameters();

        $locationsCondition = self::createSetString('loc', $locations, $allLocations, $failSilently);
        $parametersCondition = self::createSetString('par', $parameters, $allParameters, $failSilently);

        // Set timestamp limit
        $limit = '';

        if ($timestampLimit !== NULL) {
            $limit = "AND timestamp >= " . intval($timestampLimit);
        }

        // Construct query and fetch data
        $sql = "SELECT timestamp, `{$this->locationsTable}`.name AS loc, `{$this->parametersTable}`.name AS par, val
                FROM `{$this->latestValuesTable}`
                JOIN `{$this->locationsTable}` ON `{$this->latestValuesTable}`.loc = `{$this->locationsTable}`.id
                JOIN `{$this->parametersTable}` ON `{$this->latestValuesTable}`.par = `{$this->parametersTable}`.id
                WHERE $parametersCondition AND $locationsCondition
                {$limit}";

        // Execute query
        $query = $this->db->prepare($sql);
        $query->execute();

        $values = $query->fetchAll(\PDO::FETCH_OBJ);

        return $values;
    }

    /**
     * Deletes all values from the values table older than specified timestamp
     * 
     * @param integer $timestamp
     * @return bool Success
     */
    public function deleteValuesOlderThan(int $timestamp): bool
    {
        $sql = "DELETE FROM `{$this->valuesTable}` WHERE `timestamp` < {$timestamp};";

        $query = $this->db->prepare($sql);
        return $query->execute();
    }

    /**
     * Drops all database tables for the current time series completely
     * 
     * @return bool Success 
     */
    public function dropDatabaseTables(): bool
    {
        // Delete view first
        $sql = "DROP VIEW IF EXISTS `{$this->infoView}`;";
        $query = $this->db->prepare($sql);
        $query->execute();

        // Drop tables afterwards
        $sql = "DROP TABLE IF EXISTS `{$this->valuesTable}`, `{$this->latestValuesTable}`, `{$this->locationsTable}`, `{$this->parametersTable}`;";

        $query = $this->db->prepare($sql);
        return $query->execute();
    }

    /**
     * Helper function: Connect to a MySQL database and return a MyTS instance
     *
     * @param string $timeseriesName
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $database
     * @return MyTS
     */
    public static function MyTSMySQLFactory(string $timeseriesName, string $host, string $username, string $password, string $database): MyTS
    {
        $dsn = "mysql:host={$host};dbname={$database};charset=utf8;user={$username};password={$password}";
       
        return self::MyTSFromDSNFactory($timeseriesName, $dsn);
    }

    /**
     * Helper function: Connect to a MySQL database with a DSN and return a MyTS instance
     *
     * @param string $timeseriesName
     * @param string $dsn
     * @return MyTS
     */
    public static function MyTSFromDSNFactory(string $timeseriesName, string $dsn): MyTS
    {
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return new MyTS($timeseriesName, $pdo);
    }

    /**
     * Helper function to create a SQL set string from a requested value
     *
     * For creating set strings from user requested locations/parameters
     * 
     * @param string $fieldName
     * @param string/array $requested
     * @param array $validObjects
     * @param bool $failSilently
     * @return string
     * @throws \RuntimeException
     */
    protected static function createSetString(string $fieldName, mixed $requested, array $validObjects, bool $failSilently): string
    {
        $sql = '';

        if (empty($requested)) {
            // Select everything
            return "1=1";
        }

        // Create set
        $set = self::createValidSet($requested, $validObjects, $failSilently);
        if (empty($set)) $set = [-1]; // Prevent SQL syntax errors when no valid id has been requested

        $sql = "{$fieldName} IN (" . implode(',', $set) . ")";

        return $sql;
    }

    /**
     * Helper function to create a valid ids set from a requested value
     * 
     * For creating sets of valid ids of locations/parameters.
     * 
     * @param string/array $requested (If null, then all objects are returned)
     * @param array $validObjects with the identifier as key
     * @param bool $failSilently
     * @return array
     * @throws \RuntimeException
     */
    protected static function createValidSet(mixed $requested, array $validObjects, bool $failSilently): array
    {
        // Check if all are requested
        if ($requested === null) {
            // Return array of ids
            return array_map(function ($o) {
                return $o->id;
            }, $validObjects);
        }

        // Arrayify $requested
        $requested = (array) $requested;

        // Collect ids
        $set = [];
        foreach ($requested as $request) {
            if (isset($validObjects[$request])) {
                $set[] = $validObjects[$request]->id;
            } else if (!$failSilently) {
                throw new \RuntimeException("Requested {$requested} not found.");
            }
        }

        return $set;
    }
}
