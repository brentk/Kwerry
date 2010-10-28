<?
class tbl_news extends Kwerry {
	function __construct( $connectionName ) {
		parent::__construct( "tbl_news", $connectionName );
	}

	protected function buildDataModel() {
		$obTable = new Table();

		$obTable->setName( "tbl_news" );
		$obTable->setPK( "news_id" );
		
		$obTable->addColumn( "news_id" );
		$obTable->addColumn( "title" );
		$obTable->addColumn( "body" );
		$obTable->addColumn( "date" );
		$obTable->addColumn( "time" );
		$obTable->addColumn( "writer_id" );
		$obTable->addColumn( "sticky" );
		$obTable->addColumn( "active" );
		$obTable->addColumn( "insertstamp" );
		$obTable->addColumn( "updatestamp" );

		$obFK = new FK();
		$obFK->setName( "writer_id" );
		$obFK->setFKTable( "tbl_writer" );
		$obFK->setFKName( "writer_id" );
		$obTable->addFK( $obFK );

		$obRef = new Ref();
		$obRef->setName( "news_id" );
		$obRef->setRefTable( "tbl_comment" );
		$obRef->setRefName( "news_id" );
		$obTable->addRef( $obRef );

		$obRef = new Ref();
		$obRef->setName( "news_id" );
		$obRef->setRefTable( "tbl_news_tag" );
		$obRef->setRefName( "news_id" );
		$obTable->addRef( $obRef );

		$this->setTable( $obTable );
	}
}
