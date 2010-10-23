<?
require_once( "tbl_news.php" );
$obNewsTable = Kwerry::model( "tbl_news" );
$obNewsTable->whereWriter_ID( 1 )->sortInsertStamp();
echo $obNewsTable->getTitle() ."\n\n";
die();
if( true == false ) {
	foreach( $obNewsTable as $obBook ) {
		echo $obBook->getTitle()."\n";
		foreach( $obBook->getComments()->sortNumber() as $obChapter ) {
			echo $obChapter->getNumber() . ": " . $obChapter->getName() . "\n";
		}
	}
} else {
	echo $obNewsTable->getTitle() . "\n";
	foreach( $obNewsTable->getComments()->sortDate()->sortTime() as $obComment ) {
		echo $obChapter->getNumber() . ": " . $obChapter->getName() . "\n";
	}
} 
