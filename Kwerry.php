<?
/**
 * Kwerry. A small, introspection based PHP ORM.
 *
 * @author   Brent Kelly <brenttkelly@gmail.com>
 * @package  Kwerry
 */

require_once( dirname( __FILE__ ) . "/library/Relationship.php" );
require_once( dirname( __FILE__ ) . "/library/Column.php" );
require_once( dirname( __FILE__ ) . "/library/Table.php" );
require_once( dirname( __FILE__ ) . "/library/Database.php" );

class Kwerry implements arrayaccess, iterator, countable {

	/**
	 * @var  Kwerry\Table  Table object for the table that this model is representing.
	 */
	public $_table;

	/**
	 * @var  array  Array of column relationships that this model contains.
	 */
	public $_relationship = array();

	/**
	 * @var  boolean  Notifies the object if the user has added more criteria to the current model's query.
	 */
	public $_isDirty;

	/**
	 * @var  array  Array containing all where clauses added to the current model's query.
	 */
	public $_where;

	/**
	 * @var  array  Array containing all sort (order by) clauses added.
	 */
	public $_order;

	/**
	 * @var  integer  Number of records to limit the current model's query to.
	 */
	public $_limit;

	/**
	 * @var  integer  Number of records to offset the current model's query by.
	 */
	public $_offset;

	/**
	 * @var  integer  Reflects where in the current model's recrodset the cursor is.
	 */
	private $_currentRow = 0;

	/**
	 * @var  array  Array containing the current model's recordset.
	 */
	private $_recordset = array();

	/**
	 * @var  array  Array containing all changes to make to current model (and write to the database) on ::save().
	 */
	private $_updateBuffer = array();

	/**
	 * @var  boolean  Notifies the object if the user is performing an insert with the current model.
	 */
	private $_isAddingNew = false;

	/**
	 * @var  string  Which connection this model is using.
	 */
	private $_connectionName;

	/**
	 * @staticva  array  Contains all defined connection details.
	 */
	public static $_connectionDetails = array();

	/**
	 * @staticva  array  Contains all active connections.
	 */
	public static $_connections = array();

	/**
	 * Adds connectio  informatio  to the object's static array of
	 * connection details (db type, username, password, etc).
	 *
	 * @param   string     [OPTIONAL] Connection name, defaults to "default"
	 * @param   string     Setting name (e.g. "username", "password", etc).
	 * @param   string     Setting value
	 * @throws  Exception  Proper arguments were not passed into method.
	 * @return  null
	 */
	public static function setConnection() {

		$connectionName	= "";
		$settingName	= "";
		$settingValue	= "";

		if( func_num_args() < 2 || func_num_args() > 3 ) {
			throw new Exception( "Kwerry::setConnection() requires the following arguments: ".
				"connectionName [\"default\"], settingName, settingValue." );
		}

		if( func_num_args() == 2 ) {
			$connectionName	= "default";
			$settingName	= func_get_arg( 0 );
			$settingValue	= func_get_arg( 1 );
		} else {
			$connectionName	= func_get_arg( 0 );
			$settingName	= func_get_arg( 1 );
			$settingValue	= func_get_arg( 2 );
		}

		if( ! array_key_exists( $connectionName, Kwerry::$_connectionDetails ) ) {
			Kwerry::$_connectionDetails[ $connectionName ] = new stdClass();
		}

		Kwerry::$_connectionDetails[ $connectionName ]->$settingName = $settingValue;
	}

	/**
	 * Clears the current state of the model except for which table
	 * it is representing.
	 *
	 * @return  null
	*/
	public function clear() {
		$this->_where		= NULL;
		$this->_order		= NULL;
		$this->_isAddingNew	= false;
		$this->_currentRow	= 0;
		$this->_relationship	= array();
		$this->_recordset	= array();
		$this->_updateBuffer	= array();
		$this->_limit		= NULL;
		$this->_offset		= NULL;
		$this->isDirty( false );
	}

