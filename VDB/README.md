## VDB
#### A variable DB extension for managing database structures.

This F3-Plugin provides you a variable SQL data access layer by extending the SQL database plugin for the PHP Fat-Free Framework.
It might be useful for installation scripts.

NOTICE: still beta version. Currently mysql, sqlite & postgreSQL are supported.

***

### Usage

After copying vdb.php into F3's lib Folder, create your DB using VDB Class (same syntax as before, just change DB to VDB):
`$db = new VDB('mysql:host=localhost;port=3306;dbname='.$dbname,$user,$password);`

VDB Class prodives you the following methods:

- 	`$db->getTables();`
	
	This will return a list of all tables available within the given dbname.

-	`$db->getCols( $tableName, $types = false );`

	Returns an array of all column fields for a given table. When you set $types to TRUE, it will return an assoc array with fieldnames as keys and its type as value.
	
- 	`$db->createTable( $tableName )`
	
	Creates a new table, containing an auto-incremented, primary-key field named 'id', which is required for Axon.

- 	`$db->addCol( $tableName, $columnName, $dataType );`
	
	Adds a further column field to a table. The $dataType argument defines the type for the new field.
	Available mapped types so far:
	
	- BOOLEAN
	- INT8
	- INT16
	- INT32
	- FLOAT
	- DOUBLE
	- TEXT8
	- TEXT16
	- TEXT32
	- SPECIAL_DATE
	- SPECIAL_DATETIME	
	
	
-	`$db->removeCol( $tableName, $columnName );`

	Removes a column from the given table.
	
-	`$db->renameCol( $tableName, $currentColumnName, $newColumnName );`

	Renames a column from the given table.	
	
-	`$db->renameTable( $oldTableName, $newTableName );` 
	
	Renames a table.
	
-	`$db->dropTable( $tableName );`

	Deletes a table.


### TODO:

-	check syntax for all different sql backends