[![Latest Stable Version](https://poser.pugx.org/dmamontov/rararchiver/v/stable.svg)](https://packagist.org/packages/dmamontov/rararchiver)
[![License](https://poser.pugx.org/dmamontov/rararchiver/license.svg)](https://packagist.org/packages/dmamontov/rararchiver)

RarArchiver
===========

Class for working with archives RAR. It allows you to add files to the directory, as well as extract, delete, rename, and get content.


## Requirements
* PHP version ~5.5.11

## Installation

1) Install [composer](https://getcomposer.org/download/)

2) Follow in the project folder:
```bash
composer require dmamontov/rararchiver ~1.0.0
```

In config `composer.json` your project will be added to the library `dmamontov/rararchiver`, who settled in the folder `vendor/`. In the absence of a config file or folder with vendors they will be created.

If before your project is not used `composer`, connect the startup file vendors. To do this, enter the code in the project:
```php
require 'path/to/vendor/autoload.php';
```

## Available methods

### RarArchiver::__construct

#### Description
```php
void RarArchiver::__construct ( string $file [, $flag = 0] )
```
It creates or opens a file and initializes it.

#### Parameters
* `file` - The path to archive.
* `flag` - An optional parameter.
 * `RarArchiver::CREATE` - It creates the archive if it is not found.
 * `RarArchiver::REPLACE` - Overwrites the archive if it is found, otherwise create it.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);
```

### RarArchiver::isRar

#### Description
```php
boolean RarArchiver::isRar ( void )
```
Checks whether the file archive RAR.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->isRar()) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::getFileList

#### Description
```php
array RarArchiver::getFileList ( void )
```
Gets a list of files in the archive.

#### Return Values
Returns the filled array on success, or an empty array on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if (count($rar->getFileList()) > 0) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::addEmptyDir

#### Description
```php
boolean RarArchiver::addEmptyDir ( string $dirname )
```
Adds an empty directory in the archive.

#### Parameters
* `dirname` - The directory to add.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->addEmptyDir('newEmptyDirectory')) {
    echo 'Created a new root directory';
} else {
	echo 'Could not create the directory';
}
```

### RarArchiver::addFile

#### Description
```php
boolean RarArchiver::addFile ( string $filename [, string $localname = ''] )
```
Adds a file to a RAR archive from a given path.

#### Parameters
* `filename` - The path to the file to add.
* `localname` - If supplied, this is the local name inside the RAR archive that will override the `filename`.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->addFile('/path/to/index.txt', 'newname.txt')) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::addFromString

#### Description
```php
boolean RarArchiver::addFromString ( string $localname , string $contents )
```
Add a file to a RAR archive using its contents.

#### Parameters
* `localname` - The name of the entry to create.
* `contents` - The contents to use to create the entry.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->addFromString('newname.txt', 'file content goes here')) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::buildFromDirectory

#### Description
```php
void RarArchiver::buildFromDirectory ( string $path [, string $regex ] )
```
Populate a RAR archive from directory contents. The optional second parameter is a regular expression (pcre) that is used to exclude files.

#### Parameters
* `path` - The full or relative path to the directory that contains all files to add to the archive.
* `regex` - An optional pcre regular expression that is used to filter the list of files. Only file paths matching the regular expression will be included in the archive.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

$rar->buildFromDirectory(dirname(__FILE__) . '/project');
// or
$rar->buildFromDirectory(dirname(__FILE__) . '/project', '/\.php$/');
```

### RarArchiver::deleteIndex

#### Description
```php
boolean RarArchiver::deleteIndex ( int $index )
```
Delete an entry in the archive using its index.

#### Parameters
* `index` - Index of the entry to delete.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->deleteIndex(2)) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::deleteName

#### Description
```php
boolean RarArchiver::deleteName ( string $name )
```
Delete an entry in the archive using its name.

#### Parameters
* `name` - Name of the entry to delete.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->deleteName('testfromfile.php')) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::getFromIndex

#### Description
```php
string RarArchiver::getFromIndex ( int $index [, int $length = 0] )
```
Returns the entry contents using its index.

#### Parameters
* `index` - Index of the entry.
* `length` - The length to be read from the entry. If 0, then the entire entry is read.

#### Return Values
Returns the contents of the entry on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

$rar->getFromIndex(2);
// or
$rar->getFromIndex(2, 100);
```

### RarArchiver::getFromName

#### Description
```php
string RarArchiver::getFromName ( string $name [, int $length = 0] )
```
Returns the entry contents using its name.

#### Parameters
* `name` - Name of the entry.
* `length` - The length to be read from the entry. If 0, then the entire entry is read.

#### Return Values
Returns the contents of the entry on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

$rar->getFromName('testfromfile.php');
// or
$rar->getFromIndex('testfromfile.php', 100);
```

### RarArchiver::getNameIndex

#### Description
```php
string RarArchiver::getNameIndex ( int $index )
```
Returns the name of an entry using its index.

#### Parameters
* `index` - Index of the entry.

#### Return Values
Returns the name on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

$rar->getNameIndex(2);
```

### RarArchiver::renameIndex

#### Description
```php
boolean RarArchiver::renameIndex ( int $index , string $newname )
```
Renames an entry defined by its index.

#### Parameters
* `index` - Index of the entry to rename.
* `newname` - New name.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->renameIndex(2, 'newname.php')) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::renameName

#### Description
```php
boolean RarArchiver::renameName ( string $name , string $newname )
```
Renames an entry defined by its name.

#### Parameters
* `name` - Name of the entry to rename.
* `newname` - New name.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->renameName('testfromfile.php', 'newname.php')) {
    echo 'ok';
} else {
	echo 'failed';
}
```

### RarArchiver::extractTo

#### Description
```php
boolean RarArchiver::extractTo ( string $destination [, mixed $entries ] )
```
Extract the complete archive or the given files to the specified destination.

#### Parameters
* `destination` - Location where to extract the files.
* `entries` - The entries to extract. It accepts either a single entry name or an array of names.

#### Return Values
Returns TRUE on success or FALSE on failure.

#### Examples
```php
$rar = new RarArchiver('example.rar', RarArchiver::CREATE);

if ($rar->extractTo('/my/destination/dir/')) {
    echo 'ok';
} else {
	echo 'failed';
}
```
