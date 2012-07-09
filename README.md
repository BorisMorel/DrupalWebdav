Webdav server for Drupal
========================

DrupalWebdav provide a webdav sharing integrated with Drupal7. With this, you upload directly the files from a sharing. You can use this module to manage your files.

Install
-------

1. Download
2. Install Deps
3. Configure
4. Install
5. Tips
6. TODO

### Get the module

``` bash
$ git clone https://github.com/BorisMorel/DrupalWebdav.git
```

### Install the dependencies
#### Ezcomponent
EzComponent implements all webdav methods

##### Download the latest stable version on : http://ezcomponents.org/download
##### Extract

``` bash
$ tar xvjf ezcomponents-x-x.tar.bz2
```

##### Copy folder to DrupalWebdav/vendors

```bash
mkdir DrupalWebdav/vendors/ezcomponents/
cp -r ezcomponents-x-x/Webdav ezcomponents-x-x/autoload ezcomponents-x-x/Base DrupalWebdav/vendors/ezcomponents/
```

### Configure DrupalWebdav module
You can define the default node for the webdav entrypoint. ( eq : DocumentRoot ).

Edit DrupalWebdav/davsrv.module

#### Fixed entrypoint

``` php
<?php
// davsrv.module

define("DOCUMENT_ROOT", '/sharing');
```

**Note:**
`sharing` is the name of my drupal node_type. You MUST define this node into Drupal and you MUST added a file_upload field.

#### Many entrypoints

``` php
<?php 
// davsrv.module

define("DOCUMENT_ROOT", '/');

$router = new Router();
$router->setRootCollection(array('sharing', 'partage'));
```

**Note:**
`sharing` and `partage` MUST exist into Drupal7 node_type.

### Install into Drupal7

Just go into Drupal web interface. Choose `modules` menu and install dav server.

### Tips

You can check the file extensions.

```php
<?php
// davsrv.module

$backend = Backend::getInstance()
    ->setExtensionCheck()
    ;
```

### TODO

If Drupal file_upload field configuration restrict the maximum number of files, I need to prevent the user when the limit is exceeded.
