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
$kwerry_opts[ "default" ][ "password" ]	= "p@\$\$w0rd";
/* end bootstrap */

$writer = Kwerry::model( "tbl_writer" );
$news = $writer->whereName( "Brent" )->getTbl_News()->sortInsertstamp();

foreach( $news as $post ) {
	echo $post->getTitle() . "\n";
	foreach( $post->getTbl_Comment()->whereActive( 1 )->sortDate()->sortTime() as $comment ) {
		echo "\t".$comment->getName() . ": ";
		echo $comment->getDate()."\n";
	}
}

$obTest = Kwerry::model( "tbl_test" );

echo bcsub( micro_time(), $time_start, 6 )."\n";
