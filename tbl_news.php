<?
class tbl_news extends Kwerry {
	function __construct( $connectionName ) {
		parent::__construct( "tbl_news", $connectionName );
	}

	protected function buildDataModel() {
		$table = new Table();

		$table->setName( "tbl_news" );
		$table->setPK( "news_id" );

		$column = new Column();
		$column->setName( "news_id" );
		$column->setDataType( DATA_TYPE_INTEGER );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "title" );
		$column->setDataType( DATA_TYPE_STRING );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "body" );
		$column->setDataType( DATA_TYPE_STRING );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "date" );
		$column->setDataType( DATA_TYPE_DATE );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "time" );
		$column->setDataType( DATA_TYPE_TIME );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "writer_id" );
		$column->setDataType( DATA_TYPE_INTEGER );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "sticky" );
		$column->setDataType( DATA_TYPE_INTEGER );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "active" );
		$column->setDataType( DATA_TYPE_INTEGER );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "insertstamp" );
		$column->setDataType( DATA_TYPE_STAMP );
		$table->addColumn( $column );

		$column = new Column();
		$column->setName( "updatestamp" );
		$column->setDataType( DATA_TYPE_STAMP );
		$table->addColumn( $column );

		$fk = new FK();
		$fk->setName( "writer_id" );
		$fk->setFKTable( "tbl_writer" );
		$fk->setFKName( "writer_id" );
		$table->addFK( $fk );

		$ref = new Ref();
		$ref->setName( "news_id" );
		$ref->setRefTable( "tbl_comment" );
		$ref->setRefName( "news_id" );
		$table->addRef( $ref );

		$ref = new Ref();
		$ref->setName( "news_id" );
		$ref->setRefTable( "tbl_news_tag" );
		$ref->setRefName( "news_id" );
		$table->addRef( $ref );

		$this->setTable( $table );
	}
}
