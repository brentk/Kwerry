<?php
/**
 * This is the Kwerry driver for MySQL. This currently doesn't
 * properly parameterize the SQL that ultimately gets sent to
 * the database due to issues with mysqli's binding behavior.
 *
 * @author   Brent Kelly <brenttkelly@gmail.com>
 * @package  Kwerry
 */
namespace Kwerry;

class Mysql extends Database {

	protected $_connection;
	protected $_sql_strings;
	protected $_statements;

	/**
	 * Object constructor.
	 *
	 * @returns	Kwerry\Database
	 */
	public function __construct() {
		if( ! class_exists( "\\mysqli" ) ) {
			throw new Exception( "MySQL PHP support not installed." );
		}

		$this->_connection  = null;
		$this->_sql_strings = array();
		$this->_statements  = array();

		$this->setRandom( "rand()" );
		$this->setTrue( "1" );
		$this->setFalse( "0" );
	}

	/**
	 * Creates and stores the native mysqli connection to the database.
	 *
	 * @throws Exception  Unable to connect to the database.
	 * @return null
	 */
	public function connect() {
		$this->_connection = new \mysqli($this->getHost(),
						$this->getUsername(),
						$this->getPassword(),
						$this->getDBName(),
						$this->getPort() );

		if( $this->_connection->connect_error ) {
			throw new Exception( "Unable to connect to database: ".
				"\"".$this->_connection->connect_error."\"" );
		}
	}

	/**
	 * Caches prepared statements so they don't need to be processed
	 * on subsequent runs.
	 *
	 * @param   string       SQL Statement to prepare/retrieve
	 * @return  mysqli_stmt  Mysql prepared statement
	 */
	protected function getStatement( $sql ) {
		$index = array_search( $sql, $this->_sql_strings );
		if( $index === false ) {
			$index                      = count( $this->_sql_strings );
			$this->_sql_strings[$index] = $sql;
			$this->_statements[$index]  = $this->_connection->prepare( $sql );
		}
		return $this->_statements[$index];
	}

	/**
	 * Returns the newly inserted primary key after an insert operation.
	 *
	 * @return integer
	 */
	protected function getInsertID() {
		return $this->_connection->insert_id;
	}

	/**
	 * Normalizes the database specific datatypes to the set of
	 * datatypes Kwerry uses. Used during database introspection.
	 *
	 * @return integer
	 */
	protected function getDataType( $type ) {
		$value = null;

		switch( $type ) {
			case( "int" ):
				$value = constant( "DATA_TYPE_INTEGER" );
				break;
			case( "text" ):
			case( "varchar" ):
				$value = constant( "DATA_TYPE_STRING" );
				break;
			case( "date" ):
				$value = constant( "DATA_TYPE_DATE" );
				break;
			case( "time" ):
				$value = constant( "DATA_TYPE_TIME" );
				break;
			case( "timestamp" ):
				$value = constant( "DATA_TYPE_STAMP" );
				break;
			case( "numeric" ):
				$value = constant( "DATA_TYPE_NUMERIC" );
				break;
			case( "bytea" ):
				$value = constant( "DATA_TYPE_BLOB" );
				break;
			case( "bool" ):
				$value = constant( "DATA_TYPE_BOOL" );
				break;
			default:
				throw new Exception( "Unknown data type \"{$type}\"." );
		}

		return $value;
	}

	/**
	 * During introspection this method reads the table's columns
	 * from the information_schema and records their metadata.
	 *
	 * @param  Kwerry/Table
	 * @return null
	 */
	protected function populateColumns( Table $table ) {

		$sql = "SELECT column_name, data_type, column_key
			FROM information_schema.columns
			WHERE table_name = ?
			ORDER BY ordinal_position";

		$records = $this->runSQL( $sql, array( $table->getName() ) );

		foreach( $records as $record ) {
			$column = new Column();
			$column->setName( $record["column_name"] );
			$column->setDataType( $this->getDataType( $record["data_type"] ) );
			$table->addColumn( $column );

			if( $record["column_key"] == "PRI" ) {
				$table->setPrimaryKey( $record["column_name"] );
			}
		}
	}

