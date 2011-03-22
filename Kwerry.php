<?
/**
 * Kwerry ORM. A small, introspection based PHP ORM.
 * 
 * @author	Brent Kelly <brenttkelly@gmail.com>
 * @version	.5
 */

error_reporting( E_ALL );
class FK {
	private $_name;
	private $_fktable;
	private $_fkname;
	public function getName() { return $this->_name; }
	public function setName( $name ) { $this->_name = $name; }
	public function getFKTable() { return $this->_fktable; }
	public function setFKTable( $fktable ) { $this->_fktable = $fktable; }
	public function getFKName() { return $this->_fkname; }
	public function setFKName( $fkname ) { $this->_fkname = $fkname; }
}

class Ref {
	private $_name;
	private $_reftable;
	private $_refname;
	public function getName() { return $this->_name; }
	public function setName( $name ) { $this->_name = $name; }
	public function getRefTable() { return $this->_reftable; }
	public function setRefTable( $reftable ) { $this->_reftable = $reftable; }
	public function getRefName() { return $this->_refname; }
	public function setRefName( $refname ) { $this->_refname = $refname; }
}

class Table {
	private $_name;
	private $_pk;
	private $_column = array();
	private $_fk = array();
	private $_ref = array();

	public function getName() { return $this->_name; }
	public function setName( $name ) { $this->_name = $name; }
	public function getPK() { return $this->_pk; }
	public function setPK( $pk ) { $this->_pk = $pk; }
	public function addColumn( $column ) { $this->_column[] = $column; }
	public function getColumns() { return( $this->_column ); }
	public function addFK( $fk ) { $this->_fk[] = $fk; }
	public function getFKs() { return( $this->_fk ); }
	public function addRef( $ref ) { $this->_ref[] = $ref; }
	public function getRefs() { return( $this->_ref ); }
}

class database {
	private $_host;
	private $_port;
	private $_dbname;
	private $_username;
	private $_password;
	public function setHost( $value ) { $this->_host = $value; }
	public function getHost() { return( $this->_host ); }
	public function setPort( $value ) { $this->_port = $value; }
	public function getPort() { return( $this->_port ); }
	public function setDBName( $value ) { $this->_dbname = $value; }
	public function getDBName() { return( $this->_dbname ); }
	public function setUsername( $value ) { $this->_username = $value; }
	public function getUsername() { return( $this->_username ); }
	public function setPassword( $value ) { $this->_password = $value; }
	public function getPassword() { return( $this->_password ); }

	public function connect() {
		throw new Exception( "::connect not implemented!" );
	}
	public function introspection() {
		throw new Exception( "::introspection not implemented!" );
	}
	public function execute() {
		throw new Exception( "::execute not implemented!" );
	}
}

class postgresql extends database {

	private $_connection;
	public static $_prepared_statement = array();

	public function connect() {
		$connectionString = "";
		$connectionString .= " host=".$this->getHost();
		$connectionString .= " port=".$this->getPort();
		$connectionString .= " dbname=".$this->getDBName();
		$connectionString .= " user=".$this->getUsername();
		$connectionString .= " password=".$this->getPassword();
		$this->_connection = pg_connect( $connectionString );
	}

	/** Hashes and prepares sql queries. Searches for hash and returns already
	 * prepared statement if applicable.
	 * 
	 * @param	string		SQL Statement to prepare/return
	 * @return	string		Prepared statement's name
	 */
	private function getQuery( $sql ) {
		$index = array_search( $sql, postgresql::$_prepared_statement );
		if( $index === false ) {
			$index = count( postgresql::$_prepared_statement );
			postgresql::$_prepared_statement[ $index ] = $sql;
			$toss = pg_prepare( $this->_connection, $index, $sql );
		}
		return( $index );
	}

