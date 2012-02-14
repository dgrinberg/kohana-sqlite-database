<?php defined('SYSPATH') or die('No direct script access.');
/**
 * SQlite database result.   See [Results](/database/results) for usage and examples.
 *
 * @package    SQLite
 * @category   Query/Result
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Database_SQLite_Result extends Database_Result {

	protected $_internal_row = 0;

	public function __construct($result, $sql, $as_object = FALSE, array $params = NULL)
	{
		parent::__construct($result, $sql, $as_object, $params);

		// Find the number of rows in the result
		$this->_total_rows = sqlite_num_rows($result);
	}

	public function __destruct()
	{
		//Can't destruct a SQLite result?
	}

	public function seek($offset)
	{
		if ($this->offsetExists($offset) AND sqlite_seek($this->_result, $offset))
		{
			// Set the current row to the offset
			$this->_current_row = $this->_internal_row = $offset;

			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	public function current()
	{
		if ($this->_current_row !== $this->_internal_row AND ! $this->seek($this->_current_row))
			return NULL;

		// Increment internal row for optimization assuming rows are fetched in order
		$this->_internal_row++;

		if ($this->_as_object === TRUE)
		{
			// Return an stdClass
			return sqlite_fetch_object($this->_result);
		}
		elseif (is_string($this->_as_object))
		{
			// Return an object of given class name
			return sqlite_fetch_object($this->_result, $this->_as_object, $this->_object_params);
		}
		else
		{
			// Return an array of the row
			return sqlite_fetch_array($this->_result);
		}
	}

} // End Database_SQLite_Result