	/**
	 * Attmps to find a model on the path. If on is not found, attempts
	 * to create a vanilla model by examining the database schema.
	 *
	 * @param   string  Name of requested table/model
	 * @return  Kwerry  Model object
	 */
	static function model( $tableName, $connectionName = "default" ) {

		//If there's a model in the path, load that
		foreach( explode( PATH_SEPARATOR, get_include_path() ) as $path ) {

			$full_path = "{$path}/Kwerry/{$tableName}.php";

			if( file_exists( $full_path ) ) {
				require_once( $full_path );
				if( class_exists( $tableName ) ) {
					$kwerry = new $tableName( $connectionName );
					return $kwerry;
				}
			}
		}

		//If not, create one on the fly
		$kwerry = new Kwerry( $tableName, $connectionName );
		return $kwerry;
	}

	/**
	 * Attempts to create the driver and instruct it to make a connection
	 * with the available connection details.
	 *
	 * @param   string     Name of the connection to use.
	 * @throws  Exception  No file found for specified driver.
	 * @throws  Exception  No class found for specified driver.
	 * @return  null
	 */
	protected static function createConnection( $connectionName ) {

		if( array_key_exists( $connectionName, Kwerry::$_connections ) ) {
			return;
		}

		$databaseDriver = strtolower( Kwerry::$_connectionDetails[ $connectionName ]->driver );

		//Attempt to include the driver file
		if( ! file_exists( dirname(__FILE__)."/drivers/{$databaseDriver}.php" ) ) {
			throw new Exception( "Database driver \"".$databaseDriver."\" not found." );
		}

		require_once( dirname(__FILE__)."/drivers/{$databaseDriver}.php" );

		$databaseDriver = "Kwerry\\".$databaseDriver;

		if( ! class_exists( $databaseDriver ) ) {
			throw new Exception( "Unable to find database driver class named \"{$databaseDriver}\"." );
		}

		$host		= Kwerry::$_connectionDetails[ $connectionName ]->host;
		$port		= Kwerry::$_connectionDetails[ $connectionName ]->port;
		$dbname		= Kwerry::$_connectionDetails[ $connectionName ]->dbname;
		$username	= Kwerry::$_connectionDetails[ $connectionName ]->username;
		$password	= Kwerry::$_connectionDetails[ $connectionName ]->password;

		Kwerry::$_connections[ $connectionName ] = new $databaseDriver();
		Kwerry::$_connections[ $connectionName ]->setHost(	$host );
		Kwerry::$_connections[ $connectionName ]->setPort(	$port );
		Kwerry::$_connections[ $connectionName ]->setDBName(	$dbname );
		Kwerry::$_connections[ $connectionName ]->setUsername(	$username );
		Kwerry::$_connections[ $connectionName ]->setPassword(	$password );
		Kwerry::$_connections[ $connectionName ]->connect();
	}

	/**
	 * Returns the current model's connection object.
	 *
	 * @return  Kwerry\Database  Current model's connection object.
	 */
	function getConnection() {
		self::createConnection( $this->_connectionName );
		return Kwerry::$_connections[ $this->_connectionName ];
	}

	/**
	 * Uses the connection's database class's introspection to
	 * create a model of the table's layout. If a defined class
	 * exists, it can override this to build the table by hand to
	 * increase speed and flexibility.
	 *
	 * @param   string     Name of table to instruction the driver to query
	 * @throws  Exception  Primary key not found in requested table.
	 * @return  null
	 */
	protected function buildDataModel( $tableName ) {
		$table = new Kwerry\Table();
		$table->setName( $tableName );
		$this->setTable( $table );
		$this->getConnection()->introspection( $this->getTable() );
		if( ! $this->getTable()->getPrimaryKey() ) {
			throw new Exception( "No primary key found in \"".$this->getTable()->getName()."\"." );
		}
	}

	/**
	 * Constructor.
	 *
	 * @param  string  Name of table to build model for
	 * @param  string  Database connection to use.
	 */
	function __construct( $tableName, $connectionName ) {
		$this->_connectionName = $connectionName;
		$this->buildDataModel( $tableName );
		$this->_limit = null;
		$this->_offset = null;
		$this->isDirty( true );
	}

	/**
	 * isDirty is used to let the object know whether or not
	 * the parameters of the query have changed. If so, we need to
	 * execute or re-execute the query with the lastest query.
	 *
	 * @param   bool  optional wheher object is dirty or not
	 * @return  bool  state if object
	 */
	private function isDirty( $value = NULL ) {
		if( ! is_null( $value ) ) {
			$this->_isDirty = $value;
		}
		return( $this->_isDirty );
	}

