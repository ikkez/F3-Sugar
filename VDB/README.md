## VDB
#### A variable DB extension for managing database structures.

This F3-Plugin provides you a SQL table schema builder by extending the SQL database plugin for the PHP Fat-Free Framework.
It might be useful for installation scripts.

This extension is still under development. Some more features are comming up the road. Currently MySQL, SQLite & PostgreSQL are supported and fully tested. Further support for SQL Server, Sybase, Access and IBM DB2 are already included. 

***

### Usage

After copying vdb.php into F3's lib Folder, create your DB handler using VDB Class as usual (same syntax as before, just change DB to VDB):
`$db = new VDB('mysql:host=localhost;port=3306;dbname='.$dbname,$user,$password);`

VDB Class prodives you the following simple methods for creating and dropping tables:

- 	`$db->getTables();`
	
	This will return a list of all tables available within the given dbname.
	
- 	`$db->create( $tableName, func() )`
	
	Creates a new table, containing an auto-incremented, primary-key field named 'id', which is required for Axon.
	You can pass through a function in the 2nd parameter, which contains more operations on the new table.
	
	in example:	
	```
	$db->create('products',function($table){		
		$table->addCol('name','TEXT8');
		$table->addCol('desc','TEXT16');
		$table->addCol('prize','FLOAT');	
	});
	```

- 	`$db->table( $tableName, func() )`

	Works the same way like ->create() but just loads an already existing table for altering operations, instead of creating a new one.	
	
	You can also add multiple functions to `create()` and `table()` methods to collect the results for i.e. debugging purpose:
	```
	$db->begin();
	list($result1, $result2) = $db->create('products',
		function($table){	
			return $table->addCol('name','TEXT8');
		},
		function($table){	
			return $table->getCols();
		}
	);
	if($result1 && $result2) $db->commit;
	else {
		$db->rollback();
		trigger_error('something went wrong :( ');
	}
	```
		

#### Methods used for operations within the handled functions are:

- 	`$table->addCol( $columnName, $dataType );`
	
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
	- DATE
	- DATETIME	
	
	usage:
	```
	$db->table('news',function($table){		
		$table->addCol('author','TEXT8');	
	});	
	```			

-	`$table->getCols( $types = false );`

	Returns an array of all column fields for a given table. When you set $types to TRUE, it will return an assoc array with fieldnames as keys and its type as value.

	usage:
	```
	$columns = $db->table('news',function($table){		
		return $table->getCols();	
	});	
	```	
	
	
-	`$table->dropCol( $columnName );`

	Removes a column from the given table.
	
	usage:
	```
	$db->table('news',function($table){		
		$table->dropCol('plugin-xy');	
	});	
	```	

-	`$table->renameCol( $currentColumnName, $newColumnName );`

	Renames a column from the given table.
	
	usage:
	```
	$db->table('news',function($table){		
		$table->renameCol('name','title');	
	});	
	```
	
-	`$table->renameTable( $newTableName );` 
	
	Renames a table.
	
	usage:
	```
	$db->table('news',function($table){		
		$table->renameTable('article');	
	});
	```	
	
-	`$table->dropTable();`

	Deletes a table.
	
	usage:
	```
	$db->table('news',function($table){		
		$table->dropTable();	
	});	
	```	
	You can also use a shorter syntax for it, like `$db->dropTable( $tableName );`



