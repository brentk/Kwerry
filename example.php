<?

/* goes in bootstrap */
$kwerry_opts[ "default" ][ "dbtype" ]	= "postgresql";
$kwerry_opts[ "default" ][ "host" ]	= "localhost";
$kwerry_opts[ "default" ][ "port" ]	= "5432";
$kwerry_opts[ "default" ][ "dbname" ]	= "bkellydb";
$kwerry_opts[ "default" ][ "username" ]	= "brentkelly";
$kwerry_opts[ "default" ][ "password" ]	= "5uck@$$";

require_once( "Kwerry.php" );

$obWriter = Kwerry::model( "tbl_writer" );
$obWriter->whereName( "Brent" );

$obNewsTable = Kwerry::model( "tbl_news" );
$obNewsTable->whereWriter_ID( $obWriter->getWriter_ID() )->sortInsertStamp();
//$obNewsTable->whereWriter_ID( 1 )->whereTitle( "Progress Waits for No Man" )->sortInsertStamp();

foreach( $obNewsTable as $obNews ) {
	echo $obNews->getTitle() . "\n";
	foreach( $obNews->getTbl_Comment()->whereActive( 1 )->sortDate()->sortTime() as $obComment ) {
		echo "\t".$obComment->getName() . "\n";
	}
}


$obTest = Kwerry::model( "tbl_test" );
