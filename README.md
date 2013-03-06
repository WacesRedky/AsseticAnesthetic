AsseticAnesthetic
=================

An abstraction layer to enable managing of assets via a config array. A different way of implementing Assetic.

Installation
-------------

Installation is via Composer. Add the following to your ```composer.json``` file.

```
{
    "require": {
        "rob-mccann/asseticanesthetic": "0.1"
    }
}
```

Run ```composer install``` 

Features
--------

1. Generate assets based on configuration
2. Keeps MVC, no need for <script src="assets.php">
3. Quick to install and use
4. Ideal for small, quick projects where delivery is a higher requirement than raw performance

Usage
-----

This project is framework independent. You can use it in almost any PHP project.
First, you'll need to create a new object and pass it in our wonderful config array (see the ```examples``` to see what to pass in here).

```
$assets = new \AsseticAnesthetic\SimpleAssetManager($config);
```

You can then call ``` $assets->renderJs() ``` and ``` $assets->renderCss() ``` to render the HTML tags.

Most of the time, you'll want to load $config from your frameworks Config class. In Laravel and FuelPHP, it's something along the lines of ```$config = Config::read('assets')```.

### Enabling / Disabling groups

Before you call the render functions, you can override your config to enable or disable groups.
```
// the following will enable jQuery UI if it exists, but
// will then disable the jquery-ui css from being processed and shown
$assets->enable('jquery-ui');
$assets->disable('jquery-ui', SimpleAssetManager::CSS);
```

Todo
----

1. Write proper tests
2. Implement more filters
3. Improve documentation
