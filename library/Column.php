<?php
namespace Kwerry;

define( "DATA_TYPE_INTEGER",	0 );
define( "DATA_TYPE_STRING",	1 );
define( "DATA_TYPE_DATE",	2 );
define( "DATA_TYPE_TIME",	3 );
define( "DATA_TYPE_STAMP",	4 );
define( "DATA_TYPE_BOOL",	5 );
define( "DATA_TYPE_NUMERIC",	6 );
define( "DATA_TYPE_BLOB",	7 );

class Column {
	private $_name;
	private $_datatype;
	public function getName() {
		return $this->_name;
	}
	public function setName( $name ) {
		$this->_name = $name;
	}
	public function getDataType() {
		return $this->_datatype;
	}
	public function setDataType( $datatype ) {
		$this->_datatype = $datatype;
	}
}
