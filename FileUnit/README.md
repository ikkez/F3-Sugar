## F3 FileUnit

This F3-Plugin offers a bunch of file system methods, helping you getting things done faster.
It's uses the Standard PHP Libary with DirectoryIterators and file SPL objects to fit most common tasks and modifications.

***

### Usage

- 	`FileUnit::instance()->copyDir('/source/path/', '/target/path/');`
	
	Will copy the whole directory tree with all files and folders to a specified location.
	It returns an array of copied and skipped (e.g. symbolic links) items.

- 	`FileUnit::instance()->listDir('/path/to/list');`
	
	Returns an structured array of the directory/file tree found in the specified path. Add `true` as second argument to return a flatten array.

- 	`FileUnit::instance()->cleanDir('/delete/all/within/this/path/');`
	
	Deletes everything within the given path.
	