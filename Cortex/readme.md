![Cortex](https://dl.dropboxusercontent.com/u/3077539/_linked/cortex_icon.png?asd)
***

### A general purpose Data-Mapper for the PHP Fat-Free Framework


Cortex is a multi-engine ActiveRecord ORM / ODM that offers easy object persistence. Some of its main features are:

  - It handles SQL, Jig and MongoDB database engines
  - Write queries in well-known SQL Syntax, they can be translated to Jig and Mongo
  - Make SQL schema-less with the fluid SQL mode and let missing table columns be created automatically
  - Support for model objects
  - Relationships: link multiple models together to one-to-one, one-to-many and many-to-many associations
  - smart-loading of related models (intelligent lazy and eager-loading without configuration)
  - setup model schemes to auto create DB tables from installer or migrate scripts
  - custom setter and getter preprocessors for all fields
  - default values and nullable fields for NoSQL

With Cortex you can create generic apps, that work with any DB of the users choice, no matter if it's Postgre, MongoDB or even none. You can also mash-up multiple engines, use them simultaneous or link models of different DB engines together.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [SQL Fluid Mode](#sql-fluid-mode)
3. [Cortex Models](#cortex-models)
  1. [Configuration](#configuration)
  2. [Setup](#setup)
  3. [Setdown](#setdown)
  4. [Custom Field PreProcessors](#custom-field-preprocessors)
4. [Relations](#relations)
  1. [Setup the linkage](#setup-the-linkage)
  2. [Working with Relations](#working-with-relations)
  3. [Additional notes](#additional-notes)
5. [Collections](#collections)  
6. [Filter Query Syntax](#filter-query-syntax)
7. [Known Bugs](#known-bugs)
8. [Roadmap](#roadmap)
9. [Final Words](#final-words)
10. [License](#license)
    

## Quick Start

### System Requirements

Cortex requires at least Fat-Free v3.1.2 and PHP 5.3.3. For some of the features, it also requires the F3 [SQL Schema Plugin](https://github.com/ikkez/F3-Sugar/tree/master-v3/SchemaBuilder).

### Install

To install Cortex, just copy the `/lib/db/cortex.php` file into your libs.

### Setup a DB

Create a DB object of your choice. You can choose between [SQL](http://fatfreeframework.com/sql), [Jig](http://fatfreeframework.com/jig) or [MongoDB](http://fatfreeframework.com/mongo). Here are some examples:

```php
// SQL - MySQL
$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname=myApp','user', 'pw');
// SQL - SQlite
$db = new \DB\SQL('sqlite:db/database.sqlite');
// Jig
$db = new \DB\Jig('data/');
// Mongo
$db = new \DB\Mongo('mongodb://localhost:27017','testdb');
```

### Let's get it rolling

If you are familiar with F3's own Data-Mappers, you already know all about the basic CRUD operations you can also do with Cortex too. It implements the ActiveRecord [Cursor Class](http://fatfreeframework.com/cursor) with all its methods. So it's that easy:

```php
$user = new \DB\Cortex($db, 'users');
$user->name = 'Jack Ripper';
$user->mail = 'jacky@email.com';
$user->save();
```

Okay, not very impressive, Ay? But let's find this guy again now:

``` php
$user->load(array('mail = ?','jacky@email.com'));
echo $user->name; // shouts out: Jack Ripper
```

As you can see, the syntax for the filter array stays pure SQL logic, but works in all DB engines. This also works for pretty complex where criteria:

```php
$user->load(array('name like ? AND (deleted = 0 OR rights > ?)','Jack%',3));
```

No need for complex criteria objects or confusing Mongo where-array constructions. It's just as simple as you're used to. Using a Jig DB will automatically translate that query into:

``` php
Array (
    [0] => (isset(@name) && preg_match(?,@name)) AND ( (isset(@deleted) && (@deleted = 0)) OR (isset(@rights) && @rights > ?) )
    [1] => /^Jack/
    [2] => 3
)
```

And for MongoDB it translates into this:

``` php
Array (
    [$and] => Array (
        [0] => Array (
            [name] => MongoRegex Object (
                    [regex] => ^Jack
                )
            )
        [1] => Array (
            [$or] => Array (
                [0] => Array (
                    [deleted] =>  0
                )
                [1] => Array (
                    [rights] => Array (
                            [$gt] => 3
                        )
                    )
                )
            )
    )
)
```

You can use all the fancy methods from Cursor, like `load`, `find`, `cast`, `next` or `prev`. More about filtering and all the other methods a little later.

## SQL Fluid Mode

When you are prototyping some new objects or just don't want to bother with a table schema, while using Cortex along with a SQL DB backend, you can enable the SQL Fluid Mode. This way Cortex will create all necessary tables and columns automatically, so you can focus on writing your application code. It will try to guess the right datatype, based on the given sample data. To enable the fluid mode, just pass a third argument to the object's constructor:

``` php
$user = new \DB\Cortex($db, 'users', TRUE);
$user->name = 'John';            // varchar(256)
$user->age = 25;                 // integer
$user->active = true;            // boolean|tinyint
$user->lastlogin = '2013-08-28'; // date
```

This way it also creates datatypes of datetime, float, text (when strlen > 255) and double.

## Cortex Models

Using the Cortex class directly is easy for some CRUD operations, but to enable some more advanced features, you'll need to wrap Cortex in a Model class like this:

``` php
// file user.php
class User extends \DB\Cortex {

  protected
    $db = 'AppDB1',     // F3 hive key of a valid DB object
    $table = 'users',   // the DB table to work on
    $fluid = true;      // triggers the SQL Fluid Mode
}
```

Now you can create your mapper object that easy:

``` php
$user = new \Users();
```

### Configuration

Your Cortex Model accepts some sort of field configuration. This way it's able to follow a defined schema of your data entity. It looks like this:

``` php
// file user.php
class User extends \DB\Cortex {

  protected
    $fieldConf = array(
        'name' => array(
            'type' => 'VARCHAR256',
            'nullable' => false,
        ),
        'mail' => array(
            'type' => 'VARCHAR128'
        ),
        'website' => array(
            'type' => 'VARCHAR256'
        ),
        'rights_level' => array(
            'type' => 'TINYINT',
            'default' => 3,
        ),
    ),
    $db = 'AppDB1',
    $table = 'users';
}
```

You can set datatypes, nullable flags and default values for your columns. Doing so enables you to install new Models into your SQL database, and adds some nullable validation checks and the ability for defaults to the NoSQL engines (Jig and Mongo).

Because column datatypes are currently only needed for setting up the tables in SQL, it follows that [SQL DataTypes Table](https://github.com/ikkez/F3-Sugar/tree/master-v3/SchemaBuilder#column-class) from the [SQL Schema Plugin](https://github.com/ikkez/F3-Sugar/blob/master-v3/SchemaBuilder/lib/db/sql/schema.php). If you don't need that feature and your tables are already existing, then you can just skip the configuration for those fields, because the underlaying SQL Mapper exposes the existing table schema.

The datatype values are defined constants from the Schema Plugin. If you'd like to use some autocompletion in your IDE to find the right values, type in the longer path to the constants:

``` php
'type' => \DB\SQL\Schema::DT_VARCHAR256,
```

#### Alternative Configuration Method

In case you need some more flexible configurations and don't want to hard-wire it, you could overload the Model class constructor to load its config from an ini file or elsewhere. In example:

``` php
class User extends \DB\Cortex {

    function __construct() {
        $f3 = \Base::instance();
        if(!$f3->exists('usermodel'))
            $f3->config('app/models/usermodel.ini');        
        foreach ($f3->get('usermodel') as $key => $val)
            $this->{$key} = $val;
        parent::__construct();
    }
}
```

And in your `usermodel.ini` file:

``` ini
[globals]
usermodel.db = AppDB1
usermodel.table = users
usermodel.fieldConf.name.type = VARCHAR256
usermodel.fieldConf.name.nullable = FALSE
usermodel.fieldConf.mail.type = VARCHAR128
usermodel.fieldConf.website.type = VARCHAR256
usermodel.fieldConf.rights_level.type = TINYINT
usermodel.fieldConf.rights_level.default = 3
```


### Setup

This method tries to create the SQL DB tables you need to run your Cortex object. It also adds just missing fields to already existing tables.

#### With Model class
If your Model has a valid field configuration, you are able to run this installation method:

``` php
\User::setup();
``` 

#### Without Model class
If you have no model class you need to provide all parameters the setup method has.

``` php
$fields = array(
    'name' => array('type' => \DB\SQL\Schema::DT_TEXT),
    'mail' => array('type' => \DB\SQL\Schema::DT_INT4),
    'website' => array('type' => \DB\SQL\Schema::DT_INT4),
);
\DB\Cortex::setup($db, 'users', $fields);
``` 


### Setdown

This method completely removes the specified table from the used database. So handle with care. 

``` php
// With Model class
\User::setdown();

// Without Model class
\DB\Cortex::setdown($db, 'users');
``` 

### Custom Field PreProcessors

You can define some custom functions that are called when you set or get attributes from your models. These are extremely useful for validation  directly in your Model, or some extended save or load cascades.

#### Setter

To create a setter for any field, just a create class method named `set_*`, where `*` is the name of your field. In example:

``` php
class User extends \DB\Cortex {    
    [...]
    
    // validate email address
    public function set_mail($value) {
        if (\Audit::instance()->email($value) == false) {
            // no valid email address
            // throw exception or set an error var and display a flash message
            $value = null;
        }
        return $value;
    }
    
    // hash a password before saving
    public function set_password($value) {        
        return \Bcrypt::instance()->hash($value);
    }
}
```

So setting these fields in your Model, like:

``` php
$user->password = 'secret';
$user->mail = 'foo@bar.com';
```

will now trigger your custom setters, doing anything you like.

#### Getter

Custom getters works the same like setters. Just create a method that prepends the field name like `get_*`:

``` php
class User extends \DB\Cortex {    
    [...]    
    public function get_name($value) {
        return ucfirst($value);
    }    
}
```

## Relations

With Cortex you can create associations between multiple Models. By linking them together, you can create all common relationships you need for smart and easy development.

### Setup the linkage

To make relations work, you need to use a model class with field configuration. Cortex offers the following types of associations, that mostly must be defined in both classes of a relation:

<table>
    <tr>
        <th>Type</th>
        <th>Model A</th>
        <th>Direction</th>
        <th>Model B</th>
    </tr>
    <tr>
        <td>1:1</td>
        <td>belongs-to-one</td>
        <td>&lt;- -&gt;</td>
        <td>has-one</td>
    </tr>
    <tr>
        <td>1:m</td>
        <td>belongs-to-one</td>
        <td>&lt;- -&gt;</td>
        <td>has-many</td>
    </tr>
    <tr>
        <td>m:m</td>
        <td>has-many</td>
        <td>&lt;- -&gt;</td>
        <td>has-many</td>
    </tr>
    <tr>
        <td>m:m</td>
        <td>belongs-to-many</td>
        <td> ---&gt;</td>
        <td></td>
    </tr>
</table>

This is how a field config looks with a relation:

![Cortex Rel 1](https://dl.dropboxusercontent.com/u/3077539/_linked/cortex-class-conf.png)

This creates an aggregation between Author and News, so

> One News belongs to one Author.

> One Author has written many News.

![UML 1](https://dl.dropboxusercontent.com/u/3077539/_linked/cortex-dia-1.png?)

As a side note: `belongs-to-*` definitions will create a new column in that table, that is used to save the id of the counterpart model. Whereas `has-*` definitions are just virtual fields which are going to query the linked models by their own id. This leads us to the following configuration schema:

For **belongs-to-one** and **belongs-to-many**

```
'realTableField' => array(
    'relationType' => 'NamespacedClassName',
),
```

Defining a foreign key for `belongs-to-*` is optional. The default way is to use the identifier field (`id` in SQL, `_id` in NoSQL). If you need to define another id field use `array('NamespacedClassName','pKey')`. 

For **has-one** and **has-many**

```
'virtualField' => array(
    'relationType' => array('NamespacedClassName','foreignKey'),
),
```

The foreign key is the field name you used in the counterpart model to define the `belongs-to-one` connection.

### Working with Relations

Okay finally we come to the cool part. When configuration is done and setup executed, you're ready to use the following make-my-dev-life-easier methods.

#### one-to-one

To save a relation:

``` php
$author = new \AuthorModel();
$author->load(array('name = ?','Johnny English'));

$profile = new ProfileModel();
$profile->status_message = 'Hello World';
$profile->author = $author; 
// OR you can also just put in the id instead of the whole object here
$profile->author = '521eedf5ec4df'; 
$profile->save();
```

and to load it again:
``` php
$author->load();
echo $author->profile->status_message; // Hello World

$profile->load();
echo $profile->author->name; // Johnny English
```

#### one-to-many, many-to-one

Save an author to a news.

``` php
$author->load(array('name = ?','Johnny English'));
$news->load(array('_id = ?','521eedf5ed779'));
$news->author = $author; // set the object or the raw id
$news->save();
```

now you can get:

``` php
$news->author->name; // 'Johnny English'
```

The field `author` now holds the whole mapper object of the AuthorModel. So you can also update, delete or cast it.

The getting all news by an author in the counterpart looks like this:

``` php
$author->load(array('_id = ?', 42));
$author->news; // is now an array of NewsModel objects

// if you like to cast them all you can use
$allNewsByAuthorX = $author->castAll('news'); // is now a multi-dimensional array
```

#### many-to-many, bidirectional

When both models of a relation has a `has-many` configuration on their linkage fields, Cortex create a new reference table in setup, where the foreign keys of both models are linked together. This way you can query model A for related models of B and vice versa.

To save many collections to a model you've got several ways:

``` php
$news->load(array('_id = ?',1));

// array of IDs from TagModel
$news->tags2 = array(12, 5);
// OR a split-able string
$news->tags2 = '12;5;3;9'; // delimiter: [,;|]
// OR an array of single mapper objects
$tag = new \TagModel();
$tag->load(array('_id = ?', 1));
$tag2 = new \TagModel();
$tag2->load(array('_id = ?', 2));
$tag3 = $tag->findone(array('_id = ?', 3));
$news->tags = array($tag,$tag2,$tag3);
// OR a hydrated mapper that may contain multiple results
$news->tags2 = $tag->load(array('_id != ?',42));

$news->save();
```
 
Now you can get all tags of a news entry:
 
``` php
$news->load(array('_id = ?',1));
echo $news->tags2[0]['title']; // Web Design
echo $news->tags2[1]['title']; // Responsive
```

And all news that are tagged with *Responsive*:

``` php
$tags->load(array('title = ?','Responsive'));
$tags->news[0]->title; // '10 Responsive Images Plugins'
```

#### many-to-many, unidirectional

You can use a `belongs-to-many` field config to define a one-way m:m relation.
This is a special type for many-to-many as it will not use a 3rd table for reference and just puts a list of IDs into the table field, as commonly practiced in NoSQL solutions.
This is an unidirectional binding, because the counterpart wont know anything about its relation and it's harder to query the reserve way, but it's still a lightweight and useful solution in some use cases.

Saving works the same way like the other m:m type describes above

``` php
$news->tags = array(4, 7); // IDs of TagModel
$news->save();
```

and get them back:

``` php
$news->load(array('_id = ?', 77));
echo $news->tags[0]->title; // Web Design
echo $news->tags[1]->title; // Responsive
```

### Additional notes

* To release any relation, just set the field to `NULL`.

* All relations are lazy loaded to save performance. That mean, they won't be loaded until you access them by the linked property or casting the whole parent model.

* lazy loading within a result collection will **automatically** invoke the eager loading of that property to the whole set. This method is called _smart loading_ and is used to get around the [1+N query problem](http://www.phabricator.com/docs/phabricator/article/Performance_N+1_Query_Problem.html).

* to get the id of any record use `$user->_id;`

* To find any record by its id use the field `_id` in your filter array like `array('_id = ?',123)`.

## Filter Query Syntax

Well basically the `$filter` syntax for writing cortex queries is simple SQL. But there are some slightly modifications you should have read in these additional notes.

### Operators

These common filter operators are supported: 
- relational operators: `<`, `>`, `<=`, `>=`, `==`, `=`, `!=`, `<>`
- search operators: `LIKE`, `IN`, `NOT IN` (not case-sensitive)
- logical operators: `(`, `)`, `AND`, `OR`, `&&`, `||`

When using comparison operators, you can compare your table fields against simple values like `array('foo = 1')` or other fields like `array('foo < bar')`. Therefore you can also use placeholders with positional bind-parameters like `array('foo = ?',1)` or named parameters `array('foo = :bar',':bar'=>1)`. You may also mix them together in one query.

The `LIKE` operator works the same way like the [F3 SQL search syntax](http://www.fatfreeframework.com/sql-mapper#search). The search wildcard (`%`) belongs into the bind value, not the query string.

The `IN` operator usually needs multiple placeholders in raw PDO (like `foo IN (?,?,?)`). In Cortex queries you simply use an array for this: `array('foo IN ?',array(1,2,3))`, the QueryParser does the rest.


### Options

The `$options` array for load operations respects the following keys:

- order
- limit
- offset

Use `DESC` and `ASC` flags for sorting fields, just like in SQL. Additional `group` settings might be handled in a future version.

## Collections

Whenever you use the `find` method, it will return an instance of the new CortexCollection class. This way we are able determine the whole collection from the inside of every single mapper in the results, and that gives us some more advanced features, like the [smart-loading of relations](https://github.com/ikkez/F3-Sugar/issues/23#issuecomment-24956163). You can also transpose the results by a defined key using `getBy()` or fetch all values of a certain key using `getAll()`. More about that later in the API docs. The CortexCollection implements the `ArrayIterator` interface, so it is accessible like an usual array.

## Known Bugs

This plugin is still in an early stage of development. So at this point it may have some ;) If you find any, please file a [new Issue](https://github.com/ikkez/F3-Sugar/issues) on github.

## Roadmap

- ~~some more caching features~~
- ~~eager loading / preloading relations for find()~~
- polymorphic relations
- handle aggregational fields like SUM(), MAX(), AVG()
- handle creation of indexes
- extended date functions
- search and filters for relations
- logging


## Final Words

Cortex may try to bind them all, but at least it's not the one ring from Mordor!
So by the nature of a general purpose tool, it currently only reflects to the most common use cases. If you need more extensive control over your queries or the DB, you may consider to use the underlaying Mapper or DB directly. This could be done in custom methods or field preprocessors in your Model classes.

Anyways, i hope you find this useful. If you like this plugin, why not make a donation?

[![buy me a Beer](https://dl.dropboxusercontent.com/u/3077539/Beer/bdb_small_single.png)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=44UHPNUCVP7QG)

If you like to see Cortex in action, have a look at [fabulog](https://github.com/ikkez/fabulog "the new fabulous blog-ware").

License
-

GPLv3
  

    