	/**
	 * Build and cache relateed models. Return cached versions if
	 * previously built.
	 *
	 * @param   string  name of table
	 * @return  Kwerry  requested model object
	 */
	private function lazyLoad( $tableName, $their_column, $my_column ) {

		$value = $this->$my_column;

		$hash = serialize( array( $tableName, $their_column, $value ) );

		if( ! array_key_exists( $hash, $this->_relationship ) ) {
			$method = "where".$their_column;
			$this->_relationship[ $hash ] = Kwerry::model( $tableName );
			$this->_relationship[ $hash ]->$method( $value );
		}

		return( $this->_relationship[ $hash ] );
	}

	/**
	 * Create an order by clause to add to the query and sets the
	 * object as dirty.
	 *
	 * @param   string  Name of database field to sort by
	 * @param   string  (optional) Type of sort (defaults to ascending)
	 * @return  null
	 */
	public function addSort( $name, $type = "ASC" ) {
		
		$this->isDirty( true );

		$sort		= array();
		$sort[ "field" ]= $name;
		$sort[ "type" ]	= $type;
		$this->_order[]	= $sort;

	}

	/**
	 * Create a where clause to add to the query and sets the
	 * object as dirty. It also normalizes the arguments so that
	 * the query processor doesn't need any more overhead.
	 *
	 * @param   string     Name of database field to filter by
	 * @param   mixed      value(s) to filter with (could be array of values)
	 * @param   string     (optional) Operator to filter with (defaults to equals)
	 * @throws  Exception  Unknown comparison operator passed in.
	 * @return  null
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

		$where = array();
		$where[ "field" ]	= $field;
		$where[ "operator" ]	= $operator;
		$where[ "value" ]	= $value;
		$this->_where[]		= $where;

		return;
	}

	/**
	 * Actual execution of built queries.  Handles compiling all the
	 * where and sort properties and compiles it into an actual SQL query.
	 * In the future this will need to be delegated to db specifiec functions
	 * to handle the different nuances of each db's SQL implementation.
	 *
	 * @return  null
	 */
	private function executeQuery() {
		$recordset = $this->getConnection()->execute( $this );
		
		if( $recordset === false ) {
			$this->_recordset = array();
		} else {
			$this->_recordset = $recordset;
		}

		$this->_currentRow = 0;
		$this->isDirty( false );
	}

	/**
	 * Runs straight SQL and attempts to wire the Kwerry object to
	 * act like the results is a normal internal Kwerry recordset.
	 *
	 * FIXME: Object needs to be informed that it's hydrated and no longer
	 * accept chained ->where()'s, ->sort()'s, etc.
	 *
	 * @param   string     SQL SELECT statement
	 * @param   string     Parameters for parameterized SQL statement
	 * @throws  Exception  Non-SELECT statement passed in
	 * @return  Kwerry     Current Kwerry object
	 */
	public function hydrate( $sql, Array $params = NULL) {

		if( "select" != trim(strtolower(substr($sql,0,6))) ){
			throw new Exception( "Only SELECT statements can be passed to Kwerry::hydrate()." );
		}

		if( is_null( $params ) ) {
			$params = array();
		}

		$this->_recordset = $this->getConnection()->runSQL( $sql, $params );
		$this->isDirty( false );

		return $this;
	}

	/**
	 * Static. Instructs the driver to start a transaction.
	 *
	 * @return  null
	 */
	public static function begin( $connectionName = "default" ) {
		self::createConnection( $connectionName );
		return Kwerry::$_connections[ $connectionName ]->begin();
	}

	/**
	 * Static. Instructs the driver to commit the current transaction.
	 *
	 * @return	NULL
	 */
	public static function commit( $connectionName = "default" ) {
		self::createConnection( $connectionName );
		return Kwerry::$_connections[ $connectionName ]->commit();
	}

	/**
	 * Static. Instructs the driver to rollback the current transaction.
	 *
	 * @return  null
	 */
	public static function rollback( $connectionName = "default" ) {
		self::createConnection( $connectionName );
		return Kwerry::$_connections[ $connectionName ]->rollback();
	}

