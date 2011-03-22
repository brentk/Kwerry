<?
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

