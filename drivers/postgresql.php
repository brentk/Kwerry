<?php
/**
 * This is the Kwerry driver for PostgreSQL.
 *
 * @author   Brent Kelly <brenttkelly@gmail.com>
 * @package  Kwerry
 */
namespace Kwerry;

class Postgresql extends Database {

	private $_connection;
	public $_prepared_statement = array();

	/**
	 * Error handler.
	 *
	 * @param   integer    PHP error number.
	 * @param   string     Error description.
	 * @param   string     Filename error occurred in.
	 * @param   integer    Line in file that error occurred on.
	 * @throws  Exception  Triggered error.
	 * @return  null
	 */
	protected function errorHandler( $errorNumber, $errorString, $errorFile, $errorLine ) {
		throw new \Exception( $errorString );
	}

	/**
	 * Object constructor.
	 *
	 * @return Kwerry\Database
	 */
	public function __construct() {

		if( ! function_exists( "pg_connect" ) ) {
			throw new Exception( "PostgreSQL PHP support not installed." );
		}
		$this->setRandom( "random()" );
		$this->setTrue( "t" );
		$this->setFalse( "f" );
	}

	/**
	 * Creates and stores the native postgresql connection to the database.
	 *
	 * @throws Exception  Unable to connect to the database.
	 * @return null
	 */
	public function connect() {

		set_error_handler( array( $this, "errorHandler" ) );

		$connectionString = "";
		$connectionString .= " host=".$this->getHost();
		$connectionString .= " port=".$this->getPort();
		$connectionString .= " dbname=".$this->getDBName();
		$connectionString .= " user=".$this->getUsername();
		$connectionString .= " password=".str_replace( " ", "\\ ", $this->getPassword() );
		$this->_connection = pg_connect( $connectionString );

		restore_error_handler();
	}

	/**
	 * Prepares and caches sql statements and returns the prepared statement's name.
	 * Will return the already prepared statement's name if subsequently called.
	 *
	 * @param  string  SQL Statement to prepare/return
	 * @return string  Prepared statement's name
	 */
	private function getQuery( $sql ) {
		$index = array_search( $sql, $this->_prepared_statement );
		if( $index === false ) {
			$index = count( $this->_prepared_statement );
			$this->_prepared_statement[ $index ] = $sql;
			$toss = pg_prepare( $this->_connection, $index, $sql );
		}
		return $index;
	}

	/**
	 * Normalizes the database specific datatypes to the set of
	 * datatypes Kwerry uses. Used during database introspection.
	 *
	 * @return integer
	 */
	protected function getDataType( $type ) {

		switch( $type ) {
			case( "int2" ):
			case( "int4" ):
			case( "int8" ):
				return constant( "DATA_TYPE_INTEGER" );
				break;
			case( "text" ):
			case( "varchar" ):
				return constant( "DATA_TYPE_STRING" );
				break;
			case( "date" ):
				return constant( "DATA_TYPE_DATE" );
				break;
			case( "time" ):
				return constant( "DATA_TYPE_TIME" );
				break;
			case( "timestamp" ):
				return constant( "DATA_TYPE_STAMP" );
				break;
			case( "numeric" ):
				return constant( "DATA_TYPE_NUMERIC" );
				break;
			case( "bytea" ):
				return constant( "DATA_TYPE_BLOB" );
				break;
			case( "bool" ):
				return constant( "DATA_TYPE_BOOL" );
				break;
			default:
				throw new Exception( "Unknown data type \"{$type}\"." );
		}

	}

