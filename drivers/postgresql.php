<?
class postgresql extends database {

	private $_connection;
	public $_prepared_statement = array();

	public function __construct() {
		if( ! function_exists( "pg_connect" ) ) {
			throw new Exception( "PostgreSQL PHP support not installed." );
		}
	}

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
		$index = array_search( $sql, $this->_prepared_statement );
		if( $index === false ) {
			$index = count( $this->_prepared_statement );
			$this->_prepared_statement[ $index ] = $sql;
			$toss = pg_prepare( $this->_connection, $index, $sql );
		}
		return $index;
	}

	private function getDataType( $type ) {

		switch( $type ) {
			case( "int4" ):
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

		foreach( $aryColumns as $record ) {
			$column = new Column();
			$column->setName( $record[ "field" ] );
			$column->setDataType( $this->getDataType( $record[ "type" ] ) );
			$obTable->addColumn( $column );

			if( $record[ "primary_key" ] ) {
				$obTable->setPK( $record[ "field" ] );
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

	public function execute( Kwerry &$obKwerry ) {

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
		return $recordset;
	}

	function update( $columns, Kwerry $parent ) {

		$pkName = $parent->getTable()->getPK();
		$pkValue = (string)$parent->$pkName;

		$comma = " SET ";
		$param = array();

		$sql = "UPDATE ".$parent->getTable()->getName();

		foreach( $columns as $name => $value ) {
			$param[] = $value;
			$sql .= $comma . $name . " = $" . count( $param );
			$comma = ", ";
		}

		$param[] = $pkValue;
		$sql .= " WHERE " . $pkName . " = $" . count( $param );

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $param );

		/* return the new values so the kwerry object can update them */
		$sql = "SELECT * FROM " . $parent->getTable()->getName() . " WHERE " . $pkName . " = $1 ";
		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), array( $pkValue ) );
		$recordset = pg_fetch_all( $result );
		return $recordset;
	}

	function insert( $columns, Kwerry $parent ) {

		$comma = "";
		$param = array();
		$paramString = "";

		$sql = "INSERT INTO " . $parent->getTable()->getName() . " ( ";

		foreach( $columns as $name => $value ) {
			$param[] = $value;
			$sql .= $comma . $name;
			$paramString .= $comma . " $".count( $param );
			$comma = ", ";
		}

		$sql .= " ) values ( " . $paramString . " )";

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $param );
		$result = pg_execute( $this->_connection, $this->getQuery( "select lastval();" ), array() );
		$recordset = pg_fetch_all( $result );
		/* return the new values so the kwerry object can update them */
		return $recordset[ 0 ][ "lastval" ];
	}

	function delete( Kwerry $parent ) {

		$pkName = $parent->getTable()->getPK();
		$pkValue = (string)$parent->$pkName;

		$comma = " SET ";
		$param = array();

		$sql = "DELETE FROM ".$parent->getTable()->getName();

		$param[] = $pkValue;
		$sql .= " WHERE " . $pkName . " = $" . count( $param );

		$result = pg_execute( $this->_connection, $this->getQuery( $sql ), $param );

		/* return the new values so the kwerry object can update them */
		return true;
	}


}

