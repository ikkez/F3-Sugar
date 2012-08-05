## F3 vDB

This F3-Plugin provides you a variable SQL data access layer by extending the SQL database plugin for the PHP Fat-Free Framework.
It might be useful for installation scripts.

NOTICE: still beta version. currently only mysql & sqlite tested.

***

### Usage

After copying vdb.php into F3's lib Folder, create your DB using VDB Class:
`$db = new VDB("mysql:host=$host; dbname=$dbname".$port,$user,$password);`

- 	`$db->getTables();`
	
	This will return a list of all tables available within the given dbname.

-	`$db->getCols( $table, $types = false );`

	Returns an array of all column fields for a given table. When you set $types to TRUE, it will return an assoc array with fieldnames as keys and its type as value.
	
- 	`$db->createTable('article')`
	
	Creates a table named 'article', containing an auto-incremented field called 'id', which is required for Axon.

- 	`$db->addCol('article','headline','TEXT8');`
	
	Adds a further field called 'headline' to table 'article'. Third argument defines the datatype for the new field.
	Available mapped types so far, are:
	
	- BOOL
	- BOOLEAN
	- INT8
	- INT32
	- FLOAT
	- DOUBLE
	- TEXT8
	- TEXT16
	- TEXT32
	- SPECIAL_DATE
	- SPECIAL_DATETIME	
	
	
-	`$db->removeCol($table, $column);`

	Removes a column from a given table.
	
-	`$db->renameTable($old_table_name, $new_table_name);` 
	
	Renames a table.
	
-	`$db->dropTable($table);`

	Deletes a table.


### TODO:

-	check syntax for all different sql backends
-	renameColumn feature