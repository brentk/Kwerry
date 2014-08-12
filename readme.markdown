Kwerry
=========

Small introspection based ORM for PHP

Description
-----------

Kwerry is a pared down, light-weight ORM that can intelligently build itself based only on the tablename.  This is accomplished by reading database meta data to discover data types and primary keys and reading foreign key constraints to build inter-table relationships.

Creating a Connection
---------------------

To setup a connection, simply supply the following datapoints:

```php
Kwerry::setConnection( "driver", "postgresql" );
Kwerry::setConnection( "host", "localhost" );
Kwerry::setConnection( "port", "5432" );
Kwerry::setConnection( "dbname", "bookstore" );
Kwerry::setConnection( "username", "mydbuser" );
Kwerry::setConnection( "password", "secretpw" );
```

Retrieving Data
---------------

To create a model object all you need to do is supply a tablename:

```php
$books = Kwerry::model( "books" );
```
and Kwerry does the rest by examining the table and building the object to reflect it.

At this point the object model is fully built and represents all records in the book table. All columns are properties of the object and the model object itself can also be treated as a simple array:

```php
//Display the number of book records:
echo count( $books ) . " books found:\n";

//display the title of the first/current record:
echo $books->title . "\n";

//iterate through all books, outputing the title:
foreach( $books as $book ) {
	echo $book->title . "\n";
}

//output a specific record's title:
echo $books[52]->title . "\n";
```



Filtering Results
-----------------

Each column can be filtered by with dynamic methods begining with "->where" followed by a column name:

```php
//Output a specific book title by ID
echo Kwerry::model( "books" )->whereID( 5 )->title . "\n";
```

By default Kwerry uses the equals operator (=), but you can specify whatever you'd like as a second argument:

```php
$books = Kwerry::model( "books" );

//Get all books published after 1994:
foreach( $books->whereYearPublished( 1994, ">=" ) as $book ) {
	echo $book->title . "\n";
}
```

You can continue to add filters to the same object:

```php
$books = Kwerry::model( "books" );

if( $onlyNewBooks ) {
	$books->whereYearPublished( 2012, ">" );
}

if( $onlyShortBooks ) {
	$books->wherePages( 100, "<" );
}
//and so on...
```

You can also chain them:

```php
$books = Kwerry::model( "books" );

//Get all books published in 2011 with "PHP" in the title:
$myBooks = $books->whereYearPublished( 2011 )->whereTitle( "%PHP%", "LIKE" );
```

There where methods are case insensitive and camel case is only used here to increase readability.

\*\*Please note that currently the records must match **all** where criteria. Specifying OR, as well as nesting filters in parenthesis is being looked into.

Sorting Records
---------------

The sorting methods work a lot like the where methods. They being with the word "sort" followed by a column name. Just like where, multiple can be added and they can be chained.

All sorting defaults to ascending unless specified with the "DESC" argument.

```php
$books = Kwerry::model( "books" );

//Get all books published after 1990, sorted ascending by year, descending by title:
$myBooks = $books->whereYearPublished( 1990, ">=" )->sortYearPublished()->sortTitle( "DESC" );
```

Foreign Keys and Table Relationships
------------------------------------

All referenced (and referencing) tables are accessible from the object as well, and these relationships are automatically discovered by Kwerry on object creation.  The referenced table is accessible via its name as a propery of the model object:

```php
$books = Kwerry:model( "books" );

//Output each author's name by book:
foreach( $books->sortTitle() as $book ) {
	echo $book->title . ": ". $book->author->name . "\n";
}
```

You can traverse these relationships either way; many to one (like above), or one to many:

```php
$authors = Kwerry::model( "authors" );

//Iterate through each author:
foreach( $authors as $author ) {

	//Output their name:
	echo $author->name . "\n";
	
	//Iterate through all of their books alphabetically and output the title:
	foreach( $author->books->sortTitle() as $book ) {
		echo "\t" . $book->title . "\n";
	}
}
```

Limit and Offset
----------------

A limit and/or offset can be chained onto any object to restrict which records are returned:

```php
$authors = Kwerry::model( "authors" );

foreach( $authors->whereName( "John Smith" )->sortName()->limit( 2 )->offset( 5 ) as $author ) {
	echo $author->name . "\n";
}
```
Data Manipulation
-----------------

Update, insert, and delete are also available, and are implemented via a pattern closely resembling active record:

```php
//Insert a new book:
$book = Kwerry::model( "books" )->addnew();
$book->title         = "The Very Hungry Caterpillar";
$book->author_id     = Kwerry::model( "author" )->whereName( "Eric Carle" )->id;
$book->yearpublished = 1970;
$book->pages         = 25;
$book->save();

//Update the record with some corrected data:
$book->yearpublished = 1969;
$book->pages         = 22;
$book->save();

//Delete the book
$badBook = Kwerry::model( "books" )->whereID( $id );
$badBook->delete();
```
\*\*Note: Update and delete only operate on the current record. If your object currently has filtered set of data (or all recordsm, unfiltered) you will need to iterate through them, updating or deleting as you go.  Adding methods of executing these operations on all data in the object are currently being looked into.

Object Hydration
----------------

In cases where you need to use more complex logic than Kwerry exposes, you can write your own queries and use their results to hydrate a Kwerry object:

```php
//Output all books by people named "John" that were published when the author was younger than 30:
$sql = "SELECT books.*
	FROM books
	INNER JOIN authors ON books.author_id = authors.id
	WHERE lower( authors.name ) LIKE ?
	AND (authors.dob - books.yearpublished) < ?";

$books = Kwerry::model( "books" )->hydrate( $sql, array( "%john%", 30 ) );

foreach( $books as $book ) {
	echo $book->title . "\n";
}
```
The only requirement when using hydrate() is you must return **all** columns from **only** the specified table.

SQL Passthrough
---------------

We've all been there. Maybe the abstraction just doesn't have a way to query what you need. Or, maybe you need to pull 20,000 records and it's just not feasible to use an ORM. Or maybe you want to run a gigantic, dangerous update statement. Whatever the reason, it's ok. Just feed it raw SQL:

```php
//Get a count of all books in 2010, by publisher/author combination
$sql = "SELECT
	count( books.* ) as book_count,
	authors.name, publishers.name
	FROM books
	INNER JOIN authors ON books.author_id = authors.id
	INNER JOIN publishers ON books.publisher_id = publishers.id
	WHERE book.yearPublished = ?
	GROUP BY authors.name, publishers.name";
	
$books = Kwerry::runSQL( $sql, array( 2010 ) );
```

This will return a raw multi-dimensional array as a recordset, or a success boolean if running a update/insert/delete statement.
