<?php
namespace Kwerry;

class Database {
	private $_host;
	private $_port;
	private $_dbname;
	private $_username;
	private $_password;

	public function setHost( $value ) {
		$this->_host = $value;
	}
	public function getHost() {
		return( $this->_host );
	}
	public function setPort( $value ) {
		$this->_port = $value;
	}
	public function getPort() {
		return( $this->_port );
	}
	public function setDBName( $value ) {
		$this->_dbname = $value;
	}
	public function getDBName() {
		return( $this->_dbname );
	}
	public function setUsername( $value ) {
		$this->_username = $value;
	}
	public function getUsername() {
		return( $this->_username );
	}
	public function setPassword( $value ) {
		$this->_password = $value;
	}
	public function getPassword() {
		return( $this->_password );
	}

	public function connect() {
		throw new Exception( get_called_class()."::connect not implemented!" );
	}
	public function introspection() {
		throw new Exception( get_called_class()."::introspection not implemented!" );
	}
	public function execute() {
		throw new Exception( get_called_class()."::execute not implemented!" );
	}
}
