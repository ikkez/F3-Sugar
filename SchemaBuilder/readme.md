# SQL Schema Builder
### An extension for creating and managing SQL database tables.

This Plugin provides a SQL table schema builder for the **PHP Fat-Free Framework**. It might be useful for installation scripts, dynamic applications or CMS enviroments.

Currently MySQL, SQLite & PostgreSQL are supported and fully tested. Further support for SQL Server, MSSQL/Sybase, Access and DB2 are already included, but experimental. Handle with care and test your application. No warranty at all.

This Plugin is made for F3 version 3.x

***
## Installation

Just copy schema.php into F3's `lib/db/sql` Folder. Done.

## Quick Start

To work with the Schema builder, you need an active SQL Connection. Create one like this:
``` php
$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname='.$DBname, $user, $pass);
```
Now create a Schema object to work on. Inject the DB object into its constructor:
``` php
$schema = new \DB\SQL\Schema( $db );
```

### Create tables

Creating new tables is super easy. Let's have a look at this example:
``` php
$table = $schema->createTable('products');
$table->addColumn('title')->type($schema::DT_VARCHAR128);
$table->addColumn('description')->type($schema::DT_TEXT);
$table->build();
```

The `createTable()` method returns a new table object (*instance of TableCreator*) for creation purpose. You may add new columns, indexes and change the primary key with it. New tables will always contain an auto-incremented, primary-key field named `id`, which is required for further SQL\Mapper usage. All actions on a table object that affects its schema, are collected first and needs an additional `build()` command to be executed to really take effect on the database. If you're unsure of the result, you can run a simulation of that build method and have a look at the generated queries, the Schema Builder would have executed, with the following call:
``` php
$generated_queries = $table->build(false);
print_r($generated_queries);
```

#### Add new columns

Using the `$table->addColumn()` method will create a new Column object and adds it to the table object. We can use fluent calls for configuring these columns. 
``` php
$table->addColumn('deleted')->type($schema::DT_BOOL)->nullable(false)->defaults(0)->after('id');
```
Here is a list of possible configuration methods:

* **->type( string $datatype, [ bool $force = false] )**

  Set datatype of this column. Usually a constant of type \DB\SQL\Schema::DT_{datatype}. The `$force` argument will disable the datatype check with the included mappings and uses your raw string as type definition.

* **->nullable( bool $state )**	
  
  Makes this column as nullable or not. Default is true.
  
* **->defaults( mixed $value )**

  Adds a default value for records. 

* **->after( string $name )**

  Trys to place the new column behind an existing one. *(only SQLite and MySQL)*
  
* **->index([ bool $unique = false ])**

  Add an index for that field. `$unique` makes it a UNIQUE INDEX.


### Alter tables

Altering existing tables is quite similar to creating them, but offers a bunch more possibilities. A basic example:
``` php
$table = $schema->alterTable('products');
$table->addColumn('prize')->type($schema::DT_DECIMAL);
$table->addColumn('stock')->type($schema::DT_INT);
$table->removeColumn('foo_bar');
$table->renameColumn('title','name');
$table->build();
```

As you can see, `alterTable()` returns a new table object (*instance of TableModifier*) for altering purpose, which provides some more actions like removing or renaming columns.


---

---

to be continued

---

The Schema class prodives you the following simple methods for:

#### managing databases

-   **$schema->getDatabases();**
	
	Returns a list of all databases available (except SQLite). Can be useful for installation purpose, when you want the user to select a database to work on. Therefor just create your DB connection without selecting a database like: 
	```
	$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname=', $user, $password);
	```
	Some DB engine default setups also grants simple read operations, without setting a user / password.


#### managing tables
	
-   **$schema->getTables();**
	
	This will return a list of all tables available within the current database.

- 	**$schema->createTable( $tableName );**
	
	Returns a new table object for creation purpose. New tables will always contain an auto-incremented, primary-key field named 'id', which is required for further SQL\Mapper usage. You can add columns, indexes and change the primary key on this object.
		
	example:	
	``` php
	$table = $schema->createTable('products');
    $table->addColumn('title')->type($schema::DT_VARCHAR128);
    $table->addColumn('description')->type($schema::DT_TEXT);
    $table->build();
	```

- 	**$schema->alterTable( $tableName );**

	Returns a table object for altering operations on already existing tables. 
    
    example:	
	``` php
	$table = $schema->alterTable('products');
    $table->addColumn('prize')->type($schema::DT_DECIMAL);
    $table->addColumn('stock')->type($schema::DT_INT);
    $table->build();
	```

-   **$schema->renameTable( $currentTableName, $newTableName );**
	
	Renames a table.
	
	usage:
	``` php
	$builder->alterTable('news');
    $builder->renameTable('article');
    // or even shorter in one line:
    $builder->alterTable('news')->renameTable('article');
	});
	```	
    The internal pointer moves to the new table name after a rename operation, so adding further columns now goes directly to table `article`
	
-	**$builder->dropTable( $tableName [null] );**

	Deletes a table.
	
	usage:
	``` php
	$builder->dropTable('news');
    // this is also possible
    $builder->alterTable('news')->dropTable();	
	```	
	You can also use a shorter syntax for it, like `$db->dropTable( $tableName );`
    	

#### managing columns