	/**
	 * Static. Runs straight SQL and returns the raw result (usually a recordset
	 * in the form of an assoc array).
	 *
	 * @param   string  SQL statement
	 * @param   string  Parameters for parameterized SQL statement
	 * @param   string  Connection to use (defaults to "default")
	 * @return  mixed   Driver's result of the given sql statement
	 */
	public static function runSQL ( $sql, Array $params = NULL, $connectionName = "default" ) {
		if( is_null( $params ) ) {
			$params = array();
		}
		self::createConnection( $connectionName );
		return Kwerry::$_connections[ $connectionName ]->runSQL( $sql, $params );
	}

	/**
	 * Static. Retrieves the database specific random call
	 *
	 * @return  string
	 */
	public static function random( $connectionName = "default" ) {
		self::createConnection( $connectionName );
		return Kwerry::$_connections[ $connectionName ]->getRandom();
	}

	/**
	 * Static. Retrieves the database specific true value
	 *
	 * @return  string
	 */
	public static function true( $connectionName = "default" ) {
		self::createConnection( $connectionName );
		return Kwerry::$_connections[ $connectionName ]->getTrue();
	}

	/**
	 * Static. Retrieves the database specific false value
	 *
	 * @return  string
	 */
	public static function false( $connectionName = "default" ) {
		self::createConnection( $connectionName );
		return Kwerry::$_connections[ $connectionName ]->getFalse();
	}

	/**
	 * Returns a column's value at the current cursor in the
	 * recordset.  Will execute (or re-execute) the object's
	 * query if needed.
	 *
	 * @param   string     Column name.
	 * @throws  Exception  Attempting to access property in an empty recordset.
	 * @throws  Exception  Attempting to access property at non-existant index in recordset.
	 * @return  string     Column value at current cursor position.
	 */
	protected function getValue( $column ) {
		if( $this->isDirty() ) { $this->executeQuery(); }

		if( count( $this->_recordset ) == 0 )
			throw new Exception( "Attempting to access property \"{$column}\" in empty recordset." );

		if( ! array_key_exists( $this->_currentRow, $this->_recordset ) )
			throw new Exception( "Attempting to access property \"{$column}\" at unkown recordset offset \"{$this->_currentRow}\"." );

		//If it's an actual boolean field, return the cooresponding php boolean value
		if( $this->getTable()->getColumn( $column )->getDataType() == DATA_TYPE_BOOL ) {
			if( $this->_recordset[ $this->_currentRow ][ $column ] == Kwerry::true() ) {
				return true;
			} else if( $this->_recordset[ $this->_currentRow ][ $column ] == Kwerry::false() ) {
				return false;
			}
		}

		return( $this->_recordset[ $this->_currentRow ][ $column ] );
	}

	/**
	 * Returns an array of this table's column names.
	 *
	 * @return  array  This model's table's column names
	 */
	public function getColumns() {
		return( $this->getTable()->getColumns() );
	}

	/**
	 * Sets the object that contains details about the table
	 * that the current model represents.
	 *
	 * @param   Kwerry\Table  Object containing table information for current model's table.
	 * @return  null
	 */
	public function setTable( $table ) {
		$this->_table = $table;
	}

	/**
	 * Returns the object that contains details about the table
	 * that the current model represents.
	 *
	 * @return  Kwerry\Table  Object containing table information for current model's table.
	 */
	public function getTable() { return( $this->_table ); }

	/**
	 * When using the model in the a writable context (insert or update),
	 * this is the magic function in which the new values will come in.
	 *
	 * @param   string   Name of column being written to.
	 * @param   mixed    Value of column being written.
	 * @return  null
	 */
	public function __set( $name, $value ) {
		if( $this->getTable()->hasColumn( $name ) ) {
			$this->_updateBuffer[ $name ] = $value;
		}
	}

	/**
	 * Instructs the objec that the user is using the current
	 * object in an "insert" context and expects to write
	 * a new record to the database with this object's values.
	 *
	 * @param   boolean   Denotes that insert mode is on or not.
	 * @return  null
	 */
	protected function isAddingNew( $value = NULL ) {
		if( is_null( $value ) ) {
			return( $this->_isAddingNew );
		}
		$this->_isAddingNew = $value;
	}

