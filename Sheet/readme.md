### Sheet

Some Excel and CSV tools.


```php
$excel = \Sheet::instance();

// data array
$rows = [ 
	[
		"name" => "rose",
		"color" => "red",
		"num" => 15
	],
	[
		"name" => "daisy",
		"color" => "yellow",
		"num" => 25,
	],
	[
		"name" => "orchid",
		"color" => "purple",
		"num" => 7
	]
];
// header array to rename the field labels and sort/reorder the data array 
$headers = ['name'=>'Name', 'num'=>'Number', 'color'=>'Flower Color'];

$excel->renderXLS($rows,$headers,"flowers.xls");
```

In case your data array is not keyed, you can use the `applyHeader` method to transform the array:

```php
$rows = [ 
	[
		"rose",
		"red",
		15
	],
	[
		"daisy",
		"yellow",
		25,
	],
	[
		"orchid",
		"purple",
		7
	]
];
$rows = \Sheet::instance()->applyHeader($rows,['name','color','num']);

```

**Notice:** The generated XLS can have problems in older versions of MS Office (Excel) with number-formatted fields not being recognized as such (displayed as text instead) and text fields, that contain line breaks can also leads to problems there (missing text). 

Nevertheless, these problems do not appear when opened with LibreOffice or OpenOffice or rendered as csv with `renderCSV` instead and then imported to excel.
 
 
 

## License

You are allowed to use this plugin under the terms of the GNU General Public License version 3 or later.

Copyright (C) 2017 Christian Knuth [ikkez]