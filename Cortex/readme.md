![Cortex](https://dl.dropboxusercontent.com/u/3077539/_linked/cortex_icon.png?asd)
***

### A general purpose Data-Mapper for the PHP Fat-Free Framework


Cortex is a multi-engine ActiveRecord ORM / ODM that offers easy object persistence. Some of its main features are:

  - It handles SQL, Jig and MongoDB database engines
  - Write queries in well-known SQL Syntax, they can be translated to Jig and Mongo
  - Easy prototyping with the SQL Fluid Mode, which makes your RDBMS schema-less and adds new table columns automatically
  - Support for models and collections
  - Relationships: link multiple models together to one-to-one, one-to-many and many-to-many associations
  - smart-loading of related models (intelligent lazy and eager-loading without configuration)
  - automatically setup the model tables from installer or migrate scripts by defined schemes
  - lots of event handlers and custom setter / getter preprocessors for all fields
  - define default values and nullable fields for NoSQL

With Cortex you can create generic apps, that work with any DB of the users choice, no matter if it's Postgre, MongoDB or even none.
You can also mash-up multiple engines, use them simultaneous or link models of different DB engines together.

It's great for fast and easy data abstraction and offers a bunch of useful filter possibilities.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [SQL Fluid Mode](#sql-fluid-mode)
3. [Cortex Models](#cortex-models)
	1. [Configuration](#configuration)
		1. [Additional Data Types](#additional-data-types)
		2. [Alternative Configuration](#alternative-configuration-method)
		2. [Blacklist Fields](#blacklist-fields)
	2. [Setup](#set-up)
	3. [Setdown](#set-down)
4. [Relations](#relations)
	1. [Setup the linkage](#setup-the-linkage)
	2. [Working with Relations](#working-with-relations)
5. [Event Handlers](#event-handlers)
	1. [Custom Field Handler](#custom-field-handler)
6. [Filter Query Syntax](#filter-query-syntax)
	1. [Operators](#operators)
	2. [Options Array](#options)
7. [Advanced Filter Techniques](#advanced-filter-techniques)
	1. [has](#has)
	2. [filter](#filter)
8. [Insight into aggregation](#insight-into-aggregation)
	1. [Counting Relations](#counting-relations)
	2. [Virtual Fields](#virtual-fields)
9. [Collections](#collections)
10. [Additional notes](#additional-notes)
11. [Known Issues](#known-issues)
12. [Roadmap](#roadmap)
13. [Final Words](#final-words)
14. [License](#license)
    

## Quick Start

### System Requirements

Cortex requires at least Fat-Free v3.4 and PHP 5.3.3. For some of the features, it also requires the F3 [SQL Schema Plugin](https://github.com/ikkez/F3-Sugar/tree/master-v3/SchemaBuilder).

### Install

To install Cortex, just copy the `/lib/db/cortex.php` file into your libs. For the SQL Schema Plugin, copy `lib/db/sql/schema.php` as well.

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

If you are familiar with F3's own Data-Mappers, you already know all about the basic CRUD operations you can do with Cortex too. It implements the ActiveRecord [Cursor Class](http://fatfreeframework.com/cursor) with all its methods. So it's that easy:

```php
$user = new \DB\Cortex($db, 'users');
$user->name = 'Jack Ripper';
$user->mail = 'jacky@email.com';
$user->save();
```

Okay, not very impressive, Aye? But let's find this guy again now:

``` php
$user->load(array('mail = ?','jacky@email.com'));
echo $user->name; // shouts out: Jack Ripper
```

As you can see, the filter array stays pure SQL syntax, but will work with all DB engines. This also works for pretty complex where criteria:

```php
$user->load(array('name like ? AND (deleted = 0 OR rights > ?)', 'Jack%', 3));
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

When you are prototyping some new objects or just don't want to bother with a table schema, while using Cortex along with a SQL DB backend, you can enable the SQL Fluid Mode.
This way Cortex will create all necessary tables and columns automatically, so you can focus on writing your application code. It will try to guess the right data type, based on the given sample data. To enable the fluid mode, just pass a third argument to the object's constructor:

``` php
$user = new \DB\Cortex($db, 'users', TRUE);
$user->name = 'John';            // varchar(256)
$user->age = 25;                 // integer
$user->active = true;            // boolean|tinyint
$user->lastlogin = '2013-08-28'; // date
```

This way it also creates data types of datetime, float, text (when `strlen > 255`) and double.

The fluid mode disables the caching of the underlying SQL table schema. This could impact on performance, so keep in mind to deactivate this when you're done. Also keep in mind that you are not able to load or find any records from tables that are not existing - consider to create and save some sample data first, so Cortex can create the tables.


## Cortex Models

Using the Cortex class directly is easy for some CRUD operations, but to enable some more advanced features, you'll need to wrap Cortex into a Model class like this:

``` php
// file at app/model/user.php
namespace Model;

class User extends \DB\Cortex {

  protected
    $db = 'AppDB1',     // F3 hive key of a valid DB object
    $table = 'users';   // the DB table to work on
}
```

Now you can create your mapper object that easy:

``` php
$user = new \Model\Users();
```

Cortex needs at least a working DB object. You can also pass this through the constructor (`new \Model\Users($db);`) and drop it in the setup.
`$db` must be a string of a hive key where the DB object is stored, or the DB object itself.
If no `$table` is provided, Cortex will use the class name as table name.

### Configuration

Cortex does not need that much configuration. But at least it would be useful to have setup the field configuration.
This way it's able to follow a defined schema of your data entity and enables you to use some auto-installation routines (see [setup](#set-up)). It looks like this:

``` php
// file at app/model/user.php
namespace Model;

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
    $db = 'DB',
    $table = 'users'
    $fluid = true,      // triggers the SQL Fluid Mode, default: false
    $primary = 'uid',   // name of the primary key, default: id
    $ttl = 120,         // caching time of field schema, default: 60
    $rel_ttl = 30;      // caching time of relations, default: 0
}
```

In the `$fieldConf` array, you can set data types (`type`), `nullable` flags and `default` values for your columns. Doing so enables you to install new Models into your SQL database, and adds some nullable validation checks and the ability for defaults to the NoSQL engines (Jig and Mongo).

Because column data types are currently only needed for setting up the tables in SQL, it follows that [SQL Data Types Table](https://github.com/ikkez/F3-Sugar/tree/master-v3/SchemaBuilder#column-class) from the [SQL Schema Plugin](https://github.com/ikkez/F3-Sugar/blob/master-v3/SchemaBuilder/lib/db/sql/schema.php).
**You don't need to configure all fields this way.** The underlying SQL Mapper exposes the existing table schema, 
so if you don't need that install feature and your tables are already existing, then you can just skip the configuration for those fields, or just setup some of them (i.e. for fields with relations).

You may also extend this config array to have a place for own validation rules or whatever.

The data type values are defined constants from the Schema Plugin. If you'd like to use some auto-completion in your IDE to find the right values, type in the longer path to the constants:

``` php
'type' => \DB\SQL\Schema::DT_VARCHAR256,
```

#### Additional Data Types

Cortex comes with two own data types for handling array values in fields. Even when Jig and Mongo support them naturally, most SQL engines do not. Therefore Cortex introduces:

+ `DT_SERIALIZED`
+ `DT_JSON`

In example:

``` php
'colors' => array(
    'type' => self::DT_JSON
    // or
    'type' => 'JSON'
),
```

Now you're able to save array data in your model field, which is json_encoded behind the scene (of cause only when using a SQL backend).

``` php
$mapper->colors = array('red','blue','green');
```


#### Alternative Configuration Method

In case you need some more flexible configurations and don't want to hard-wire it, you can overload the Model class constructor to load its config from an `ini`-file or elsewhere. In example:

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

#### Blacklist Fields

The `field()` method can be used to return the available fields on the current model. If called with one simple array argument like `$news->fields(array('title'));`, it'll apply the provided elements as a whitelist to the whole mapper. For the rest of its lifetime it'll only hydrate the fields you permitted here.
If called with a 2nd argument like `$news->fields(array('author'),true);`, the array is going to be uses as a blacklist instead, and restrict the access to the provided fields.
You can also define deep nested fields using a dot as separator: `$news->fields(array('tags.title'));` will only hydrate the tag title in your news model and wont load or save any other field that exists in your tag model.


### Set up

This method tries to create the SQL DB tables you need to run your Cortex object. It also adds just missing fields to already existing tables.

If your Model has a valid field configuration, you are able to run this installation method:

``` php
\Model\User::setup();
``` 

If you have no model class you need to provide all parameters the setup method has.

``` php
$fields = array(
    'name' => array('type' => \DB\SQL\Schema::DT_TEXT),
    'mail' => array('type' => \DB\SQL\Schema::DT_INT4),
    'website' => array('type' => \DB\SQL\Schema::DT_INT4),
);
\DB\Cortex::setup($db, 'users', $fields);
``` 


### Set down

This method completely removes the specified table from the used database. So handle with care. 

``` php
// With Model class
\Model\User::setdown();

// Without Model class
\DB\Cortex::setdown($db, 'users');
``` 


## Relations

With Cortex you can create associations between multiple Models. By linking them together, you can create all common relationships you need for smart and easy persistence.

### Setup the linkage

To make relations work, you need to use a model class with field configuration. Cortex offers the following types of associations, that mostly **must be defined in both classes** of a relation:

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

As a side note: `belongs-to-*` definitions will create a new column in that table, that is used to save the id of the counterpart model (foreign key field).
Whereas `has-*` definitions are just virtual fields which are going to query the linked models by their own id (the inverse way). This leads us to the following configuration schema:

For **belongs-to-one** and **belongs-to-many**

```
'realTableField' => array(
    'relationType' => '\Namespace\ClassName',
),
```

Defining a foreign key for `belongs-to-*` is optional. The default way is to use the identifier field. For SQL engines this is either the default primary key `id` or the custom primary key that can be set with the `$primary` class property. NoSQL engines will use `_id`. If you need to define another non-primary field to join with, use `array('\Namespace\ClassName','cKey')`.

For **has-one** and **has-many**

```
'virtualField' => array(
    'relationType' => array('\Namespace\ClassName','foreignKey'),
),
```

The foreign key is the field name you used in the counterpart model to define the `belongs-to-one` connection.

There is one special case for many-to-many relations: here you use a `has-many` type on both models, which implies that there must be a 3rd pivot table that will be used for keeping the foreign keys that binds everything together. Usually Cortex will auto-create that table upon [setup](#set-up) method, using an auto-generated table name. If you like to use a custom name for that joining-table, add a 3rd parameter to the config array of *both* models, i.e.:

```
'tags' => array(
    'has-many' => array('\Model\Tag','news','news_tags'),
),
```


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
$allNewsByAuthorX = $author->castField('news'); // is now a multi-dimensional array
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

This example shows the inverse way of querying (using the TagModel to find the corresponding news). Of cause the can also use a more direct way that offers even more possibilities, therefore check the [has()](#has) method.

#### many-to-many, unidirectional

You can use a `belongs-to-many` field config to define a one-way m:m relation.
This is a special type for many-to-many as it will not use a 3rd table for reference and just puts a list of IDs into the table field, as commonly practiced in NoSQL solutions.
This is an unidirectional binding, because the counterpart wont know anything about its relation and it's harder to query the reserve way, but it's still a lightweight and useful solution in some cases.

Saving works the same way like the other m:m type described above

``` php
$news->tags = array(4,7); // IDs of TagModel
$news->save();
```

and get them back:

``` php
$news->load(array('_id = ?', 77));
echo $news->tags[0]->title; // Web Design
echo $news->tags[1]->title; // Responsive
```


## Event Handlers

Cortex inherits all setters form the [Cursor Event Handlers](http://fatfreeframework.com/cursor#event-handlers) and additionally adds custom field handlers (setter/getter). These can be used to execute some extra code right before or after doing something. This could be useful for validation directly in your Model, or some extended save, load or delete cascades.

The following events are supported:

* `onload`
* `onset`
* `onget`
* `beforeerase`
* `aftererase`
* `beforeinsert`
* `afterinsert`
* `beforeupdate`
* `afterupdate`


You can setup own handlers to this events like this:

```php
$mapper->onload(function($self){
	// custom code
});
// or 
$mapper->onload('App/Foo/Bar::doSomething');
```

You can provide anything that is accepted by the [Base->call](http://fatfreeframework.com/base#call) method as handler function. Notice to use the `$self->set('field','val')` instead of `$self->field=val`, if you define a handler within a child class of Cortex (i.e. an extended `__construct` in your own model class).

If any `before*` event returns a `false` result, the action that is going to be performed will be aborted, and the `after*` events are skipped. 

### Custom Field Handler

The `onset` and `onget` events have slightly different parameters:
 
```php
$mapper->onset('field',function($self, $val){
	return md5($val);
});
```

You can also define these custom field preprocessors as a method within the class, named `set_*` or `get_*`, where `*` is the name of your field. In example:

``` php
class User extends \DB\Cortex {
    // [...]
        
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
    public function get_name($value) {
        return ucfirst($value);
    }  
}
```

So setting these fields in your Model, like:

``` php
$user->password = 'secret';
$user->mail = 'foo@bar.com';
```

will now trigger your custom setters, doing anything you like.



## Filter Query Syntax

Well basically the `$filter` syntax for writing cortex queries is simple SQL. But there are some slightly modifications you should have read in these additional notes.

### Operators

These common filter operators are supported: 
- relational operators: `<`, `>`, `<=`, `>=`, `==`, `=`, `!=`, `<>`
- search operators: `LIKE`,`NOT LIKE`, `IN`, `NOT IN` (not case-sensitive)
- logical operators: `(`, `)`, `AND`, `OR`, `&&`, `||`

When using comparison operators, you can compare your table fields against simple values like `array('foo = 1')` or other fields like `array('foo < bar')`. Therefore you can also use placeholders with positional bind-parameters like `array('foo = ?',1)` or named parameters `array('foo = :bar',':bar'=>1)`. You may also mix them together in one query.

The `LIKE` operator works the same way like the [F3 SQL search syntax](http://www.fatfreeframework.com/sql-mapper#search). The search wildcard (`%`) belongs into the bind value, not the query string.

The `IN` operator usually needs multiple placeholders in raw PDO (like `foo IN (?,?,?)`). In Cortex queries you simply use an array for this: `array('foo IN ?',array(1,2,3))`, the QueryParser does the rest.


### Options

The `$options` array for load operations respects the following keys:

- order
- limit
- offset

Use `DESC` and `ASC` flags for sorting fields, just like in SQL. Additional `group` settings are currently just bypassed to the underlying mapper and should work dependant on the selected db engine. Any unification on that might be handled in a future version.

## Advanced Filter Techniques

When your application reaches the point where all basic CRUD operations are working, you probably need some more control about finding your records based on conditions for relations.
Here comes the `has()` and `filter()` methods into play:

### has

The has method adds some conditions to a related field, that must be fulfilled in addition, when the **next** find() or load() method of its parent is fired. So this is meant for limiting the main results.

In other words: Let's find all news records that are tagged by "Responsive".

``` php
$news->has('tags', array('title = ?','Responsive'));
$results = $news->find();
$results[0]->title; // '10 Responsive Images Plugins'
```

Of cause you can also use the inverse way of querying, using the TagModel, load them by title and access the shared `$tags->news` property to find your records.
The advantage of the "has" method is that you can also add a condition to the parent as well. This way you could edit the load line into something like this:
`$news->find(array('published = ?', 1));`. Now you can limit your results based on two different models - you only load *published* news which were tagged "Responsive".

You can also add multiple has-conditions to different relations:

``` php
$news->has('tags', array('title = ?','Responsive'));
$news->has('author', array('username = ?','ikkez'));
$results = $news->find(array('published = ?', 1), array('limit'=>3, 'order'=>'date DESC'));
```

Now you only load the last 3 published news written by me, which were tagged "Responsive", sorted by release date. ;)

If you like, you can also call them in a fluent style: `$news->has(...)->load(...);`.

### filter

The filter method is meant for limiting the results of relations. In example: load author x and only his news from 2014.

``` php
$author->filter('news', array('date > ?','2014-01-01'));
$author->load(array('username = ?', 'ikkez'));
```

The same way like the `has()` method does, you can add multiple filter conditions. You can mix filter and has conditions too.
Once a `load` or `find` function is executed, the filter (and has) conditions are cleared for the next upcoming query.

Filter conditions are currently not inherited. That means if you recursively access the fields of a relation ($author->news[0]->author->news) they get not filtered, but fully lazy loaded again.


## Insight into aggregation

Cortex comes with some handy shortcuts that could be used for essential field aggregation.

### counting relations

Sometimes you need to know how many relations a record has - i.e. for some stats or sorting for top 10 list views.

Therefore have a look at the `countRel($key)` method. You can call this to add a new virtual field to the resulting records that counts the related records on `has-many` fields.

```php
// find all tags with the sum of all news that used the tag, ordered by the top occurring tags first.
$tag = new \Model\Tag();
$tag->countRel('news');
$result = $tag->find(null,array('order'=>'count_news DESC, title'))
```

The new field that is going to be added to the record is named like `count_{$key}`. As you can see, you can also use that field for additional sorting of your results. You can also combine this with the `has()` and `filter()` methods.
Notice that `countRel()` only applies to the next called `find()` operation. Currently, you cannot use the virtual count field in your `$filter` query.

### Virtual fields

Cortex has some abilities for own custom virtual fields. These might be useful to add additional fields that may contain data that is not stored in the real db table or computes its value out of other fields or functions, similar to the [custom field setters and getters](#custom-field-preprocessors).

```php
// just set a simple value
$user->virtual('is_online', TRUE);
// or use a callback function
$user->virtual('full_name', function($this) {
	return $this->name.' '.$this->surname;
});
```

You can also use this to count or sum fields together and even reorder you collection on this fields using `$collection->orderBy('foo DESC, bar ASC')`. Keep in mind that these virtual fields only applies to your final received collection - you cannot use these fields in your filter query or sort condition before the actual find. But if you use a SQL engine, you can use the underlying mapper abilities of virtual fields - just set `$mapper->newField = 'SQL EXPRESSION';` before any load or find operation happens.


## Collections

Whenever you use the `find` method, it will return an instance of the new CortexCollection class. This way we are able determine the whole collection from the inside of every single mapper in the results, and that gives us some more advanced features, like the [smart-loading of relations](https://github.com/ikkez/F3-Sugar/issues/23#issuecomment-24956163). The CortexCollection implements the `ArrayIterator` interface, so it is accessible like an usual array. Here are some of the most useful methods the Cortex Collection offers:

### getBy

You can transpose the results by a defined key using `getBy()`.
Therefore you need to provide an existing field in the mapper, like this;
 
 ```php
 $comment_result = $comments->find();
 $comment_result->getBy('email');
 ```
 
 This will resort the resulting array by the email field of each mapper, which gives you a result array like `array("foo@domain.com"=>array(...))`. If you provide `true` as 2nd argument, the records are ordered into another array depth, to keep track of multiple results per key.
 
### getAll

You can fetch all values of a certain key from all containing mappers using `getAll()`. Set the 2nd argument to `true` the get only the raw DB results instead of resolved mappers on fields that are configured as a relation.

### castAll

Similar to the `Cortex->afind` method, which automatically cast all resulting mappers to a simple nested array.

### orderBy

If you need to re-sort a result collection once more to another key, use this method like `$results->orderBy('name DESC');`.


## Additional notes

* To release any relation, just set the field to `NULL` and save the mapper.

* All relations are lazy loaded to save performance. That means they won't get loaded until you access them by the linked property or cast the whole parent model.

* lazy loading within a result collection will **automatically** invoke the eager loading of that property to the whole set. The results are saved to an [Identity Map](http://martinfowler.com/eaaCatalog/identityMap.html) to relieve the strain on further calls. I called this _smart loading_ and is used to get around the [1+N query problem](http://www.phabricator.com/docs/phabricator/article/Performance_N+1_Query_Problem.html) with no need for extra configuration.

* If you need to use a primary key in SQL which is different from `id` (for any legacy reason), you can use the `$primary` class property to set it to something else. You should use the new custom pkey in your queries now. Doing so will limit your app to SQL engines.

* to get the id of any record use `$user->_id;`. This even works if you have setup a custom primary key.

* To find any record by its **id** use the field `_id` in your filter array, like `array('_id = ?', 123)`.

* primary fields should not be included in the `$fieldConf` array. They could interfere with the [setup](#set-up) routine.

* the `copyfrom` method has extended functionality. The 1st argument accepts a hive key or an array of fields as source for the copy action. Its 2nd argument accepts a lambda function, an array of fields, or a split-able string for fields, that are used to filter the source array. 

* There are some little behaviours of Cortex you can control by these hive keys:

	* `CORTEX.queryParserCache`: if `TRUE` all query string are going to be cached too. Default: `FALSE` (may add a lot of cache entries)

	* `CORTEX.smartLoading`: triggers the intelligent-lazy-eager-loading. Default is `TRUE`, but turn it off if you think something works wrong. Could cause a lot of extra queries send to your DB, if deactivated.

	* `CORTEX.standardiseID`: Default `TRUE`. This moves any defined primary key into the `_id` field on returned arrays. 

## Known Issues

* Not really a bug, but collections returned by `find()` method are not cloneable because they need to keep unique references to the identity map of its relations. This leads to the point that all containing mappers are not automatically escaped in templates, regardless of the `ESCAPE` setting. Keep in mind to add the `| esc` filter to your tokens.

If you find any issues or bugs, please file a [new Issue](https://github.com/ikkez/F3-Sugar/issues) on github or write a mail. Thanks.

## Roadmap

I got a bunch of tasks on my todo list for cortex. If you have any ideas, suggestions or improvements, feel free to add an issue for this on github.


## Final Words

Cortex may try to bind them all, but at least it's not the one ring from Mordor!
So by the nature of a general purpose tool, it currently only reflects to the most common use cases. If you need more extensive control over your queries or the DB, you may consider to use the underlying mapper or DB directly. This could be done in custom methods or field preprocessors in your Model classes.

Anyways, i hope you find this useful. If you like this plugin, why not make a donation?

[![buy me a Beer](https://dl.dropboxusercontent.com/u/3077539/Beer/bdb_small_single.png)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=44UHPNUCVP7QG)

If you like to see Cortex in action, have a look at [fabulog](https://github.com/ikkez/fabulog "the new fabulous blog-ware").

License
-

GPLv3
  

    
