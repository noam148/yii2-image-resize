Image resize for Yii2
========================

A Yii2 component for resizing images and store it in a cache folder

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

* Either run

```
php composer.phar require "noam148/yii2-image-resize" "*"
```
or add

```json
"noam148/yii2-image-resize" : "*"
```

to the require section of your application's `composer.json` file.

* Add a new component in `components` section of your application's configuration file, for example:

```php
'components' => [
    'imageresize' => [
		'class' => 'noam148\imageresize\ImageResize',
		//path relative web folder. In case of multiple environments (frontend, backend) add more paths 
		'cachePath' =>  ['assets/images', '../../frontend/web/assets/images'],
		//use filename (seo friendly) for resized images else use a hash
		'useFilename' => true,
		//show full url (for example in case of a API)
		'absoluteUrl' => false,
	],
],
```

Usage
-----

If you want to get a image url:

```php
/*
 * $sImageFilePath_id: (required) path to file
 * $width/$height: (required) width height of the image
 * $mode: "outbound", "inset" or "{horz}:{vert}" where {horz} is one from "left", "cetrer", "right" and {vert} is one from "top", "center", "bottom"
 * $$quality: (1 - 100)
 * $chosenFileName: if config -> components -> imageresize -> useFilename is true? its an option to give a custom name else use original file name
 */
\Yii::$app->imageresize->getUrl($sImageFilePath, $width, $height, $mode, $quality, $chosenFileName);
```

**If you got questions, tips or feedback? Please, let me know!**
