## SQL Schema Builder
#### An F3 extension for managing database structures.

This F3-Plugin provides you a SQL table schema builder for the PHP Fat-Free Framework. It might be useful for installation scripts and dynamic applications, you might need have in cms enviroments.

Currently MySQL, SQLite & PostgreSQL are supported and fully tested. Further support for SQL Server, MSSQL/Sybase, Access and DB2 are already included. Handle with care and test your application. No warranty at all.

This Plugin is made for F3 version 3.x

***
### Installation

Just copy schema.php into F3's lib/db/sql Folder. Done.

### Usage

To work with the Schema builder, you need an active SQL Connection. Create one like this:
``` php
$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname='.$DBname, $user, $pass);
```
Now create a Schema object to work on. Inject the DB object into its constructor:
``` php
$builder = new \DB\SQL\Schema( $db );
```

The class prodives you the following simple methods for:

#### managing tables

-   **$builder->getTables();**
	
	This will return a list of all tables available within the given dbname.
	
- 	**$builder->createTable( $tableName );**
	
	Creates a new table, containing an auto-incremented, primary-key field named 'id', which is required for further SQL\Mapper usage. The SchemaBuilder selects and reminds that table name for more altering operations.
		
	example:	
	``` php
	$builder->createTable('products');
    $builder->addColumn('title',\DB\SQL\Schema::DT_TEXT8);
    $builder->addColumn('description',\DB\SQL\Schema::DT_TEXT16);
	```

- 	**$builder->alterTable( $tableName );**

	Just selects a table for altering operations on already existing tables. 
    
    example:	
	``` php
	$builder->alterTable('products');
    $builder->addColumn('prize',\DB\SQL\Schema::DT_DECIMAL);
    $builder->addColumn('stock',\DB\SQL\Schema::DT_INT);
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
	You can find these available mapped types as constants in \DB\SQL\Schema so far:
	
	<table>
		<tr>
			<th>Type</th>
			<th></th>
			<th>size</th>
			<th>bound</th>
		</tr>
		<tr>
			<td>DT_BOOL<br/>DT_BOOLEAN</td>
			<td>resolves in a numeric</td>
			<td>at least 1 byte</td>
			<td>0,1</td>
		</tr>
		<tr>
			<td>DT_TINYINT<br/>DT_INT8</td>
			<td>exact integer</td>
			<td>at least 1 byte</td>
			<td>lower: 0, upper; 255</td>
		</tr>
		<tr>
			<td>DT_INT<br/>DT_INT16</td>
			<td>exact integer</td>
			<td>at least 4 bytes</td>
			<td>±2,147,483,648</td>
		</tr>
		<tr>
			<td>DT_BIGINT<br/>DT_INT32</td>
			<td>exact integer</td>
			<td>at most 8 bytes</td>
			<td>±2^63</td>
		</tr>
		<tr>
			<td>DT_FLOAT</td>
			<td>approximate numeric</td>
			<td>4 bytes</td>
			<td>±1.79E + 308</td>
		</tr>
		<tr>
			<td>DT_DECIMAL<br/>DT_DOUBLE</td>
			<td>exact numeric</td>
			<td>at least 5 bytes</td>
			<td>±10^38+1</td>
		</tr>
		<tr>
			<td>DT_TEXT8<br/>DT_VARCHAR</td>
			<td>character string</td>
			<td>1 byte</td>
			<td>max length 255</td>
		</tr>
		<tr>
			<td>DT_TEXT<br/>DT_TEXT16</td>
			<td>character string</td>
			<td>2 bytes</td>
			<td>max length 2,147,483,647</td>
		</tr>
		<tr>
			<td>DT_TEXT32</td>
			<td>character string</td>
			<td>4 bytes</td>
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
    $builder->addColumn('author',\DB\SQL\Schema::DT_TEXT8);
    $builder->addColumn('bodytext',\DB\SQL\Schema::DT_TEXT16);

    // or even chained for one field
    $builder->alterTable('news')->addCol('author',\DB\SQL\Schema::DT_TEXT8);	
	```	
	
	If `$nullable` is false, the field is added as NOT NULL field, so it cannot contain a null value and therefore needs a default.
    
    example:
    ``` php    
    $builder->alterTable('news');
    $builder->addColumn('version',\DB\SQL\Schema::DT_INT,false,1);
    $builder->addColumn('title',\DB\SQL\Schema::DT_TEXT16,false,'new untitled news item');
	```	
    
    But you can set defaults to nullable fields as well.
    
    **CURRENT_TIMESTAMP as dynamic default value**
    If you like to add a timestamp of the current time to new inserted records, you can add a TIMESTAMP field with a special default value to achieve this.
    
    example:
    ``` php    
    $builder->alterTable('news')->addColumn('creation_date',\DB\SQL\Schema::DT_TIMESTAMP,false,\DB\SQL\Schema::DF_CURRENT_TIMESTAMP);
	```	
	
	Notice: constants with DF_ prefix are default values, DT_ is for DataTypes.
	

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

-	**$builder->setPKs( $pkeyArray );**

	You can define an Array of existing column names, that are going to be used as a composite primary key. If your table contains an auto incremented field (usually `id`), than it should always be the first element in $pkeyArray.
	
	usage:
	``` php
	$builder->createTable('news');
	$builder->addColumn('title', \DB\SQL\Schema::DT_TEXT8);
	$builder->addColumn('bodytext', \DB\SQL\Schema::DT_TEXT16);
	$builder->addColumn('version', \DB\SQL\Schema::DT_INT8, false, 1);
	$builder->setPKs(array('id', 'version'));
	```
	
	Now your primary key is build upon 2 columns, to use records like `id=1, version=1` and `id=1, version=2`.