	private function populateColumns( &$obTable ) {

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


		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $obTable->getName() ) );
		$aryColumns = pg_fetch_all( $result );

		if( $aryColumns === false ) {
			throw new Exception( "Unable to list columns in table $tableName\n\n" );
		}

		foreach( $aryColumns as $column ) {
			$obTable->addColumn( $column[ "field" ] );

			if( $column[ "primary_key" ] ) {
				$obTable->setPK( $column[ "field" ] );
			}
		}
	}
	
	public function populateFK( &$obTable) {
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

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $obTable->getName() ) );
		$aryFK = pg_fetch_all( $result );

		if( $aryFK !== false ) {
			foreach( $aryFK as $fk ) {
				$obFK = new FK();
				$obFK->setName( $fk[ "fkcol" ] );
				$obFK->setFKTable( $fk[ "reftab" ] );
				$obFK->setFKName( $fk[ "refcol" ] );
				$obTable->addFK( $obFK );
			}
		}

	}

	public function populateRef( &$obTable) {
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

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $obTable->getName() ) );
		$aryRef = pg_fetch_all( $result );
		if( $aryRef !== false ) {
			foreach( $aryRef as $ref ) {
				$obRef = new Ref();
				$obRef->setName( $ref[ "refcol" ] );
				$obRef->setRefTable( $ref[ "fktab" ] );
				$obRef->setRefName( $ref[ "fkcol" ] );
				$obTable->addRef( $obRef );
			}
		}
	}

	public function introspection( &$obTable) {
		$this->populateColumns( $obTable );
		$this->populateFK( $obTable );
		$this->populateRef( $obTable );
	}

	public function execute( &$obKwerry ) {

		$param = array();

		$sql = " SELECT * FROM ".$obKwerry->getTable()->getName() . " ";

		if( count( $obKwerry->_where ) ) {

			$where = "";
			$and = "WHERE";

			foreach( $obKwerry->_where as $aryWhere ) {

				$where .= " " . $and . " ";
				$where .= $aryWhere[ "field" ] . " ";
				$where .= $aryWhere[ "operator" ] . " ";

				if( is_array( $aryWhere[ "value" ] ) ) {
					$comma = "";
					foreach( $aryWhere[ "value" ] as $value ) {
						$param[] = $value;
						$where .= $comma . "$".count( $param )." ";
						$comma = ",";
					}
				} else {
					$param[] = $aryWhere[ "value" ];
					$where .= "$".count( $param )." ";
				}
				
				$and = "AND";
			}
	
			$sql .= $where;
		}

		if( count( $obKwerry->_order ) ) {
			$orderBy = "";
			$comma = "ORDER BY";

			foreach( $obKwerry->_order as $sort ) {
				$orderBy .= " " . $comma . " " . $sort[ "field" ] . " " . $sort[ "type" ];
				$comma = ",";
			}
			$sql .= $orderBy;
		}

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $param );
		$recordset = pg_fetch_all( $result );
		return( $recordset );
	}
}

class Kwerry implements arrayaccess, iterator, countable {

	public $_tableName;
	public $_table;
	public $_relationship = array();
	public $_isDirty;
	public $_where;
	public $_order;

	private $_stringValue;
	private $_currentRow = 0;
	private $_recordset = array();
	private $_connectionName;

	public static $_connection = array();


	/** Attmps to find a model on the path. If on is not found, attempts
	 * to create a vanilla model by examining the database schema.
	 *
	 * @access	static
	 * @param	string	Name of requested table/model
	 * @return	object	Model object
	 */
	static function model( $tableName, $connectionName = "default" ) {

		//If there's a model in the path, load that
		foreach( explode( PATH_SEPARATOR, get_include_path() ) as $path ) {
			if( file_exists( $path . "/" . $tableName . ".php" ) ) {
				require_once( $path . "/" . $tableName . ".php" );
				if( class_exists( $tableName ) ) {
					$kwerry = new $tableName( $connectionName );
					return( $kwerry );
				}
			}
		}

		//If not, create one on the fly
		$kwerry = new Kwerry( $tableName, $connectionName );
		return( $kwerry );
	}

	/** Uses the connection's database class's introspection to
	 * create a model of the table's layout. If a defined class 
	 * exists, it can override this to build the table by hand to
	 * increase speed and flexibility.
	 *
	 * @access	protected
	 * @return	NULL
	 */
	protected function buildDataModel( $tableName ) {
		$obTable = new Table();
		$obTable->setName( $tableName );
		$this->setTable( $obTable );
		Kwerry::$_connection[ $this->_connectionName ]->introspection( $obTable );
	}

	function __construct( $tableName, $connectionName ) {
		global $kwerry_opts;

		//Ensure that the options variable is kosher
		if( ! $kwerry_opts ) { 
			throw new Excetption( "\$kwerry_opts Array not found!" );
		}
		if( ! is_array( $kwerry_opts ) ) { 
			throw new Excetption( "\$kwerry_opts not an Array!" );
		}
		$dbclass = $kwerry_opts[ $connectionName ][ "dbtype" ];
		if( ! class_exists( $dbclass ) ) {
			throw new Excetption( "Uknown dbtype: \"".$dbclass."\"." );
		}
		$this->_connectionName = $connectionName;

		//Setup this db connection if it hasn't already been 
		if( ! isset( Kwerry::$_connection[ $this->_connectionName ] ) ) {
			Kwerry::$_connection[ $this->_connectionName ] = new $dbclass();
			Kwerry::$_connection[ $this->_connectionName ]->setHost(	$kwerry_opts[ $connectionName ][ "host" ] );
			Kwerry::$_connection[ $this->_connectionName ]->setPort(	$kwerry_opts[ $connectionName ][ "port" ] );
			Kwerry::$_connection[ $this->_connectionName ]->setDBName(	$kwerry_opts[ $connectionName ][ "dbname" ] );
			Kwerry::$_connection[ $this->_connectionName ]->setUsername(	$kwerry_opts[ $connectionName ][ "username" ] );
			Kwerry::$_connection[ $this->_connectionName ]->setPassword(	$kwerry_opts[ $connectionName ][ "password" ] );
			Kwerry::$_connection[ $this->_connectionName ]->connect();		
		}

		$this->buildDataModel( $tableName );
	}

