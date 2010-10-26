<?
require_once( "Kwerry.php" );
$obNewsTable = Kwerry::model( "tbl_news" );
$obNewsTable->whereWriter_ID( 1 )->sortInsertStamp();


if( true == false ) {
	foreach( $obNewsTable as $obBook ) {
		echo $obBook->getTitle()."\n";
		foreach( $obBook->getComments()->sortNumber() as $obChapter ) {
			echo $obChapter->getNumber() . ": " . $obChapter->getName() . "\n";
		}
	}
} else {
	foreach( $obNewsTable as $obNews ) {
		echo $obNews->getTitle() . ": ";

		//Working:
		echo count( $obNews->getTbl_Comment() ) ."\n";

		//Not Working:
		echo count( $obNews->getTbl_Comment()->whereActive( 1 ) ) ."\n";



///		foreach( $obNewsTable->getTbl_Comment()->sortDate()->sortTime() as $obComment ) {
//			echo $obChapter->getNumber() . ": " . $obChapter->getName() . "\n";
//		}
	}
} 
