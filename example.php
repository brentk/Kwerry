<?
function micro_time() {
    $temp = explode(" ", microtime());
    return bcadd($temp[0], $temp[1], 6);
}
$time_start = micro_time();







/* start bootstrap */
require_once( "Kwerry.php" );
$kwerry_opts[ "default" ][ "dbtype" ]	= "postgresql";
$kwerry_opts[ "default" ][ "host" ]	= "localhost";
$kwerry_opts[ "default" ][ "port" ]	= "5432";
$kwerry_opts[ "default" ][ "dbname" ]	= "bkellydb";
$kwerry_opts[ "default" ][ "username" ]	= "brentkelly";
$kwerry_opts[ "default" ][ "password" ]	= "5uck@$$";
/* end bootstrap */



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





echo bcsub( micro_time(), $time_start, 6 )."\n";