	/** isDirty is used to let the object know whether or not 
	 * the parameters of the query have changed. If so, we need to
	 * execute or re-execute the query with the lastest query.
	 * 
	 * @access	private
	 * @param	bool	optional wheher object is dirty or not
	 * @return	bool	state if object
	 */
	private function isDirty( $value = NULL ) {
		if( ! is_null( $value ) ) {
			$this->_isDirty = $value;
		}
		return( $this->_isDirty );
	}

	/** Build and cache relateed models. Return cached versions if
	 * previously built.
	 *
	 * @access	private
	 * @param	string	name of table 
	 * @return	object	requested model object
	 */
	private function lazyLoad( $tableName, $their_column, $my_column ) {

		$argument = "get".$my_column;
		$value = $this->$argument();

		$hash = serialize( array( $tableName, $their_column, $value ) );

		if( ! array_key_exists( $hash, $this->_relationship ) ) {
			$method = "where".$their_column;
			$this->_relationship[ $hash ] = Kwerry::model( $tableName );
			$this->_relationship[ $hash ]->$method( $value );
		}

		return( $this->_relationship[ $hash ] );
	}

	/** Create an order by clause to add to the query and sets the 
	 * object as dirty. 
	 *
	 * @access	public
	 * @param	string	Name of database field to sort by
	 * @param	string	(optional) Type of sort (defaults to ascending)
	 * @return	null
	 */
	public function addSort( $name, $type = "ASC" ) {
		
		$this->isDirty( true );

		$sort		= array();
		$sort[ "field" ]= $name;
		$sort[ "type" ]	= $type;
		$this->_order[]	= $sort;

	}

	/** Create a where clause to add to the query and sets the 
	 * object as dirty. It also normalizes the arguments so that
	 * the query processor doesn't need any more overhead.
	 *
	 * @access	public
	 * @param	string	Name of database field to filter by
	 * @param	variant	value(s) to filter with (could be array of values)
	 * @param	string	(optional) Operator to filter with (defaults to equals)
	 * @return	null
	 */
	public function addWhere( $field, $value, $operator = "=" ) {

		$this->isDirty( true );

                $available_operators = array( "=", "<=", ">=", "<", ">", "!=", "<>", "IN", "NOT IN", "LIKE", "IS", "IS NOT" );
		$operator = strtoupper( $operator );
		if( ! in_array( $operator, $available_operators ) ) {
			throw new Exception( "Unknown operator: \"$operator\"" );
		}

		//Handle aggregate operators
		if( $operator == "IN" || $operator == "NOT IN" ) {
			//aggregate operators expect an array
			if( ! is_array( $value ) ) {
				$value = array( $value );
			}
		}

		// If the value is null, use the correct operator
		if( is_null( $value ) ) {
			switch( $operator ) {
				case( "=" ):
					$operator = "IS";
					break;
				case( "!=" ):
				case( "<>" ):
					$operator = "IS NOT";
					break;
			}
		}
	
		$where = array();
		$where[ "field" ]	= $field;
		$where[ "operator" ]	= $operator;
		$where[ "value" ]	= $value;
		$this->_where[]		= $where;

		return;
	}

	/** Once the call has built a query and requested a column,
	 * this ensures that the requested value will be output when
	 * the object is echoed, etc.
	 *
	 * @access	magic
	 * @return	string		Value specified in earlier ->getFoo()
	 */
	function __toString() {
		return( $this->_stringValue );
	}

	/** Actual execution of built queries.  Handles compiling all the 
	 * where and sort properties and compiles it into an actual SQL query.
	 * In the future this will need to be delegated to db specifiec functions
	 * to handle the different nuances of each db's SQL implementation.
	 * 
	 * @access	private
	 * @return	NULL
	 */
	private function executeQuery() {

		$recordset = Kwerry::$_connection[ $this->_connectionName ]->execute( $this );		
		
		if( $recordset === false ) {
			$this->_recordset = array();
		} else {
			$this->_recordset = $recordset;
		}

		$this->_currentRow = 0;
		$this->isDirty( false );
	}