- 	**$table->addColumn( $columnName, $dataType, $nullable [true], $default [false] );**
	
	Adds a further column field to the selected table. The $dataType argument defines the type for the new field.
	You can find these available mapped types as constants in \DB\SQL\Schema so far:
	
	<table>
		<tr>
			<th>Type</th>
			<th>Description</th>
			<th>Storage size</th>
			<th>Save Range</th>
		</tr>
		<tr>
			<td>DT_BOOL<br/>DT_BOOLEAN</td>
			<td>resolves in a numeric</td>
			<td>at least 1 byte</td>
			<td>0,1</td>
		</tr>
		<tr>
			<td>DT_INT1<br/>DT_TINYINT</td>
			<td>exact integer</td>
			<td>at least 1 byte</td>
			<td>lower: 0, upper; 255</td>
		</tr>
		<tr>
			<td>DT_INT2<br/>DT_SMALLINT</td>
			<td>exact integer</td>
			<td>at least 2 bytes</td>
			<td>�32,768</td>
		</tr>
		<tr>
			<td>DT_INT4<br/>DT_INT</td>
			<td>exact integer</td>
			<td>4 bytes</td>
			<td>�2,147,483,648</td>
		</tr>
		<tr>
			<td>DT_INT8<br/>DT_BIGINT</td>
			<td>exact integer</td>
			<td>at most 8 bytes</td>
			<td>�2^63</td>
		</tr>
		<tr>
			<td>DT_FLOAT</td>
			<td>approximate numeric</td>
			<td>4 bytes</td>
			<td>�1.79E + 308</td>
		</tr>
		<tr>
			<td>DT_DECIMAL<br/>DT_DOUBLE</td>
			<td>exact numeric</td>
			<td>at least 5 bytes</td>
			<td>�10^38+1</td>
		</tr>
		<tr>
			<td>DT_VARCHAR128</td>
			<td>character string</td>
			<td>128 bytes</td>
			<td>128 chars</td>
		</tr>
		<tr>
			<td>DT_VARCHAR256</td>
			<td>character string</td>
			<td>256 bytes</td>
			<td>256 chars</td>
		</tr>
		<tr>
			<td>DT_VARCHAR512</td>
			<td>character string</td>
			<td>512 bytes</td>
			<td>512 chars</td>
		</tr>
		<tr>
			<td>DT_TEXT</td>
			<td>character string</td>
			<td></td>
			<td>max length 2,147,483,647</td>
		</tr>
		<tr>
			<td>DT_LONGTEXT</td>
			<td>character string</td>
			<td></td>
			<td>max length 4,294,967,295</td>
		</tr>
		<tr>
			<td>DT_DATE</td>
			<td>Y-m-d</td>
			<td>3 bytes</td>
			<td></td>
		</tr>
		<tr>
			<td>DT_DATETIME</td>
			<td>Y-m-d H:i:s</td>
			<td>8 bytes</td>
			<td></td>
		</tr>
		<tr>
			<td>DT_TIMESTAMP</td>
			<td>Y-m-d H:i:s</td>
			<td>8 bytes</td>
			<td></td>
		</tr>
		<tr>
			<td>DT_BLOB<br/>DT_BINARY</td>
			<td>bytes</td>
			<td></td>
			<td></td>
		</tr>
	</table>	
	
	usage:
	``` php    
    $builder->alterTable('news');
    $builder->addColumn('author', \DB\SQL\Schema::DT_VARCHAR128);
    $builder->addColumn('bodytext', \DB\SQL\Schema::DT_TEXT);

    // or even chained for one field
    $builder->alterTable('news')->addCol('author', \DB\SQL\Schema::DT_VARCHAR128);
     
    $builder->addColumn('image', \DB\SQL\Schema::DT_VARCHAR128, true, NULL); // use NULL as a default value 	
    ```	
	
	If `$nullable` is false, the field is added as NOT NULL field, so it cannot contain a null value and therefore needs a default.
    
    example:
    ``` php    
    $builder->alterTable('news');
    $builder->addColumn('version', \DB\SQL\Schema::DT_INT, false, 1);
    $builder->addColumn('title', \DB\SQL\Schema::DT_TEXT, false, 'new untitled news item');
	```	
    
    But you can set defaults to nullable fields as well.
    
    **CURRENT_TIMESTAMP as dynamic default value**
    
    If you like to add a timestamp of the current time to new inserted records, you can add a TIMESTAMP field with a special default value to achieve this.
    
    example:
    ``` php    
    $builder->alterTable('news');
    $builder->addColumn('creation_date',\DB\SQL\Schema::DT_TIMESTAMP,false,\DB\SQL\Schema::DF_CURRENT_TIMESTAMP);
	```	
	
	Notice: constants with DF_ prefix are default values, DT_ is for DataTypes.
	

-	**$builder->getCols( $types [false] );**

	Returns an array of all column fields for a given table. When you set $types to TRUE, it will return an assoc array with fieldnames as keys and its type as value. (It's quiet the same as $db->schema, but includes some workarounds for internal default value handling)

	usage:
	``` php
	$columns = $builder->alterTable('news')->getCols();
	```	
	
-	**$builder->dropColumn( $columnName );**

	Removes a column from the given table.
	
	usage:
	``` php
	$builder->alterTable('plugins')->dropColumn('plugin-xy');		
	```	

-	**$builder->renameColumn( $currentColumnName, $newColumnName );**

	Renames a column from the given table.
	
	usage:
	``` php
	$builder->alterTable('news')->renameColumn('name','title');
	```

-	**$builder->setPKs( $pkeyArray );**

	You can define an Array of existing column names, that are going to be used as a composite primary key. If your table contains an auto incremented field (usually `id`), than it should always be the first element in $pkeyArray.
	
	usage:
	``` php
	$builder->createTable('news');
	$builder->addColumn('title', \DB\SQL\Schema::DT_VARCHAR128);
	$builder->addColumn('bodytext', \DB\SQL\Schema::DT_TEXT);
	$builder->addColumn('version', \DB\SQL\Schema::DT_INT8, false, 1);
	$builder->setPKs(array('id', 'version'));
	```
	
	Now your primary key is build upon 2 columns, to use records like `id=1, version=1` and `id=1, version=2`.

