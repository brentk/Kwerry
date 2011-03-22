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
			throw new Exception( "\$kwerry_opts Array not found!" );
		}
		if( ! is_array( $kwerry_opts ) ) { 
			throw new Exception( "\$kwerry_opts not an Array!" );
		}
		$dbclass = $kwerry_opts[ $connectionName ][ "dbtype" ];

		//Attempt to include the driver file
		if( ! file_exists( "Kwerry/drivers/{$dbclass}.php" ) ) {
			throw new Exception( "Database driver for dbtype \"".$dbclass."\" not found." );
		}

		require_once( "Kwerry/drivers/{$dbclass}.php" );

		if( ! class_exists( $dbclass ) ) {
			throw new Exception( "Driver class not found for dbtype: \"".$dbclass."\"." );
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
