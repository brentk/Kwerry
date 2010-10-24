<?
error_reporting( E_ALL );
class FK {
	public $_name;
	public $_fktable;
	public $_fkname;
}
class Ref {
	public $_name;
	public $_reftable;
	public $_refname;
}

class Table {
	public $_name;
	public $_pk;
	public $_column = array();
	public $_fk = array();
	public $_ref = array();

	public function getName() { return $this->_name; }
	public function setName( $name ) { $this->_name = $name; }
	public function getPK() { return $this->_pk; }
	public function setPK( $pk ) { $this->_pk = $pk; }
}

class Kwerry implements arrayaccess, iterator, countable {

	private $_conn;
	private $_tableName;
	private $_table;
	private $_relationship = array();
	private $_isDirty;
	private $_where;
	private $_order;
	private $_stringValue;
	private $_currentRow = 0;
	private $_recordset = array();

	public static $_column_query;
	public static $_fk_query;
	public static $_ref_query;


	/** Attmps to find a model on the path. If on is not found, attempts
	 * to create a vanilla model by examining the database schema.
	 *
	 * @access	static
	 * @param	string	Name of requested table/model
	 * @return	object	Model object
	 */
	static function model( $tableName ) {

		//If there's a model in the path, load that
		foreach( explode( PATH_SEPARATOR, get_include_path() ) as $path ) {
			if( file_exists( $path . "/" . $tableName . ".php" ) ) {
				require_once( $path . "/" . $tableName . ".php" );
				if( class_exists( $tableName ) ) {
					$kwerry = new $tableName();
					return( $kwerry );
				}
			}
		}

		//If not, create one on the fly
		$kwerry = new Kwerry( $tableName );
		return( $kwerry );
	}

	function __construct( $tableName ) {

		$conn = pg_connect( "host=localhost port=5432 dbname=bkellydb user=brentkelly password=5uck@$$" );
		$this->setConn( $conn );

		//======================
		// Begin Introspection
		//======================

		$obTable = new Table();
		$obTable->setName( $tableName );
		$this->setTable( $obTable );

		if( ! Kwerry::$_column_query ) {
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

			Kwerry::$_column_query = pg_prepare( $this->getConn(), "column_query", $sql );
		}
		$result = pg_execute( $this->getConn(), "column_query", array( $this->getTable()->getName() ) );
		$aryColumns = pg_fetch_all( $result );

		if( $aryColumns === false ) {
			throw new Exception( "Unable to list columns in table $tableName\n\n" );
		}

		foreach( $aryColumns as $column ) {
			$this->getTable()->_column[] = $column[ "field" ];

			if( $column[ "primary_key" ] ) {
				$this->getTable()->_pk = $column[ "field" ];
			}
		}

		if( ! Kwerry::$_fk_query ) {
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

			Kwerry::$_fk_query = pg_prepare( $this->getConn(), "fk_query", $sql );
		}
		$result = pg_execute( $this->getConn(), "fk_query", array( $this->getTable()->getName() ) );
		$aryFK = pg_fetch_all( $result );

		if( $aryFK !== false ) {
			foreach( $aryFK as $fk ) {

				$obFK = new FK();
				$obFK->_name	= $fk[ "fkcol" ];
				$obFK->_fktable	= $fk[ "reftab" ];
				$obFK->_fkname	= $fk[ "refcol" ];

				$this->getTable()->_fk[] = $obFK;

			}
		}

		if( ! Kwerry::$_ref_query ) {
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

			Kwerry::$_ref_query = pg_prepare( $this->getConn(), "ref_query", $sql );
		}
		$result = pg_execute( $this->getConn(), "ref_query", array( $this->getTable()->getName() ) );
		$aryRef = pg_fetch_all( $result );
		if( $aryRef !== false ) {
			foreach( $aryRef as $ref ) {

				$obRef = new Ref();
				$obRef->_name		= $ref[ "refcol" ];
				$obRef->_reftable	= $ref[ "fktab" ];
				$obRef->_refname	= $ref[ "fkcol" ];

				$this->getTable()->_ref[] = $obRef;
			}
		}
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
	private function lazyLoad( $tableName ) {
		if( ! array_key_exists( $tableName, $this->_relationship ) ) {
			$this->_relationship[ $tableName ] = Kwerry::model( $tableName );
		}
		return( $this->_relationship[ $tableName ] );
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

	function __toString() {
		return( $this->_stringValue );
	}

	function executeQuery() {

		$param = array();

		$sql = " SELECT * FROM ".$this->getTable()->getName() . " ";

		if( count( $this->_where ) ) {

			$where = "";
			$and = "WHERE";

			foreach( $this->_where as $aryWhere ) {

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
			}
	
			$sql .= $where;
		}


		if( count( $this->_order ) ) {
			$orderBy = "";
			$comma = "ORDER BY";

			foreach( $this->_order as $sort ) {
				$orderBy .= " " . $comma . " " . $sort[ "field" ] . " " . $sort[ "type" ];
				$comma = ",";
			}
			$sql .= $orderBy;
		}

		debug_print_backtrace();

		$res = pg_prepare( $this->getConn(), $q, $sql );
		$result = pg_execute( $this->getConn(), $q, $param );
		$this->_recordset = pg_fetch_all( $result );
		$this->_currentRow = 0;
		$this->isDirty( false );
	}

	function getValue( $column ) {
		if( $this->isDirty() ) { $this->executeQuery(); }
		return( $this->_recordset[ $this->_currentRow ][ $column ] );
	}

	function __call( $name, $argument ) {

		if( strtolower( substr( $name, 0, 3 ) ) == "get" ) {

			//Extrac the subject being requested for 
			$subject = strtolower( substr( $name, 3 ) );

			//See if they're requesting a foreign keyed table
			foreach( $this->getTable()->_fk as $obFK ) {
				if( $obFK->_fktable == $subject ) {
					$fkTable = $this->lazyLoad( $subject );
					$fkmethod = "where" . $obFK->_fkname;
					$localmethod = "get" . $obFK->_name;
					$fkTable->$fkmethod( $this->$localmethod() );
					return( $fkTable );
				}
			}

			//See if they're requesting a referencing table
			foreach( $this->getTable()->_ref as $obRef ) {
				if( $obRef->_reftable == $subject ) {
					$refTable = $this->lazyLoad( $subject );
					$refmethod = "where" . $obRef->_refname;
					$localmethod = "get" . $obRef->_name;
					$refTable->$refmethod( $this->$localmethod() );
					return( $refTable );
				}
			}

			//must be a column
			if( in_array( $subject, $this->getTable()->_column ) ) {
				$this->_stringValue = $this->getValue( $subject );
				return( $this );
			}

			throw new Exception( "Unable to find a gettable property for \"$subject\"." );

		}

		if( strtolower( substr( $name, 0, 5 ) ) == "where" ) {

			//Extrac the subject being requested for 
			$subject = strtolower( substr( $name, 5 ) );

			//See if we can locate the column they're requesting
			if( in_array( $subject, $this->getTable()->_column ) ) {

				$value	= $argument[ 0 ];
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
			if( in_array( $subject, $this->getTable()->_column ) ) {
	
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

	protected function setTable( $table ) { $this->_table = $table; }
	protected function getTable() { return( $this->_table ); }
	public function setConn( $conn ) { $this->_conn = $conn; }
	public function getConn() { return( $this->_conn ); }

	//array & iterator functions 
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
		if( $this->isDirty() ) { $this->executeQuery(); }
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
