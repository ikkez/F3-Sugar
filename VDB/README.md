## F3 vDB

This F3-Plugin provides you a variable SQL data access layer by extending the SQL database plugin for the PHP Fat-Free Framework.
It might be useful for installation scripts.

NOTICE: still beta version.

***

### Usage

After copying vdb.php into F3's lib Folder, create your DB using VDB Class:
`$db = new VDB("mysql:host=$host; dbname=$dbname".$port,$user,$password);`

- 	`$db->getTables();`
	
	This will return a list of all tables available within the given dbname.

- 	`$db->createTable('article')`
	
	Creates a table named 'article', containing an auto-incremented field called 'id', which is required for Axon.

- 	`$db->addCol('article','headline','TEXT8');`
	
	Adds a further field called 'headline' to table 'article'. Third argument defines the datatype for the new field.
	See inside $dataTypes array for a full list of mapped types so far.
	


### TODO:

-	add remove Column feature
-	add drop talbe feature
-	check syntax for all different sql backends