	/**
	 * Method user uses to instruct the object to enter "insert"
	 * mode.
	 *
	 * @return  Kwerry  Current Kwerry object
	 */
	public function addnew() {
		$this->isAddingNew( true );
		return $this;
	}

	/**
	 * Instructs the driver to write the buffered changes (or new values
	 * in the case of ::addnew()) to the database.
	 *
	 * @return  Kwerry  Current Kwerry object
	 */
	public function save() {
		if( $this->isAddingNew() ) {
			//Insert record
			$key = $this->getConnection()->insert( $this->_updateBuffer, $this );

			//remove all state from this object (including isAddingNew)
			$this->clear();

			//Pull just this new record
			$method = "where" . $this->getTable()->getPrimaryKey();
			$this->$method( $key );
		} else {
			$updatedRecord = $this->getConnection()->update( $this->_updateBuffer, $this );
			$this->_recordset[ $this->_currentRow ] = $updatedRecord[0];
		}

		$this->_updateBuffer = array();
		return $this;
	}

	/**
	 * Instructs the driver to delete the record that the current
	 * cursor points to in the internal recordset.
	 *
	 * @return  null
	 */
	public function delete() {
		$this->getConnection()->delete( $this );
		$this->clear();
	}

	/**
	 * Magic get function used to pull column values, foreign
	 * keyed tables, or referecing tables.
	 *
	 * @param   string     Property name.
	 * @throws  Exception  Requested non-existant property (column or relationship).
	 * @return  Kwerry     Reference to current, keyed, or referencing Kwerry object.
	 */
	public function __get( $name ) {

		//Check for column
		if( $this->getTable()->hasColumn( $name ) ) {
			return $this->getValue( $name );
		}

		//See if they're requesting a related table
		foreach( $this->getTable()->getRelationships() as $relationship ) {
			if( $relationship->getForeignTable() == $name ) {
				$foreignTable = $this->lazyLoad( $name, $relationship->getForeignColumn(), $relationship->getLocalColumn() );
				return( $foreignTable );
			}
		}

		throw new Exception( "No property named \"{$name}\" found in \"{$this->getTable()->getName()}\"." );
	}

