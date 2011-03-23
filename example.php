<?
function micro_time() {
    $temp = explode(" ", microtime());
    return bcadd($temp[0], $temp[1], 6);
}
$time_start = micro_time();

require_once( "Kwerry/Kwerry.php" );
$kwerry_opts[ "default" ][ "dbtype" ]	= "postgresql";
$kwerry_opts[ "default" ][ "host" ]	= "localhost";
$kwerry_opts[ "default" ][ "port" ]	= "5432";
$kwerry_opts[ "default" ][ "dbname" ]	= "bkellydb";
$kwerry_opts[ "default" ][ "username" ]	= "brentkelly";
$kwerry_opts[ "default" ][ "password" ]	= "p@\$\$w0rd";
//$kwerry_opts[ "default" ][ "prefix" ]	= "tbl_";
//$kwerry_opts[ "default" ][ "suffix" ]	= "";

try{

	/*
	$writer = Kwerry::model( "tbl_writer" );
	$news = $writer->whereName( "Brent" )->tbl_news->sortInsertstamp();

	foreach( $news as $post ) {
		echo $post->title . "\n";
		foreach( $post->tbl_comment->whereActive( 1 )->sortDate()->sortTime() as $comment ) {
			echo "\t".$comment->name . ": ";
			echo $comment->date."\n";
		}
	}
	*/

	

	$test = Kwerry::model( "tbl_test" );
	$test->whereTest_ID( 2 );
	echo $test->testname."\n";
	$test->testname = "testtwo";
	$test->update();
	echo $test->testname."\n";
	

} catch( Exception $e ) {
	echo "\n********* ERROR ***********\n";
	echo $e->getMessage()."\n\n";
	echo $e->getTraceAsString()."\n\n";
}


echo bcsub( micro_time(), $time_start, 6 )."\n";
