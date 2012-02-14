<?php defined('SYSPATH') or die('No direct script access.');
/**
 * SQLite database connection.
 *
 * @package    SQLite
 * @category   Drivers
 * @author     David Grinberg
 * @copyright  (c) 2012 David Grinberg
 * @license    http://kohanaphp.com/license
 */
class Database_SQLite extends Database {

	// Database in use by each connection
	protected static $_current_databases = array();

	// Use SET NAMES to set the character set
	protected static $_set_names;

	// Identifier for this connection within the PHP driver
	protected $_connection_id;

	// SQLite uses double quotes for identifiers
	protected $_identifier = '"';

	public function connect()
	{
		if ($this->_connection)
			return;

		// Extract the connection parameters, adding required variabels
		extract($this->_config['connection'] + array(
			'database'   => '',
			'persistent' => FALSE,
		));

		try
		{
			if ($persistent)
			{
				// Create a persistent connection
				$this->_connection = sqlite_popen($database);
			}
			else
			{
				// Create a connection and force it to be a new link
				$this->_connection = sqlite_open($database);
			}
		}
		catch (Exception $e)
		{
			// No connection exists
			$this->_connection = NULL;

			throw new Database_Exception(':error',
				array(':error' => $e->getMessage()),
				$e->getCode());
		}

		$this->_connection_id = sha1($dsn);

		if ( ! empty($this->_config['connection']['variables']))
		{
			// Set session variables
			$variables = array();

			foreach ($this->_config['connection']['variables'] as $var => $val)
			{
				$variables[] = 'SESSION '.$var.' = '.$this->quote($val);
			}

			sqlite_query('SET '.implode(', ', $variables), $this->_connection);
		}
	}

	/**
	 * Select the database
	 *
	 * @param   string  Database
	 * @return  void
	 */
	protected function _select_db($database)
	{
		throw new Kohana_Exception('Database method :method is not supported by :class', array(':method' => __FUNCTION__, ':class' => __CLASS__)); 
	}

	/**
	 * Closes the connection with the database
	 *
	 * @return void
	 */
	public function disconnect()
	{
		try
		{
			// Database is assumed disconnected
			$status = TRUE;

			if (is_resource($this->_connection))
			{
				if ($status = sqlite_close($this->_connection))
				{
					// Clear the connection
					$this->_connection = NULL;

					// Clear the instance
					parent::disconnect();
				}
			}
		}
		catch (Exception $e)
		{
			// Database is probably not disconnected
			$status = ! is_resource($this->_connection);
		}

		return $status;
	}

	/**
	 * set_charset is not supported by SQLite
	 *
	 * @return error
	 */
	public function set_charset()
	{
		throw new Kohana_Exception('Database method :method is not supported by :class', array(':method' => __FUNCTION__, ':class' => __CLASS__)); 
	}

	/**
	 * Runs a query on the database
	 *
	 * @param QueryType Type
	 * @param string    SQL
	 * @param bool      AsObject
	 * @param array     params
	 * @return variant
	 */
	public function query($type, $sql, $as_object = FALSE, array $params = NULL)
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		if ( ! empty($this->_config['profiling']))
		{
			// Benchmark this query for the current instance
			$benchmark = Profiler::start("Database ({$this->_instance})", $sql);
		}

		// Execute the query
		if (($result = sqlite_query($sql, $this->_connection)) === FALSE)
		{
			if (isset($benchmark))
			{
				// This benchmark is worthless
				Profiler::delete($benchmark);
			}

			throw new Database_Exception(':error [ :query ]',
				array(':error' => sqlite_error_string(sqlite_last_error($this->_connection)), ':query' => $sql),
				sqlite_last_error($this->_connection));
		}

		if (isset($benchmark))
		{
			Profiler::stop($benchmark);
		}

		// Set the last query
		$this->last_query = $sql;