	/** Returns a column's value at the current cursor in the 
	 * recordset.  Will execute (or re-execute) the object's 
	 * query if needed.
	 *
	 * @access	public
	 * @param	string		Column name.
	 * @return	string		Column value at current cursor position.
	 */
	function getValue( $column ) {
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( $this->_recordset[ $this->_currentRow ][ $column ] );
	}

	/** Returns an array of this table's column names.
	 *
	 * @access	public
	 * @return	array		This model's table's column names
	 */
	public function getColumns() {
		return( $this->getTable()->getColumns() );
	}

	public function setTable( $table ) { $this->_table = $table; }
	public function getTable() { return( $this->_table ); }
	public function setConn( $conn ) { $this->_conn = $conn; }
	public function getConn() { return( $this->_conn ); }

	/** Catch all fucntion for ->whereFoo(), ->sortFoo(), & ->getFoo().
	 * When ->get-ing, method will attempt to figure out whether caller is
	 * asking for a column value, a foreigned keyed table, or a referencing table
	 * and ether return the column's value or a model of the requested table.
	 *
	 * @access	magic
	 * @param	string		Name of method caller requested.
	 * @param	array		Array of arguments caller supplied to method.
	 * @return	variant		Will return either this object, or fk/reference table object
	 */
	function __call( $name, $argument ) {
		
		if( strtolower( substr( $name, 0, 3 ) ) == "get" ) {

			//Extrac the subject being requested for 
			$subject = strtolower( substr( $name, 3 ) );

			//See if they're requesting a foreign keyed table
			foreach( $this->getTable()->getFKs() as $obFK ) {
				if( $obFK->getFKTable() == $subject ) {
					$fkTable = $this->lazyLoad( $subject, $obFK->getFKName(), $obFK->getName() );
					return( $fkTable );
				}
			}

			//See if they're requesting a referencing table
			foreach( $this->getTable()->getRefs() as $obRef ) {
				if( $obRef->getRefTable() == $subject ) {
					$refTable = $this->lazyLoad( $subject, $obRef->getRefName(), $obRef->getName() );
					return( $refTable );
				}
			}

			//must be a column
			if( in_array( $subject, $this->getTable()->getColumns() ) ) {
				$this->_stringValue = (string)$this->getValue( $subject );
				return( $this );
			}

			throw new Exception( "Unable to find a gettable property for \"$subject\"." );

		}

		if( strtolower( substr( $name, 0, 5 ) ) == "where" ) {

			//Extrac the subject being requested for 
			$subject = strtolower( substr( $name, 5 ) );

			//See if we can locate the column they're requesting
			if( in_array( $subject, $this->getTable()->getColumns() ) ) {

				$value	= (string)$argument[ 0 ];
				$operator = "=";
				if( isset( $arugment[ 1 ] ) ) {
					$operator = $arugment[ 1 ];
				}

				$this->addWhere( $subject, $value, $operator );

				return( $this );
			}
			
			throw new Exception( "Unable to find column \"$subject\" for where." );
		}

		if( strtolower( substr( $name, 0, 4 ) ) == "sort" ) {

			//Extrac the subject being requested for 
			$subject = strtolower( substr( $name, 4 ) );

			//See if we can locate the column they're requesting
			if( in_array( $subject, $this->getTable()->getColumns() ) ) {
	
				$type = "ASC";
				if( isset( $argument[ 0 ] ) ) {
					$type = $argument[ 0 ];
				}

				$this->addSort( $subject, $type );
				return( $this );
			}

			throw new Exception( "Unable to find column \"$subject\" for where." );
		}

		throw new Exception( "Unknown method \"$name\"." );
	}

	//arrayaccess, iterator, and count methods 
	public function offsetExists( $offset ) { 
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( isset( $this->_recordset[ $offset ] ) ); 
	}
	public function offsetGet( $offset ) { 
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( $this->_recordset[ $offset ] ); 
	}
	public function offsetSet( $offset, $value ) { 
		throw new Exception( "You may not add records this way." ); 
	}
	public function offsetUnset( $offset ) { 
		throw new Exception( "You may not remove records this way." ); 
	}
	public function current() {
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( $this );
	}
	public function key() {
		return( $this->_currentRow );
	}
	public function next() {
		$this->_currentRow++;
	}
	public function rewind() {
		if( $this->isDirty() ) { $this->executeQuery(); }
		$this->_currentRow = 0;
	}
	public function valid() {
		if( ! isset( $this->_recordset[ $this->_currentRow ] ) ) {
			$this->_currentRow--;
			return( false );
		}
		return( true );
	}
	public function count() { 
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( count( $this->_recordset ) ); 
	}
}
