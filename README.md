kohana-sqlite-database
======================

A SQLite database driver for the Kohana V3 database module.

This driver is different from most others available, in that it uses the actual PHP SQLite extension.  It is NOT an extension of the PDO driver.


Requirements
------------

The PHP SQLite extension is required for this module.

From the [PHP manual](http://www.php.net/manual/en/sqlite.requirements.php):

> The SQLite extension is enabled by default as of PHP 5.0. Beginning with PHP 5.4, the SQLite extension is available only via PECL.


Installation
------------

*Step 1*

Download the files

*Step 2*

Create a folder 'sqlite', in your modules folder, and copy in the downloaded files

*Step 3*

Enable the module within your 'bootstrap.php' file

Add
    'sqlite' => MODPATH.'sqlite',

wthin the array
    Kohana::modules(array(



Configuration
-------------

    return array
    (
        'default' => array
        (
            'type'         => 'sqlite',
            'connection'   => array(
                'database' => realpath('.').'/database/database.sqlite'
            ),
            'table_prefix' => '',
            'charset'      => NULL,
            'caching'      => FALSE,
            'profiling'    => TRUE,
        )
    )
    
