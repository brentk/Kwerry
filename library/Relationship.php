<?php
namespace Kwerry;

class Relationship {

	/**
	 * Reference to the table object that this reference belongs to
	 * @var Kwerry\Table
	 */
	protected $_parentTable;

	/**
	 * Name of column in this table that relates to a column in another table.
	 * @var string
	 */
	protected $_localColumn;

	/**
	 * Name of the related table
	 * @var string
	 */
	protected $_foreignTable;

	/**
	 * Name of column in the related table that the local column references.
	 * @var string
	 */
	protected $_foreignColumn;

	/**
	 * Constructor.
	 *
	 * @param   Kwerry\Table         Reference to parent table that this relationship belongs to.
	 * @return  Kwerry\Relationship  Instance of this object.
	 */
	public function __construct( Table $parentTable ) {
		$this->_parentTable = $parentTable;
	}

	/**
	 * Gets the column in this table that relates to a column in another table.
	 *
	 * @return  string  Column name.
	 */
	public function getLocalColumn() {
		return $this->_localColumn;
	}

	/**
	 * Sets the column in this table that relates to a column in another table.
	 *
	 * @param   string  Column name.
	 * @return  null
	 */
	public function setLocalColumn( $localColumn ) {
		$this->_localColumn = $localColumn;
	}

	/**
	 * Gets the name of the related table.
	 *
	 * @return  string  Column name.
	 */
	public function getForeignTable() {
		return $this->_foreignTable;
	}

	/**
	 * Sets the name of the related table.
	 *
	 * @return  string  Column name.
	 * @return  null
	 */
	public function setForeignTable( $foreignTable ) {
		$this->_foreignTable = $foreignTable;
	}

	/**
	 * Gets the name of column in the related table that the local column references.
	 *
	 * @return  string  Column name.
	 */
	public function getForeignColumn() {
		return $this->_foreignColumn;
	}

	/**
	 * Sets the name of column in the related table that the local column references.
	 *
	 * @return  string  Column name.
	 * @return  null
	 */
	public function setForeignColumn( $foreignColumn ) {
		$this->_foreignColumn = $foreignColumn;
	}
}


