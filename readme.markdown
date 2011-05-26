Kwerry
=========

Small introspection based ORM for PHP

Description
----------

I decided to write Kwerry to see if I could pare down the ORM concept to a simple, light-weight wrapper class that could intelligently build itself based on the only the tablename.

Example Usage
------------------

To setup a connection, simply supply the following datapoints:

`Kwerry::setConnection( "driver", "postgresql" );
Kwerry::setConnection( "host", "localhost" );
Kwerry::setConnection( "port", "5432" );
Kwerry::setConnection( "dbname", "bookstore" );
Kwerry::setConnection( "username", "mydbuser" );
Kwerry::setConnection( "password", "secretpw" );`

Then create the object by supplying a tablename:

`$books = Kwerry::model( "books" );`

At this point the object model is fully built and represents all records in the book table. All columns are properties of the object and the model object itself implements ArrayAccess, Iterator, and Countable:

`//Iterate through all books and print the title:
foreach( $books as $book ) {
	echo $book->title . "\n";
}`

There are many SQL-analog methods in the object that reference its columns, and each call returns the current object instance so they can be chained:

`//Print the title of all books published in 1990, sorted by Title:`
`foreach( $books->whereYearPublished( 1990 )->sortTitle() as $book ) {`
`	echo $book->title . "\n";`
`}`

You can also supply more arguments to have more fine grained control:

`//Print the title of all books published before or on 1990, sorted in reverse by Title:`
`foreach( $books->whereYearPublished( 1990, "<=" )->sortTitle( "DESC" ) as $book ) {`
`	echo $book->title . "\n";`
`}`

Referenced (and referencing) tables are accessible via methods as well:

`//Iterate through all authors and print their books' titles:`
`$authors = Kwerry::model( "authors" );`
`foreach( $authors as $author ) {`
`	echo $author->name . "\n";`
`	foreach( $author->getBooks()->sortTitle() as $book ) {`
`		echo "\t" . $book->title . "\n";`
`	}`
`}`
