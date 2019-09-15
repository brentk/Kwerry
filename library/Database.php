<?php
namespace Kwerry;

class Database {
	private $_host		= null;
	private $_port		= null;
	private $_dbname	= null;
	private $_username	= null;
	private $_password	= null;
	private $_random	= null;
	private $_true		= null;
	private $_false		= null;

	public function setHost( $value ) {
		$this->_host = $value;
	}
	public function getHost() {
		return $this->_host;
	}
	public function setPort( $value ) {
		$this->_port = $value;
	}
	public function getPort() {
		return $this->_port;
	}
	public function setDBName( $value ) {
		$this->_dbname = $value;
	}
	public function getDBName() {
		return $this->_dbname;
	}
	public function setUsername( $value ) {
		$this->_username = $value;
	}
	public function getUsername() {
		return $this->_username;
	}
	public function setPassword( $value ) {
		$this->_password = $value;
	}
	public function getPassword() {
		return $this->_password;
	}
	public function setRandom( $value ) {
		$this->_random = $value;
	}
	public function getRandom() {
		if( is_null( $this->_random ) )
			throw new \Exception( "Random function not defined in ".get_called_class());
		return $this->_random;
	}
	public function setTrue( $value ) {
		$this->_true = $value;
	}
	public function getTrue() {
		if( is_null( $this->_true ) )
			throw new \Exception( "True value not defined in ".get_called_class());
		return $this->_true;
	}
	public function setFalse( $value ) {
		$this->_false = $value;
	}
	public function getFalse() {
		if( is_null( $this->_false ) )
			throw new \Exception( "False value not defined in ".get_called_class());
		return $this->_false;
	}

	public function connect() {
		throw new \Exception( get_called_class()."::connect not implemented!" );
	}
	public function introspection( Table $table ) {
		throw new \Exception( get_called_class()."::introspection not implemented!" );
	}
	public function execute( \Kwerry $kwerry ) {
		throw new \Exception( get_called_class()."::execute not implemented!" );
	}
	public function runSQL( $sql, array $params ) {
		throw new \Exception( get_called_class()."::runSQL not implemented!" );
	}
	public function insert( array $update_buffer, \Kwerry $kwerry ) {
		throw new \Exception( get_called_class()."::insert not implemented!" );
	}
	public function update( array $update_buffer, \Kwerry $kwerry ) {
		throw new \Exception( get_called_class()."::update not implemented!" );
	}
	public function delete( \Kwerry $kwerry ) {
		throw new \Exception( get_called_class()."::delete not implemented!" );
	}

}
