## SchemaBuilder
#### An F3 extension for managing database structures.

This F3-Plugin provides you a SQL table schema builder for the PHP Fat-Free Framework. It might be useful for installation scripts and dynamic applications, you might need have in cms enviroments.

Currently MySQL, SQLite & PostgreSQL are supported and fully tested. Further support for SQL Server, MSSQL/Sybase, Access and DB2 are already included. Handle with care and test your application. No warranty at all.

This Plugin is made for F3 version 3.x

***
### Installation

Just copy schemabuilder.php into F3's lib/db/sql Folder. Done.

### Usage

To work with the SchemaBuilder, you need an active SQL Connection. Create one like this:
``` php
$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname='.$DBname, $user, $pass);
```
Now create a SchemaBuilder object to work on. Inject the DB object into its constructor:
``` php
$builder = new \DB\SQL\SchemaBuilder( $db );
```

The class prodives you the following simple methods for

#### managing tables

-   **$builder->getTables();**
	
	This will return a list of all tables available within the given dbname.
	
- 	**$builder->createTable( $tableName );**
	
	Creates a new table, containing an auto-incremented, primary-key field named 'id', which is required for further SQL\Mapper usage. The SchemaBuilder selects and reminds that table name for more altering operations.
		
	example:	
	``` php
	$builder->createTable('products');
    $builder->addColumn('title',DT:TEXT8);
    $builder->addColumn('description',DT:TEXT16);
	```

- 	**$builder->alterTable( $tableName );**

	Just selects a table for altering operations on already existing tables. 
    
    example:	
	``` php
	$builder->alterTable('products');
    $builder->addColumn('prize',DT:DECIMAL);
    $builder->addColumn('stock',DT:INT);
	```

-   **$builder->renameTable( $newTableName );**
	
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
	You can find these available mapped types as constants in DT class so far:
	
	<table>
		<tr>
			<th>Type</th>
			<th></th>
			<th>size</th>
			<th>bound</th>
		</tr>
		<tr>
			<td>DT::BOOL<br/>DT::BOOLEAN</td>
			<td>resolves in a numeric</td>
			<td>at least 1 byte</td>
			<td>0,1</td>
		</tr>
		<tr>
			<td>DT::TINYINT<br/>DT::INT8</td>
			<td></td>
			<td>at least 1 byte</td>
			<td>lower: 0, upper; 255</td>
		</tr>
		<tr>
			<td>DT::INT<br/>DT::INT16</td>
			<td></td>
			<td>at least 4 bytes</td>
			<td>±2,147,483,648</td>
		</tr>
		<tr>
			<td>DT::BIGINT<br/>DT::INT32</td>
			<td></td>
			<td>at most 8 bytes</td>
			<td>±2^63</td>
		</tr>
		<tr>
			<td>DT::FLOAT</td>
			<td>approximate numeric</td>
			<td>4 bytes</td>
			<td>±1.79E + 308</td>
		</tr>
		<tr>
			<td>DT::DECIMAL<br/>DT::DOUBLE</td>
			<td>exact numeric</td>
			<td>at least 5 bytes</td>
			<td>±10^38+1</td>
		</tr>
		<tr>
			<td>DT::TEXT8<br/>DT::VARCHAR</td>
			<td>character string</td>
			<td>1 byte</td>
			<td>max length 255</td>
		</tr>
		<tr>
			<td>DT::TEXT<br/>DT::TEXT16</td>
			<td>character string</td>
			<td>2 bytes</td>
			<td>max length 2,147,483,647</td>
		</tr>
		<tr>
			<td>DT::TEXT32</td>
			<td>character string</td>
			<td>4 bytes</td>
			<td>max length 4,294,967,295</td>
		</tr>
		<tr>
			<td>DT::DATE</td>
			<td></td>
			<td>3 bytes</td>
			<td></td>
		</tr>
		<tr>
			<td>DT::DATETIME</td>
			<td></td>
			<td>8 bytes</td>
			<td></td>
		</tr>
	</table>	
	
	usage:
	``` php    
    $builder->alterTable('news');
    $builder->addColumn('author',DT::TEXT8);
    $builder->addColumn('bodytext',DT::TEXT16);

    // or even chained for one field
    $builder->alterTable('news')->addCol('author',DT:TEXT8);	
	```	
	
	If `$nullable` is false, the field is added as NOT NULL field, so i cannot contain null value and therefore needs a default
    
    example:
    ``` php    
    $builder->alterTable('news');
    $builder->addColumn('version',DT::INT,false,1);
    $builder->addColumn('title',DT::TEXT16,false,'new untitled news item');
	```	
    
    But you can set defaults to nullable fields as well.

-	**$builder->getCols( $types [false] );**

	Returns an array of all column fields for a given table. When you set $types to TRUE, it will return an assoc array with fieldnames as keys and its type as value. (It's quiet the same as $db->schema, but includes some workarounds for internal default value handling)

	usage:
	``` php
	$columns = $builder->table('news')->getCols();
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