	/**
	 * During introspection this method reads the information_schema
	 * for all foreign keys in this table that are referencing other
	 * tables and records them.
	 *
	 * @param  Kwerry/Table
	 * @return null
	 */
	protected function populateForeignKeys( Table $table ) {

		$sql = "SELECT
			kcu.table_name as local_table,
			kcu.column_name as local_column,
			kcu.referenced_table_name as foreign_table,
			kcu.referenced_column_name as foreign_column
			FROM information_schema.referential_constraints as rc
			INNER JOIN information_schema.key_column_usage as kcu ON rc.constraint_name = kcu.constraint_name
			WHERE kcu.table_name = ?";

		$records = $this->runSQL( $sql, array( $table->getName() ) );

		foreach( $records as $record ) {
			$foreignKey = new Relationship( $table );
			$foreignKey->setLocalColumn( $record["local_column"] );
			$foreignKey->setForeignTable( $record["foreign_table"] );
			$foreignKey->setForeignColumn( $record["foreign_column"] );
			$table->addRelationship( $foreignKey );
		}
	}

	/**
	 * During introspection this method reads the information_schema
	 * for all columns in this table that have foreign keys in other
	 * tables referencing them and records them.
	 *
	 * @param  Kwerry/Table
	 * @return null
	 */
	protected function populateReferencedColumns( Table $table ) {

		$sql = "SELECT
			kcu.table_name as local_table,
			kcu.column_name as local_column,
			kcu.referenced_table_name as foreign_table,
			kcu.referenced_column_name as foreign_column
			FROM information_schema.referential_constraints as rc
			INNER JOIN information_schema.key_column_usage as kcu ON rc.constraint_name = kcu.constraint_name
			WHERE kcu.referenced_table_name = ?";


		$records = $this->runSQL( $sql, array( $table->getName() ) );

		foreach( $records as $record ) {
			$referencedKey = new Relationship( $table );
			$referencedKey->setLocalColumn( $record["foreign_column"] );
			$referencedKey->setForeignTable( $record["local_table"] );
			$referencedKey->setForeignColumn( $record["local_column"] );
			$table->addRelationship( $referencedKey );
		}
	}

	/**
	 * Calls all the internal methods that read the information_schema
	 * and informs the driver about the specified table.
	 *
	 * @param  Kwerry/Table
	 * @return null
	 */
	public function introspection( Table $table) {
		$this->populateColumns( $table );
		$this->populateForeignKeys( $table );
		$this->populateReferencedColumns( $table );
	}

	/**
	 * Reads all of the Kwerry where commands for the current statement
	 * and translates them into an actual SQL where clauses for use in
	 * the final SQL query.
	 *
	 * @param   Kwerry     Kwerry model object being operated on
	 * @throws  Exception  Non applicable operator used in NULL comparison.
	 * @return  array      [0] string: Where clause [1] array: Parameter values for where clause
	 */
	protected function buildWhere( \Kwerry $kwerry ) {

		$params      = array();
		$whereClause = "";
		$and         = "WHERE";

		if( count( $kwerry->_where ) ) {

			foreach( $kwerry->_where as $where ) {

				$whereClause .= " " . $and . " ";
				$whereClause .= $where[ "field" ] . " ";

				if( NULL === $where["value"] ) {
					$is_equal = array( "IS", "=" );
					$not_equal = array( "IS NOT", "<>", "!=" );

					if( in_array( $where["operator"], $is_equal ) ) {
						$whereClause .= " IS NULL ";
					} else if( in_array( $where["operator"], $not_equal ) ) {
						$whereClause .= " IS NOT NULL ";
					} else {
						throw new Exception( "Non-applicable operator \"".$where["operator"]."\" used with NULL value." );
					}

				} else if( is_array( $where[ "value" ] ) ) {
					$whereClause .= $where[ "operator" ] . " ";
					$comma = "";
					foreach( $where[ "value" ] as $value ) {
						$params[] = $value;
						$whereClause .= $comma . "? ";
						$comma = ",";
					}
				} else {
					$params[] = $where[ "value" ];
					$whereClause .= $where[ "operator" ] . " ";
					$whereClause .= "? ";
				}

				$and = "AND";
			}
		}

		return array( $whereClause, $params );
	}

