<?php
namespace Kwerry;

class Relationship {
	private $_localColumn;
	private $_foreignTable;
	private $_foreignColumn;
	public function getLocalColumn() {
		return $this->_localColumn;
	}
	public function setLocalColumn( $localColumn ) {
		$this->_localColumn = $localColumn;
	}
	public function getForeignTable() {
		return $this->_foreignTable;
	}
	public function setForeignTable( $foreignTable ) {
		$this->_foreignTable = $foreignTable;
	}
	public function getForeignColumn() {
		return $this->_foreignColumn;
	}
	public function setForeignColumn( $foreignColumn ) {
		$this->_foreignColumn = $foreignColumn;
	}
}


