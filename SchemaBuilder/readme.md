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

### Create Tables

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

#### Add Columns

Using the `$table->addColumn()` method will create a new Column object and adds it to the table object. We can use fluent calls for configuring these columns. 
``` php
$table->addColumn('deleted')->type($schema::DT_BOOL)->nullable(false)->defaults(0)->after('id');
```
Here is a list of possible configuration methods:

* **->type( $datatype)**

  Set datatype of this column. Usually a constant of type \DB\SQL\Schema::DT_{datatype}. Have a look at the Column Class API for more details about datatypes.

* **->nullable( $state )**
  
  Set this column as NULL or NOT NULL. Default is true / nullable.
  
* **->defaults( $value )**

  Adds a default value for records. 

* **->after( $name )**

  Trys to place the new column behind an existing one.
  
* **->index([ bool $unique = false ])**

  Add an index for that field. `$unique` makes it a UNIQUE INDEX.


### Alter Tables

Altering existing tables is quite similar to creating them, but offers a bunch more possibilities. A basic example:
``` php
$table = $schema->alterTable('products');
$table->addColumn('prize')->type($schema::DT_DECIMAL);
$table->addColumn('stock')->type($schema::DT_INT);
$table->dropColumn('foo_bar');
$table->renameColumn('title','name');
$table->build();
```

As you can see, `$schema->alterTable()` returns a new table object (*instance of TableModifier*) for altering purpose, which provides all methods of the TableCreator plus some more actions like removing or renaming columns. Here is a list of method you can use:

-   **renameColumn( string $name, string $new_name );**
-   **updateColumn( string $name, string $datatype );**
-   **dropColumn( string $name );**
-   **addIndex( string | array $columns, [ bool $unique = false ]);**
-   **dropIndex( string | array $columns );**
-   **listIndex();**
-   **getCols([ bool $types = false ]);**


The SchemaBuilder will quote all your table and column identifiers and should be resistent against preserved word errors.

---

## API Usage


### Schema Class

The Schema class prodives you the following simple methods for:

#### managing databases

-   **$schema->getDatabases();**
	
	Returns an array of all databases available (*except for SQLite*). Can be useful for installation purpose, when you want the user to select a database to work on. Therefor just create your DB connection without selecting a database like:
	```
	$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname=', $user, $password);
	```
	Some DB engine default setups also grants simple read operations, without setting a user / password.


#### managing tables
	
-   **$schema->getTables();**
	
	Returns an array of all tables available within the current database.

- 	**$schema->createTable( $tableName );**
	
	Returns a new table object for creation purpose.

- 	**$schema->alterTable( $tableName );**

	Returns a table object for altering operations on already existing tables.

-   **$schema->renameTable( string $name, string $new_name, [ bool $exec = true ]);**
	
	Renames a table. If you set `$exec` to `FALSE`, it will return the generated query instead of executing it.
	You can also use a short-cut on an altering table object, like `$table->rename( string $new_name, [ bool $exec = true ]);`.
	
-	**$schema->dropTable( $name, [ bool $exec = true ]);**

	Deletes a table. Set `$exec` to `FALSE` will return the generated query instead of executing it.
	You can also use a short-cut on an altering table object, like `$talbe->drop([ bool $exec = true ]);`.



### TableCreator Class

This class is ment for creating new tables. It can be created by using `$schema->createTable($name)`.

-   **$table->addColumn($key,$args = null); Column**

    This creates a new Column object and saves a reference to it. You can configure the Column for your needs using further fluent calls, setting its public parameters or directly via config array like this:
    ``` php
    $table->addColumn('title',array(
        'type'=>\DB\SQL\Schema::DT_INT4,
        'nullable'=>false,
        'default'=>'untitled new entry',
        'after'=>'id',
        'index'=>true,
        'unique'=>true,
    ));
    ```

-   **$table->addIndex($columns, $unique = FALSE);**

    You can add an index to a column by configuring the Column object while adding the new column, or like this:
    ``` php
    $table->addIndex('name');
    ```
    For adding an combined index on multiple columns, just use an array as parameter:
    ``` php
    $table->addIndex(array('name','email'));
    ```

-   **$table->primary($pkeys);**

    If you like to change the default `id` named primary-key right on the creation of a new table, you can use this one:
    ``` php
    $table->primary('uid');
    ```
    This will rename the `id` field to `uid`. If you like to set a primary key on multiple columns (*a composite key*), use an array:
    ``` php
    $table->primary(array('uid','version'));
    ```
    The first element of this pkey array will always be treated as an auto-incremented field.

    example:
    ``` php
    $table = $schema->createTable('news');
    $table->addColumn('title')->type($schema::DT_VARCHAR128);
    $table->addColumn('bodytext')->type($schema::DT_TEXT);
    $table->addColumn('version')->type($schema::DT_INT8)->nullable(false)->defaults(1);
    $table->primary(array('id', 'version'));
    $table->build();
    ```

    Now your primary key is build upon 2 columns, to use records like `id=1, version=1` and `id=1, version=2`.