	/**
	 * Reads the specified Kwerry model object and translates it
	 * into the final SQL statement to be executed.
	 *
	 * @param  Kwerry  Kwerry model object being operated on
	 * @return array   [0] string: Final SQL query [1] array: Parameter values for SQL string
	 */
	public function buildSelectSQL( \Kwerry $kwerry ) {

		$sql = " SELECT * FROM ".$kwerry->getTable()->getName() . " ";

		list( $where, $params ) = $this->buildWhere( $kwerry );

		$sql .= $where;

		if( count( $kwerry->_order ) ) {
			$orderBy = "";
			$comma = "ORDER BY";

			foreach( $kwerry->_order as $sort ) {
				$orderBy .= " " . $comma . " " . $sort[ "field" ] . " " . $sort[ "type" ];
				$comma = ",";
			}
			$sql .= $orderBy;
		}

		if( ! is_null($kwerry->_limit) ) {
			$params[] = $kwerry->_limit;
			$sql .= " LIMIT ? ";
		}

		if( ! is_null($kwerry->_offset) ) {
			$params[] = $kwerry->_offset;
			$sql .= " OFFSET ? ";
		}

		return array( $sql, $params );
	}

	/**
	 * Binds the paramters to the prepared statement.
	 *
	 * @param   mysqli_stmt  The MySQL statement to bind the paramters to.
	 * @param   array        The parameter values for the sql statement.
	 * @return  null
	 */
	protected function bindParamters( \mysqli_stmt $statement, array $params ) {

		//Only if there are parameters present
		if( count( $params ) > 0 ) {

			$bind_param_arguments = array();

			$types = "";
			foreach( $params as $value ) {
				if( is_double( $value )  ) {
					$types .= "d";
				}
				else if( is_integer( $value ) ) {
					$types .= "i";
				}
				else {
					$types .= "s";
				}
			}

			//Only here because the arugments *can't* be passed by value
			$referencingArray = array();
			foreach( $params as $key => $value ) {
				$$value = $value;
				$referencingArray[$key] = &$$value;
			}

			$bind_param_arguments = array_merge( array($types), $referencingArray );

			$method_name = "bind_param";
			$bind_param = array( $statement, $method_name );
			call_user_func_array( $bind_param, $bind_param_arguments );
		}
	}

	/**
	 * This method is where the constructed SQL statement is actually executed against
	 * the database and the correct return value is assessed and returned.
	 *
	 * @param  mysqli_stmt  The SQL statement to execute.
	 * @return mixed        If select: Resulting recordset; If Insert/Update/Delete: Success boolean.
	 */
	protected function executePreparedStatement( \mysqli_stmt $statement ) {

		$success = $statement->execute();

		//-1 is returned on selects, valid numbers on insert/update/delete:
		if( $statement->affected_rows > -1 ) {
			return $success;
		}

		//Create array containing resulting field names:
		$meta = $statement->result_metadata();
		$results = array();
		while( $field = $meta->fetch_field() ) {
			$results[] = $field->name;
		}

		//Create array of pointers to to dynamic variables to hold results
		//because ->bind_result() requires references:
		$bind = array();
		foreach( $results as $field_name ) {
			$$field_name = $field_name;
			$bind[$field_name] = &$$field_name;
		}

		$method_name = "bind_result";
		$method = array( $statement, $method_name );

		call_user_func_array( $method, $bind );

		//build the recordset:
		$recordset = array();
		while( $statement->fetch() ) {
			$row = array();
			foreach( $results as $field_name ) {
				$row[$field_name] = $bind[$field_name];
			}
			$recordset[] = $row;
		}

		return $recordset;

	}

