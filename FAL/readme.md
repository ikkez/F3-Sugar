![FAL LOGO](https://dl.dropboxusercontent.com/u/3077539/_linked/F3FAL.png)
#### A File Abstraction Layer for the Fat-Free Framework

This
F3-Plugin aims to give you an easy mapper for files, that allows you to attach some meta information to them. It comes with some filesystem adapters, to provide you a unified access to your files, which takes apart the need of changing code, when you'll change the files location / filesystem.

This way you don't have to worry about where and how the files will be stored. You can easiely load, save and move files between the given filesystems. Currently availabe adapters are: **Local Filesystem**, **FTP** and **DropBox** (expect more to come).

`This Plugin requires F3 version 3.0.5 or higher.`

***
### Filesystem

The FAL package comes along with a bunch of adapters you can all use separately. They all should implement the filesystem.php interface, that'll give you some basic filesystem operations, the FAL mapper takes advantage from.

To setup your filesystem, follow these examples:

**Local Filesystem Adapter**

Mount on your `files/` folder:
``` php
$myFS = new \FAL\LocalFS('files/');
```

You can also use this Prefab shortcut. It will create a mapper object and mounts the LocalFS on your layout directory defined in F3 **UI** var.
``` php
$fal = \FAL::instance();
```

**FTP Adapter**

``` php
$myFS = new \FAL\FTP('html/files/','ftp.mydomain.com','user','pass');
```

**DropBox Adapter**

``` php
$myFS = new \FAL\Dropbox($appKey,$appSecret);
```

To gain access to the users DropBox account, you need a _KEY_ and a _SECRET_ token for your app first. You can register a new app and get the keys for free right here: https://www.dropbox.com/developers/apps

Now the User has to authenticate with your new App. Therefor he'll be redirected to the DropBox Auth page, where he must agree. To run the login procedure, do the following:

``` php
$dropbox = new \FAL\Dropbox($appKey,$appSecret);
$authTokens = $dropbox->login();
```

If the login was successful, `$authTokens` will contain an array of `oauth_token` and `oauth_token_secret`. Save them beside your user data and set them with `$dropbox->setAuthToken($token, $secret);` the next time, to reuse that token-pair. Overwise you'll need to login on every request again. (Currently, the token-pair is also cached and reused from the browser SESSION. This may change in progress of development.)


#### Working with filesystems

-   **$myFS->read( $file );**
    
    Will return the contents of a given file. Whereas `$file` may always be a full file path (like `path/to/my/file.txt`).
    
-   **$myFS->write( $file, $content );**
   
    Write some data into a file. This will also overwrite existing files.


-   **$myFS->exists( $file );**
-   **$myFS->delete( $file );**
-   **$myFS->move( $from, $to );**
-   **$myFS->modified( $file );** returns timestamp of file's last modified time.
-   **$myFS->size( $file );** returns filesize in bytes
-   **$myFS->isDir( $path );**
-   **$myFS->listDir( $path, $filter );**
	
	Get all files and folders within $path. You can use a regex pattern i.e. like `/\.php/i` to filter the result array. Some filesystems also have a $recursive option.

-   **$myFS->createDir( $path );**
-   **$myFS->removeDir( $path );**

Some filesystems may have additional features. Look inside the code to get to know about it.

***
### Working with the FAL mapper

#### Create a new file
``` php
$fal = new \FAL($myFS); // pass filesystem object into constructor
// or the shortcut to your local UI dir
$fal = \FAL::instance();
$fal->load('documents/important.txt'); // use existing file, or create new if it's not existing
$fal->setContent = 'TOP SECRET DOCS CONTENT'; // write some data into the file
$fal->save(); // save the file
```
#### Using meta data
You can add new keys to the FAL object. Each will represent a meta property.
``` php
$fal->load('images/bridge.jpg');
$fal->title = 'a nice picture of a bridge in town';
$fal->author = 'picture was taken by: John Doe';
$fal->year = 2013;
$fal->save();
```
To access them after you hydrated the mapper by `load()`, you can simply `echo $fal->title` or use it as Array like `echo $fal['title']`.

#### Caching
FAL has an integrated caching feature that uses the F3 Cache engine. This way you can save remote files of a slow filesystem and don't need to connect and fetch the file data each time you read on it.
``` php
$fal->load('read/this/file.xml',3600); // cache file for 1 hour
```
If you save new content or meta data to a loaded, cached file, it'll refresh your cached data too.

#### Meta files
When you save meta data, FAL uses a MetaStorageInterface to store it. By default it uses meta files, that will be stored beside the real file. Let's say you have _monalisa.jpg_ and save _artist='Leonardo da Vinci'_ to it. FAL will now create the file monalisa.jpg.meta, which contains a json encoded meta array. If you like to name this meta files in a different way, you can achieve it like this:
``` php
// create a filesystem adapter
$localFS = new \FAL\LocalFS('files/');
// create meta file storage handler, set meta file mask
$meta = new \FAL\MetaFileStorage($localFS, '.meta_%s');
// create fal using both above
$fal = new \FAL($localFS,$meta);
```
Now your meta files would look like _.meta_monalisa.jpg_ and would be hidden due to the leading dot(.) in filename (except on windows). But be careful, with hidden files. I.e. when using the FTP adapter, you might need to change the FTP server settings. If you run _Proftpd_, your default settings wont allow clients to view hidden files, so FAL can not find them. You can change that behaviour by editing `/etc/proftpd/proftpd.conf` and add this:
```
ListOptions  "-la"
```

#### File Streams

Sometimes you may consider to work with files. Therefor FAL creates a StreamWrapper and loads the file into it. You can now use this file stream to run all other common operations that requires a valid file path.
``` php
$fal->load('data/categories.xml');
$xmlFile = $fal->getFileStream(); // fal://data/categories.xml
$xml = simplexml_load_file($xmlFile);
```

#### Function Overview

-   **$fal->load( $file, $ttl);**

    Hydrate mapper with meta data and file contents of the specified file. If $ttl is set (in seconds) and F3 Caching is on, it'll also load the file contents from cache. If caching is off, the file contents is lazy loaded, when you call getContent()
-   **$fal->delete( $file );** delete file and meta data
-   **$fal->move( $from, $to );** move the file, and call a _move_ hook in the used MetaStorage handler, if existing
-   **$fal->getContent();** return file content. If not already loaded, the filesystem is used to read the file
-   **$fal->setContent( $data );** set file content
-   **$fal->getFileStream()** register fal:// stream wrapper, if not defined, load file into it and return stream path
-   **$fal->save( $ttl );** write new data into file and meta, if changed. It also refreshes cache record if $ttl is set
-   **get(); set(); clear(); exists();** for handling meta key fields

### Roadmap
-   create a DB meta storage handler, to get rid of meta files
-   add filesystem adapter for Google Drive and Amazon S3