-   **$table->build([ bool $exec = true ]);**

    This will start the table generation process and executes all queries if `$exec` is `TRUE`, otherwise it will just return all queries as array.

### TableModifier Class

This class is ment for creating new tables. It can be created by using `$schema->alterTable($name)`.

-   **$table->addColumn($key,$args = null); Column**

    Adds a new column.

-   **$table->renameColumn( string $name, string $new_name );**

    This is used to rename an existing column.

-   **$table->updateColumn( string $name, string datatype, [ bool $force = false ]);**

    This is used to modify / update the column's datatype.

-   **$table->dropColumn( string $name );**

    Trys to removes a column from the table, if it exists.

-   **$table->addIndex( string | array $columns, [ bool $unique = false ]);**

    Creates a index or unique index for one or multiple columns on the table.

-   **$table->dropIndex( string | array $columns );**

    Drops an index.

-   **$table->listIndex();**

    Returns an associative array with index name as key and `array('unique'=>$value)` as value.

-   **$table->primary( string | array $pkeys);**

    Creates a new primary or compositve key on the table.

-   **$table->getCols([ bool $types = false ]);**

    Returns an array of existing table columns. If `$types` is set to `TRUE`, it will return an associative array with column name as key and the schema array as value.

-   **$table->build([ bool $exec = true ]);**

    This generates the queries needed for the table alteration and executes them when `$exec` is true, otherwise it returns them as array.

-   **$table->rename( string $new_name, [ bool $exec = true ]);**

    This will instantly rename the table. Notice: Instead of being executed on calling `build()` the execution is controlled by `$exec`.

-   **$table->drop([ bool $exec = true ]);**

    This will instantly drop the table. Notice: Instead of being executed on calling `build()` the execution is controlled by `$exec`.


### Column Class

The method `$table->addColumn($columnName);` adds a further column field to the selected table and creates and returns a new Column object, that can be configured in different ways, before finally building it.


* **->type( string $datatype, [ bool $force = false ])**
    
    Set datatype of this column. The `$force` argument will disable the datatype check with the included mappings and uses your raw string as type definition.

    You can use these available mapped types as constants in \DB\SQL\Schema:

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
            <td>±32,768</td>
        </tr>
        <tr>
            <td>DT_INT4<br/>DT_INT</td>
            <td>exact integer</td>
            <td>4 bytes</td>
            <td>±2,147,483,648</td>
        </tr>
        <tr>
            <td>DT_INT8<br/>DT_BIGINT</td>
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
    $table = $schema->alterTable('news');
    $table->addColumn('author')->type(\DB\SQL\Schema::DT_VARCHAR128);
    // or
    $table->addColumn('bodytext')->type($schema::DT_TEXT);
    // or the shorthand
    $table->addColumn('bodytext')->type_text();

    // save changes to database
    $table->build();
    ```

    there are also a bunch of shorthand methods available, you can use instead of `type()`:

    -   **type_tinyint()**
    -   **type_smallint()**
    -   **type_int()**
    -   **type_bigint()**
    -   **type_float()**
    -   **type_decimal()**
    -   **type_text()**
    -   **type_longtext()**
    -   **type_varchar([ $length = 255 ])**
    -   **type_date()**
    -   **type_datetime()**
    -   **type_timestamp([ $asDefault = FALSE ])**
    -   **type_blob()**
    -   **type_bool()**


  
* **->nullable( bool $state )**

  Set this column as NULL or NOT NULL. Default is `TRUE` / nullable.
  You can set defaults to nullable fields as well.

* **->defaults( mixed $value )**

  Adds a default value for records. Usually a *string* or *integer* value or `NULL`.

  **CURRENT_TIMESTAMP as dynamic default value**

  But if you like to add a timestamp of the current time to new inserted records, you can use a TIMESTAMP field with a special default value to achieve this.
  ``` php
  $table->addColumn('creation_date')->type($schema::DT_TIMESTAMP)->defaults($schema::DF_CURRENT_TIMESTAMP);

  // a shorthand would be:
  $table->addColumn('creation_date')->type_timestamp(TRUE);
  ```

* **->after( string $name )**

  Trys to place the new column behind an existing one. (*only works for SQLite and MySQL*)

* **->index([ bool $unique = false ])**

  Add an index for that field. `$unique` makes it a UNIQUE INDEX.


---

Like this Plugin?

[![buy me a beer](https://raw.github.com/ikkez/Beer-Donation-Button/gh-pages/img/beer_donation_button_single.png "donate")](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=44UHPNUCVP7QG
)