	/**
	 * During introspection this method reads the postgresql metadata
	 * for column informtaion (name, type, pk, etc) and records it.
	 *
	 * @param  Kwerry\Table
	 * @return null
	 */
	private function populateColumns( Table $table ) {

		$sql = "SELECT pg_attribute.attnum, pg_attribute.attname AS field, pg_type.typname AS type,
				pg_attribute.attlen AS length, pg_attribute.atttypmod AS lengthvar,
				pg_attribute.attnotnull AS notnull, pg_index.indisunique AS unique_key,
				pg_index.indisprimary AS primary_key
			FROM pg_class
			INNER JOIN pg_attribute ON pg_class.oid = pg_attribute.attrelid
			INNER JOIN pg_type ON pg_attribute.atttypid = pg_type.oid
			LEFT OUTER JOIN pg_index ON (
				pg_class.oid = pg_index.indrelid AND
				pg_index.indrelid = pg_attribute.attrelid AND
				pg_attribute.attnum = pg_index.indkey[pg_attribute.attnum-1]
				)
			WHERE
			pg_class.relname = $1
			and pg_attribute.attnum > 0
			ORDER BY pg_attribute.attnum";


		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $table->getName() ) );
		$columns = pg_fetch_all( $result );

		if( $columns === false ) {
			throw new Exception( "Unable to find table ".$table->getName()."\n\n" );
		}

		foreach( $columns as $record ) {
			$column = new Column();
			$column->setName( $record[ "field" ] );
			$column->setDataType( $this->getDataType( $record[ "type" ] ) );
			$table->addColumn( $column );

			if( $record[ "primary_key" ] ) {
				$table->setPrimaryKey( $record[ "field" ] );
			}
		}
	}

	/**
	 * During introspection this method reads the PostgreSQL meta data
	 * for all foreign keys in this table that are referencing other
	 * tables and records them.
	 *
	 * @param  Kwerry/Table
	 * @return null
	 */
	public function populateForeignKeys( Table $table ) {

		//Lifted verbatim from propel
		$sql = "SELECT conname, confupdtype, confdeltype,
			CASE nl.nspname WHEN 'public' THEN cl.relname
			ELSE nl.nspname||'.'||cl.relname END as fktab,
			a2.attname as fkcol,
			CASE nr.nspname WHEN 'public' THEN cr.relname
			ELSE nr.nspname||'.'||cr.relname END as reftab,
			a1.attname as refcol
			FROM pg_constraint ct
			JOIN pg_class cl ON cl.oid=conrelid
			JOIN pg_class cr ON cr.oid=confrelid
			JOIN pg_namespace nl ON nl.oid = cl.relnamespace
			JOIN pg_namespace nr ON nr.oid = cr.relnamespace
			LEFT JOIN pg_catalog.pg_attribute a1 ON a1.attrelid = ct.confrelid
			LEFT JOIN pg_catalog.pg_attribute a2 ON a2.attrelid = ct.conrelid
			WHERE contype='f'
			AND cl.relname = $1
			AND a2.attnum = ct.conkey[1]
			AND a1.attnum = ct.confkey[1]
			ORDER BY conname";

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $table->getName() ) );
		$foreignKeys = pg_fetch_all( $result );

		if( $foreignKeys !== false ) {
			foreach( $foreignKeys as $record ) {
				$foreignKey = new Relationship( $table );
				$foreignKey->setLocalColumn( $record[ "fkcol" ] );
				$foreignKey->setForeignTable( $record[ "reftab" ] );
				$foreignKey->setForeignColumn( $record[ "refcol" ] );
				$table->addRelationship( $foreignKey );
			}
		}

	}

	/**
	 * During introspection this method reads the PostgreSQL meta data
	 * for all columns in this table that have foreign keys in other
	 * tables referencing them and records them.
	 *
	 * @param  Kwerry/Table
	 * @return null
	 */
	public function populateReferencedColumns( Table $table ) {

		//Lifted verbatim from propel
		$sql = "SELECT conname, confupdtype, confdeltype,
			CASE nl.nspname WHEN 'public' THEN cl.relname
			ELSE nl.nspname||'.'||cl.relname END as fktab,
			a2.attname as fkcol,
			CASE nr.nspname WHEN 'public' THEN cr.relname
			ELSE nr.nspname||'.'||cr.relname END as reftab,
			a1.attname as refcol
			FROM pg_constraint ct
			JOIN pg_class cl ON cl.oid=conrelid
			JOIN pg_class cr ON cr.oid=confrelid
			JOIN pg_namespace nl ON nl.oid = cl.relnamespace
			JOIN pg_namespace nr ON nr.oid = cr.relnamespace
			LEFT JOIN pg_catalog.pg_attribute a1 ON a1.attrelid = ct.confrelid
			LEFT JOIN pg_catalog.pg_attribute a2 ON a2.attrelid = ct.conrelid
			WHERE contype='f'
			AND cr.relname = $1
			AND a2.attnum = ct.conkey[1]
			AND a1.attnum = ct.confkey[1]
			ORDER BY conname";

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $table->getName() ) );
		$references = pg_fetch_all( $result );
		if( $references !== false ) {
			foreach( $references as $reference ) {
				$referencedColumn = new Relationship( $table );
				$referencedColumn->setLocalColumn( $reference[ "refcol" ] );
				$referencedColumn->setForeignTable( $reference[ "fktab" ] );
				$referencedColumn->setForeignColumn( $reference[ "fkcol" ] );
				$table->addRelationship( $referencedColumn );
			}
		}
	}

	/**
	 * Calls all the internal methods that read the PostgreSQL meta data
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

				//I CAN NOT get pg_prepare/pg_execute to play nice with null literals...
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
						$whereClause .= $comma . "$".count( $params )." ";
						$comma = ",";
					}
				} else {
					$params[] = $where[ "value" ];
					$whereClause .= $where[ "operator" ] . " ";
					$whereClause .= "$".count( $params )." ";
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
			$sql .= " LIMIT $" . count( $params ) . " ";
		}

		if( ! is_null($kwerry->_offset) ) {
			$params[] = $kwerry->_offset;
			$sql .= " OFFSET $" . count( $params ) . " ";
		}

		return array( $sql, $params );
	}

	/**
	 * Kwerry allows ODBC style parameter placeholders (?) across database
	 * backends.  This function converts them to postgresql style placeholders.
	 *
	 * @param	string		SQL statement to process.
	 * @return	string		Processed SQL statement with postgresql style placeholders
	 */
	protected function convertPlaceholders( $sql ) {
		if( strstr( $sql, "?" ) === false ) {
			return $sql;
		}

		$return = "";

		$count = 0;
		foreach( explode( "?", $sql ) as $token ) {
			if( $count == 0 ) {
				$return .= $token;
			} else {
				$return .= "$" . $count . $token;
			}
			$count++;
		}
		return $return;
	}


	/**
	 * This method is where the constructed SQL statement is actually executed against
	 * the database.
	 *
	 * @param   string     The SQL statement to execute.
	 * @param   array      The parameter values for the sql statement.
	 * @throws  Exception  Error occurred when executing query.
	 * @return  mixed      Resulting recordset
	 */
	public function runSQL( $sql, $params ) {

		$sql = $this->convertPlaceholders( $sql );

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $params );

		if( $result === false ) {
			throw new Exception( pg_last_error( $this->_connection ) );
		}

		$recordset = pg_fetch_all( $result );

		//return an empty array instead of a false bool
		if( $recordset === false ) {
			return( array() );
		}

		return $recordset;
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
			$sql .= $comma . $name . " = $" . count( $params );
			$comma = ", ";
		}

		$params[] = $pkValue;
		$sql .= " WHERE " . $pkName . " = $" . count( $params );

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $params );

		/* return the new values so the kwerry object can update them */
		$sql = "SELECT * FROM " . $kwerry->getTable()->getName() . " WHERE " . $pkName . " = $1 ";
		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $pkValue ) );
		$recordset = pg_fetch_all( $result );
		return $recordset;
	}

	/**
	 * Builds an insert statement, executes it against the database,
	 * and returns the new primary key value.
	 *
	 * @param  Array    Column/Value pairs to insert
	 * @return integer  Value of the newly inserted primary key.
	 */
	function insert( $columns, \Kwerry $kwerry ) {

		$comma = "";
		$params = array();
		$paramString = "";

		$sql = "INSERT INTO " . $kwerry->getTable()->getName() . " ( ";

		foreach( $columns as $name => $value ) {
			$params[] = $value;
			$sql .= $comma . $name;
			$paramString .= $comma . " $".count( $params );
			$comma = ", ";
		}

		$sql .= " ) values ( " . $paramString . " )";

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $params );
		$result = pg_execute( $this->_connection, $this->getQuery( "select lastval();" ), array() );
		$recordset = pg_fetch_all( $result );
		/* return the new values so the kwerry object can update them */
		return $recordset[ 0 ][ "lastval" ];
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
		$sql .= " WHERE " . $pkName . " = $" . count( $params );

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $params );

		return true;
	}

	/**
	 * Begin a database transaction.
	 *
	 * @return null
	 */
	function begin() {
		pg_query( $this->_connection, "BEGIN" );
	}

	/**
	 * Commit a database transaction.
	 *
	 * @return null
	 */
	function commit() {
		pg_query( $this->_connection, "COMMIT" );
	}

	/**
	 * Rollback a database transaction.
	 *
	 * @return null
	 */
	function rollback() {
		pg_query( $this->_connection, "ROLLBACK" );
	}
}

