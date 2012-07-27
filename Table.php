<?php
namespace Kwerry;

class Table {
	private $_name;
	private $_primaryKey;
	private $_columns		= array();
	private $_relationships		= array();

	public function getName() {
		return $this->_name;
	}
	public function setName( $name ) {
		$this->_name = $name;
	}
	public function getPrimaryKey() {
		return $this->_primaryKey;
	}
	public function setPrimaryKey( $primaryKey ) {
		$this->_primaryKey = $primaryKey;
	}
	public function addColumn( $column ) {
		$this->_columns[] = $column;
	}
	public function getColumns() {
		return( $this->_columns );
	}
	public function addRelationship( Relationship $relationship ) {
		$this->_relationships[] = $relationship;
	}
	public function getRelationships() {
		return( $this->_relationships );
	}

	public function hasColumn( $name ) {
		foreach( $this->getColumns() as $column ) {
			if ( $column->getName() == $name ) {
				return true;
			}
		}
		return false;
	}
}