	/**
	 * Catch all function for ->whereColumnName(), & ->sortColumnName().
	 *
	 * @param   string     Name of method caller requested.
	 * @param   array      Array of arguments caller supplied to method.
	 * @throws  Exception  Issued where on non-existant column.
	 * @throws  Exception  Issued sort on non-existant column.
	 * @throws  Exception  Unknown method called.
	 * @return  Kwerry     Current Kwerry object
	 */
	function __call( $name, $argument ) {

		//User is specifying a "WHERE"
		if( strtolower( substr( $name, 0, 5 ) ) == "where" ) {

			//Extrac the subject being requested for 
			$subject = strtolower( substr( $name, 5 ) );

			//See if we can locate the column they're requesting
			if( $this->getTable()->hasColumn( $subject ) ) {

				$value = $argument[ 0 ];
				$operator = "=";
				if( isset( $argument[ 1 ] ) ) {
					$operator = $argument[ 1 ];
				}

				$this->addWhere( $subject, $value, $operator );

				return( $this );
			}
			
			throw new Exception( "Unable to find column \"$subject\" for where." );
		}

		//User is specifying an "ORDER BY"
		if( strtolower( substr( $name, 0, 4 ) ) == "sort" ) {

			//Extrac the subject being requested for 
			$subject = strtolower( substr( $name, 4 ) );

			//See if we can locate the column they're requesting
			if( $this->getTable()->hasColumn( $subject ) ) {
	
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

	/** Limits query to only return specified amount of records.
	 *
	 * @param   integer  number of record to return
	 * @return  Kwerry   Current Kwerry object
	*/
	public function limit( $limit ) {
		$this->isDirty( true );
		$this->_limit = $limit;
		return $this;
	}

	/** Offsets query to only return specified amount of records.
	 *
	 * @param   integer  number of record to return
	 * @return  Kwerry   Current Kwerry object
	*/
	public function offset( $offset ) {
		$this->isDirty( true );
		$this->_offset = $offset;
		return $this;
	}

	//////////////////////////////////////////////////////
	//ArrayAccess, ArrayIterator, and Count implementation

	/**
	 * Implementation of the index test for the ArrayAccess
	 * interface that maps to model's internal recordset.
	 *
	 * @param   integer  Index to test for in the current recordset.
	 * @return  boolean  Whether or not the internal recordset contains a record at the given index.
	 */
	public function offsetExists( $offset ) { 
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( isset( $this->_recordset[ $offset ] ) ); 
	}

	/**
	 * Implementation of the index request for the ArrayAccess
	 * interface that maps to model's internal recordset.
	 *
	 * @param   integer    Index in the internal recordset to pull.
	 * @throws  Exception  Requested index does not exist in internal recordset.
	 * @return  Kwerry     Object with the cursor in the internal recordset moved to the requested position
	 */
	public function offsetGet( $offset ) { 
		if( $this->isDirty() ) { $this->executeQuery(); }

		if( $offset < 0 || $offset > count( $this->_recordset )-1) {
			throw new Exception( "Index \"$offset\" does not exist." );
		}

		$this->_currentRow = $offset;
		return $this;
	}

	/**
	 * Implementation of writing at index for the ArrayAccess
	 * interface. Kwerry handles its own writing via magic __set() call,
	 * and there is currently no plan to implement writting an entire record
	 * via the array access, so this exists just to throw an exception if
	 * the user tries.
	 *
	 * @param   integer    ignored.
	 * @param   mixed      ignored.
	 * @throws  Exception  Will always throw this exception stating that you cannot write to the objec this way.
	 * @return  null
	 */
	public function offsetSet( $offset, $value ) { 
		throw new Exception( "You may not add records this way." ); 
	}

	/**
	 * Implementation of removing a row for the ArrayAccess
	 * interface. This could be mapped to the ::delete() method,
	 * but that seems a bit goofy and hidden, so I'm just disabling
	 * this implementation.
	 *
	 * @param   integer    ignored.
	 * @throws  Exception  Will always throw this exception stating that you cannot use this.
	 */
	public function offsetUnset( $offset ) { 
		throw new Exception( "You may not remove records this way." ); 
	}

	/**
	 * Implementation of retrieving the current row for the ArrayIterator
	 * interface. Leaves the internal recordset's cursor where it is and returns
	 * the current object.
	 *
	 * @return  Kwerry   Current Kwerry object
	 */
	public function current() {
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( $this );
	}

	/**
	 * Implementation of retrieving the current key for the ArrayIterator
	 * interface. Returns the value of the internal recordset's cursor position.
	 *
	 * @return  integer  Internal recordset cursor position
	 */
	public function key() {
		return( $this->_currentRow );
	}

	/**
	 * Implementation of advancing to the next index for the ArrayIterator
	 * interface. Simply advances the current position of the internal recordset's
	 * cursor.
	 *
	 * @return  null
	 */
	public function next() {
		$this->_currentRow++;
	}

	/**
	 * Implementation of returning to the first index for the ArrayIterator
	 * interface. Simply set the current position of the internal recordset's
	 * cursor to zero.
	 *
	 * @return  null
	 */
	public function rewind() {
		if( $this->isDirty() ) { $this->executeQuery(); }
		$this->_currentRow = 0;
	}

	/**
	 * Implementation of check for more indexes for the ArrayIterator
	 * interface. After being advanced by one, checks to see of the
	 * internal recordset contains a record at the current position.
	 * If not it rolls it back by 1 to a valid record.
	 *
	 * @return  boolean  Whether or not the internal recordset's cursor landed beyond the range of existing indexes.
	 */
	public function valid() {
		if( ! isset( $this->_recordset[ $this->_currentRow ] ) ) {
			$this->_currentRow--;
			return( false );
		}
		return( true );
	}

	/**
	 * Implementation of Countable interface. Simply counts
	 * how many records are in the internal recordset.
	 *
	 * @return  integer  Number of records in the internal recordset
	 */
	public function count() { 
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( count( $this->_recordset ) ); 
	}
}
