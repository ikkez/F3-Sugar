## F3 FileUnit

This F3-Plugin offers a bunch of file system methods, helping you getting things done faster.
It's uses the Standard PHP Libary with DirectoryIterators and file SPL objects to fit most common tasks and modifications.

***

### Usage

- 	`FileUnit::copyDir('/source/path/', '/target/path/');`
	
	Will copy the whole directory tree with all files and folders to a specified location.
	It returns an array of copied and skipped (e.g. symbolic links) items.

- 	`FileUnit::listDir('/path/to/list');`
	
	Returns an structured array of the directory/file tree found in the specified path. Add `true` as second argument to return a flatten array.

- 	`FileUnit::cleanDir('/delete/all/within/this/path/');`
	
	Deletes everything within the given path.
	

- 	`FileUnit::saveUploaded($formInputName, 'files/', $slug);`
	
	Catches the uploaded file of a submitted Form, e.g. of `<input name="image>`, and copy it to the specified path. Be sure to set the right encoding in your form. Typically for file upload is `enctype="multipart/form-data"`.
	If you set $slug to TRUE, it will convert the filename string to a URL & filesystem-friendly version.
	It returns some file information, like basename, filename, extension, filesize in kbyte and  mimetype, when everything went fine, otherwise false.
	


### TODO:

-	add support for multiple uploaded files