	/**
	 * Method that wraps the overhead of binding the paramters and return values,
	 * as well as executing the sql statement.
	 *
	 * @param  string  The SQL statement to execute.
	 * @param  array   The parameter values for the sql statement.
	 * @return mixed   If select: Resulting recordset; If Insert/Update/Delete: Success boolean.
	 */
	public function runSQL( $sql, array $params ) {
		$statement = $this->getStatement( $sql );
		$this->bindParamters( $statement, $params );
		return $this->executePreparedStatement( $statement );
	}

	/**
	 * Method used by query to translate the model object into
	 * actual SQL, execute it, and return the result.
	 *
	 * @param  Kwerry  The model object.
	 * @return array   Resulting recordset.
	 */
	public function execute( \Kwerry $kwerry ) {
		list( $sql, $params ) = $this->buildSelectSQL( $kwerry );
		return $this->runSQL( $sql, $params );
	}

	/**
	 * Builds an update statement, executes it against the database,
	 * and returns the resulting row as an array.
	 *
	 * @param  Array  Column/Value pairs to update
	 * @return Array  Recordset of the affected row.
	 */
	function update( array $columns, \Kwerry $kwerry ) {

		$pkName = $kwerry->getTable()->getPrimaryKey();
		$pkValue = (string)$kwerry->$pkName;

		$comma = " SET ";
		$params = array();

		$sql = "UPDATE ".$kwerry->getTable()->getName();

		foreach( $columns as $name => $value ) {
			$params[] = $value;
			$sql .= $comma . $name . " = ?";
			$comma = ", ";
		}

		$params[] = $pkValue;
		$sql .= " WHERE " . $pkName . " = ?";

		$result = $this->runSQL( $sql, $params );

		/* return the new values so the kwerry object can update them */
		$sql = "SELECT * FROM " . $kwerry->getTable()->getName() . " WHERE " . $pkName . " = ? ";
		$recordset = $this->runSQL( $sql, array( $pkValue ) );
		return $recordset;
	}

	/**
	 * Builds an insert statement, executes it against the database,
	 * and returns the new primary key value.
	 *
	 * @param  Array    Column/Value pairs to insert
	 * @return integer  Value of the newly inserted primary key.
	 */
	function insert( array $columns, \Kwerry $kwerry ) {

		$comma = "";
		$params = array();
		$paramString = "";

		$sql = "INSERT INTO " . $kwerry->getTable()->getName() . " ( ";

		foreach( $columns as $name => $value ) {
			$params[] = $value;
			$sql .= $comma . $name;
			$paramString .= $comma . " ?";
			$comma = ", ";
		}

		$sql .= " ) values ( " . $paramString . " )";

		$result = $this->runSQL( $sql, $params );

		/* return the new PK */
		return $this->getInsertID();
	}

	/**
	 * Builds a delete statement for the currently active record
	 * in the specified Kwerry object, executes it against the
	 * database, and returns a boolean success indication.
	 *
	 * @param  Array    Column/Value pairs to insert
	 * @return boolean  Success of delete statement.
	 */
	function delete( \Kwerry $kwerry ) {

		$pkName = $kwerry->getTable()->getPrimaryKey();
		$pkValue = (string)$kwerry->$pkName;

		$comma = " SET ";
		$params = array();

		$sql = "DELETE FROM ".$kwerry->getTable()->getName();

		$params[] = $pkValue;
		$sql .= " WHERE " . $pkName . " = ?";

		$result = $this->runSQL( $sql, $params );

		return true;
	}

	/**
	 * Begin a database transaction.
	 *
	 * @return null
	 */
	function begin() {
		$this->runSQL( "BEGIN;", array() );
	}

	/**
	 * Commit a database transaction.
	 *
	 * @return null
	 */
	function commit() {
		$this->runSQL( "COMMIT;", array() );
	}

	/**
	 * Rollback a database transaction.
	 *
	 * @return null
	 */
	function rollback() {
		$this->runSQL( "ROLLBACK;", array() );
	}
}