		if ($type === Database::SELECT)
		{
			// Return an iterator of results
			return new Database_SQLite_Result($result, $sql, $as_object, $params);
		}
		elseif ($type === Database::INSERT)
		{
			// Return a list of insert id and rows created
			return array(
				sqlite_last_insert_rowid($this->_connection),
				sqlite_changes($this->_connection),
			);
		}
		else
		{
			// Return the number of rows affected
			return sqlite_changes($this->_connection);
		}
	}

	public function datatype($type)
	{
		static $types = array
		(
			'blob'                      => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '65535'),
			'bool'                      => array('type' => 'bool'),
			'bigint unsigned'           => array('type' => 'int', 'min' => '0', 'max' => '18446744073709551615'),
			'datetime'                  => array('type' => 'string'),
			'decimal unsigned'          => array('type' => 'float', 'exact' => TRUE, 'min' => '0'),
			'double'                    => array('type' => 'float'),
			'double precision unsigned' => array('type' => 'float', 'min' => '0'),
			'double unsigned'           => array('type' => 'float', 'min' => '0'),
			'enum'                      => array('type' => 'string'),
			'fixed'                     => array('type' => 'float', 'exact' => TRUE),
			'fixed unsigned'            => array('type' => 'float', 'exact' => TRUE, 'min' => '0'),
			'float unsigned'            => array('type' => 'float', 'min' => '0'),
			'int unsigned'              => array('type' => 'int', 'min' => '0', 'max' => '4294967295'),
			'integer unsigned'          => array('type' => 'int', 'min' => '0', 'max' => '4294967295'),
			'longblob'                  => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '4294967295'),
			'longtext'                  => array('type' => 'string', 'character_maximum_length' => '4294967295'),
			'mediumblob'                => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '16777215'),
			'mediumint'                 => array('type' => 'int', 'min' => '-8388608', 'max' => '8388607'),
			'mediumint unsigned'        => array('type' => 'int', 'min' => '0', 'max' => '16777215'),
			'mediumtext'                => array('type' => 'string', 'character_maximum_length' => '16777215'),
			'national varchar'          => array('type' => 'string'),
			'numeric unsigned'          => array('type' => 'float', 'exact' => TRUE, 'min' => '0'),
			'nvarchar'                  => array('type' => 'string'),
			'point'                     => array('type' => 'string', 'binary' => TRUE),
			'real unsigned'             => array('type' => 'float', 'min' => '0'),
			'set'                       => array('type' => 'string'),
			'smallint unsigned'         => array('type' => 'int', 'min' => '0', 'max' => '65535'),
			'text'                      => array('type' => 'string', 'character_maximum_length' => '65535'),
			'tinyblob'                  => array('type' => 'string', 'binary' => TRUE, 'character_maximum_length' => '255'),
			'tinyint'                   => array('type' => 'int', 'min' => '-128', 'max' => '127'),
			'tinyint unsigned'          => array('type' => 'int', 'min' => '0', 'max' => '255'),
			'tinytext'                  => array('type' => 'string', 'character_maximum_length' => '255'),
			'year'                      => array('type' => 'string'),
		);

		$type = str_replace(' zerofill', '', $type);

		if (isset($types[$type]))
			return $types[$type];

		return parent::datatype($type);
	}

	/**
	 * Start a SQL transaction
	 *
	 * @param string Isolation level
	 * @return boolean
	 */
	public function begin($mode = NULL)
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();
		
		return (bool) sqlite_query('BEGIN TRANSACTION', $this->_connection);
	}

	/**
	 * Commit a SQL transaction
	 *
	 * @param string Isolation level
	 * @return boolean
	 */
	public function commit()
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		return (bool) sqlite_query('COMMIT TRANSACTION', $this->_connection);
	}

	/**
	 * Rollback a SQL transaction
	 *
	 * @param string Isolation level
	 * @return boolean
	 */
	public function rollback()
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		return (bool) sqlite_query('ROLLBACK TRANSACTION', $this->_connection);
	}

	public function list_tables($like = NULL)
	{
		if (is_string($like))
		{
			// Search for table names
			$result = $this->query(Database::SELECT, 'SELECT name FROM sqlite_master WHERE type=\'table\' AND name LIKE \''.$this->quote($like).'\' ORDER BY name;', FALSE);
		}
		else
		{
			// Find all table names
			$result = $this->query(Database::SELECT, 'SELECT name FROM sqlite_master WHERE type=\'table\' ORDER BY name;', FALSE);
		}

		$tables = array();
		foreach ($result as $row)
		{
			$tables[] = reset($row);
		}

		return $tables;
	}

	public function list_columns($table, $like = NULL, $add_prefix = TRUE)
	{
		// Quote the table name
		$table = ($add_prefix === TRUE) ? $this->quote_table($table) : $table;

		if (is_string($like))
		{
			throw new Kohana_Exception('Database method :method is not supported by :class',
				array(':method' => __FUNCTION__, ':class' => __CLASS__));
		}
		
		// Find all column names
		$result = $this->query(Database::SELECT, 'PRAGMA table_info('.$table.')', FALSE);
		
		$columns = array();
		foreach ($result as $row)
		{
			// Get the column name from the results
			$columns[] = $row['name'];
		}

		return $columns;
	}

	public function escape($value)
	{
		// Make sure the database is connected
		$this->_connection or $this->connect();

		if (($value = sqlite_escape_string( (string) $value )) === FALSE)
		{
			throw new Database_Exception(':error',
				array(':error' => sqlite_error_string(sqlite_last_error($this->_connection)),
				sqlite_last_error($this->_connection)));
		}

		// SQL standard is to use single-quotes for all values
		return "'$value'";
	}

} // End Database_SQLite
