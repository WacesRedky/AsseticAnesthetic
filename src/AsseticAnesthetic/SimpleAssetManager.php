<?php

/**
* A simple wrapper for Assetic that speeds up development time.
* An abstraction layer to enable managing of assets via a config array. A different way of implementing Assetic.
*
* @package    AsseticAnesthetic
* @version    0.1
* @license    MIT License
* @copyright  2013 Rob McCann
*/

namespace AsseticAnesthetic;

use Assetic\AssetManager;
use Assetic\AssetWriter;
use Assetic\Asset\GlobAsset;
use Assetic\Asset\HttpAsset;
use Assetic\Factory\AssetFactory;
use Assetic\Factory\Worker\CacheBustingWorker;
use Assetic\Filter\Yui\CssCompressorFilter;
use Assetic\FilterManager;
use Assetic\Filter\Yui;
use Assetic\Filter\CssRewriteFilter;
use Assetic\Filter\CompassFilter;
use Assetic\Cache\FilesystemCache;
use Assetic\Asset\AssetCache;
use Assetic\Asset\AssetCollection;
use FuelPHP\Common;
use FuelPHP\Common\Arr;

class SimpleAssetManager {

    const JAVASCRIPT = 'js';
    const CSS = 'css';

    // holds the config array that's passed in the constructor
    protected $config = array();

    // the list of groups and types that have already been rendered.
    // prevents duplicate html tags.
    protected $rendered = array(
        'js' => array(),
        'css' => array(),
    );

    protected $filterManager = null;
    protected $assetFactory = null;
    protected $assetWriter = null;

    protected $filters = array();



    /**
    * Instanciates the AssetManager. For the most part, you'll only ever need one instance.
    * @param array $config the config array that contains your assets and options
    */
    public function __construct(array $config = array()) {
        $this->config = $config;
    }

    /**
     * Enabling an asset group will cause it's filters to be run.
     * It's tag will be output when calling the render functions too.
     * 
     * @param  string $groupName    the name of the group to enable.
     * @param  int $type            either null, AssetManager::JAVASCRIPT or AssetManager::CSS
     * @return AssetManager         returns itself for method chaining
     */
    public function enable($groupName, $type = null) {

        switch($type) {
            case static::CSS:
                Arr::set($this->config, "groups.css.$groupName.enabled", true);
                break;
            case static::JAVASCRIPT:
                Arr::set($this->config, "groups.js.$groupName.enabled", true);
                break;
            case null:
                Arr::set($this->config, "groups.css.$groupName.enabled", true);
                Arr::set($this->config, "groups.js.$groupName.enabled", true);
                break;
            default:
                throw new \OutOfBoundsException('The provided type does not exist');
        }
       
        return $this;
    }

    /**
     * Disabling an asset group will prevent it's filters from being run.
     * The group will no longer show in the render functions.
     * 
     * @param  string $groupName    the name of the group to disable
     * @param  int $type            either null, AssetManager::JAVASCRIPT or AssetManager::CSS
     * @return AssetManager         returns itself for method chaining
     */
    public function disable($groupName, $type = null) {

       switch($type) {
            case static::CSS:
                Arr::set($this->config, "groups.css.$groupName.enabled", false);
                break;
            case static::JAVASCRIPT:
                Arr::set($this->config, "groups.js.$groupName.enabled", false);
                break;
            case null:
                Arr::set($this->config, "groups.css.$groupName.enabled", false);
                Arr::set($this->config, "groups.js.$groupName.enabled", false);
                break;
            default:
                throw new \OutOfBoundsException('The provided type does not exist');
        }
        return $this;
    }

    /**
     * Renders the HTML CSS stylesheet <link> tags for enabled groups.
     * If the group is disabled, an empty string is returned.
     *
     * If no group is provided, all enabled groups' tags will be returned.
     * 
     * @param  string $groupName the name of the group whose tag you wish to return. Passing no argument or
     *                           null will return all enabled CSS tags.
     * @return string            the <link> html tags to the relevant CSS.
     */
    public function renderCss($groupName = null) {
        return $this->render($groupName, static::CSS);
    }

    /**
     * Renders the HTML <script> tags for enabled groups.
     * If the group is disabled, an empty string is returned.
     *
     * If no group is provided, all enabled groups' tags will be returned.
     * 
     * @param  string $groupName the name of the group whose tag you wish to return. Passing no argument or
     *                           null will return all enabled groups' JS tags.
     * @return string            the <script> html tags to the relevant JS files.
     */
    public function renderJs($groupName = null) {
        return $this->render($groupName, static::JAVASCRIPT);
    }

    /**
     * Parses the config, runs filters, concatenates, caches. Basically does all the leg work.
     *
     * Passing null for both arguments will return all JS and CSS tags for all enabled groups.
     *
     * Each group may only be rendered once. Subsequent calls that render that group will return the empty string.
     * 
     * @param  string $groupName the name of the group to parse and return the tag for. null returns all groups of $type
     * @param  int    $type      Either null, AssetManager::JAVASCRIPT or AssetManager::CSS. Null will return 
     * @return [type]            [description]
     */
    public function render($groupName = null, $type = null) {
        
        $html = '';

        if (!isset($this->config['groups'])) {
            return '';
        }
       
        foreach (array_keys($this->config['groups']) as $configType) {
            if (!isset($this->config['groups'][$configType])) {
                continue;
            }

            if ($type !== null and $type !== $configType) {
                continue;
            }

            if ($groupName !== null) {
                $html .= $this->renderGroup($groupName, $this->config['groups'][$configType][$groupName], $configType);
                continue;
            }
            foreach($this->config['groups'][$configType] as $name => $group) {
                $html .= $this->renderGroup($name, $group, $configType);
            }
        }

        return $html;
    }

    /**
     * Renders the individual group
     * @param  [type] $group [description]
     * @param  [type] $type  [description]
     * @return [type]        [description]
     */
    protected function renderGroup($name, $params, $type) {
        // is the group enabled?
        if ($params['enabled'] === false) {
            echo 'asdf';
            return '';
        }
        
        // has the group already been rendered?
        if (isset($this->rendered[$type][$name]) and $this->rendered[$type][$name]) {
            return '';
        }

        $this->rendered[$type][$name] = true;


        $html = '';

        $tagFunctionName = $type.'Tag';
        // no filters or compression, just spit out the tags
        if (!isset($params['filters']) or !$params['filters']) {   
            foreach($params['files'] as $file) {
                $url = $this->parseFileConfig($file, $type);
                $html .= $this->$tagFunctionName($url);
            }

            return $html;
        }
        $assetCollection = $this->arrayToCollection($params, $type);
        
        $options = array('output' => '*.'.$type);

        $factory = $this->getAssetFactory();

        $assetCollection->setTargetPath(
            str_replace(
                '*',
                $factory->generateAssetName($params['files'], $params['filters'], $options),
                $options['output']
            )
        );

        $html = $this->$tagFunctionName('/assets/cache/'.$assetCollection->getTargetPath());


        foreach ($params['filters'] as $filter) {
            $this->getFactoryWithFilter($filter);
            $assetCollection->ensureFilter($this->filters[$filter]);
        }

        $cache = new AssetCache(
            $assetCollection,
            new FilesystemCache($this->cachePath().'/cache')
        );

        self::getAssetWriter()->writeAsset($cache);

        return $html;
    }

    protected function getFactoryWithFilter($filterName) {

        // normalise
        $filterName = strtolower($filterName);
        $factory = $this->getAssetFactory();
        if (!array_key_exists($filterName, $this->filters)) {
            // get the object
            $func = 'getFilter'.ucfirst($filterName);
            $this->filters[$filterName] = $this->$func();

            $factory->getFilterManager()->set($filterName,  $this->filters[$filterName]);
        }

        return $factory;
    }

    protected function getFilterYuicss() {
        return new Yui\CssCompressorFilter($this->config['yuicompressor']);
    }

    protected function getFilterYuijs() {
        return new Yui\JsCompressorFilter($this->config['yuicompressor']);
    }

    protected function getFilterCompass() {
        $assetPath = $this->assetPath();
        $cachePath = $this->cachePath();
        $compass = new CompassFilter('/usr/local/bin/compass');
        $compass->setHttpPath('/assets/');
        $compass->setImagesDir($assetPath.'/img');
        $compass->setHttpGeneratedImagesPath('img');
        $compass->setGeneratedImagesPath($cachePath .'/img');
        $compass->setJavascriptsDir($assetPath.'/js');
        $compass->setHttpJavascriptsPath('assets/js');
        $compass->setNoCache(true);
        $compass->setScss(true);

        return $compass;
    }

    protected function getAssetFactory() {
        // TODO get rid of singletons
        if ($this->assetFactory === null) {
            $this->assetFactory = new AssetFactory($this->cachePath());
            $this->assetFactory->setFilterManager($this->getFilterManager());
        }
        return $this->assetFactory;
    }

    protected function getFilterManager() {
        // TODO get rid of singletons
        if ($this->filterManager === null) {
            $this->filterManager = & new FilterManager();
        }

        return $this->filterManager;
    }

    protected function getAssetWriter()
    {
        if ($this->assetWriter === null) {
            $this->assetWriter = & new AssetWriter($this->cachePath());
        }
        return $this->assetWriter;
    }

    /**
     * Where your assets are kept. We automatically append js/css to your paths so no need to include those.
     *
     * You can specify this in your config or overload this method to change it.
     *
     * This does *not* return urls to assets. If you're not sure, you should probably be calling render() rather than this.
     * 
     * @return string the directory on the filesystem that the assets are stored.
     */
    protected function assetPath() {
        if (isset($this->config['assetPath']))
        {
            return $this->config['assetPath'];
        }
        return realpath('./assets');
    }

    /**
     * This returns the URL to the default asset directory.
     * 
     * @return [type] [description]
     */
    protected function assetUrl() {
        if (isset($this->config['assetUrl']))
        {
            return $this->config['assetUrl'];
        }

        return '/assets';
    }

    /**
     * Where to store minified versions.
     * The default for this should suffice; it's relative to the assetPath which you may wish to change.
     *
     * This directory needs to be writable.
     *
     * This does *not* return a URL.
     * 
     * @return string the path on the filesystem where the cached files are stored.
     */
    protected function cachePath() {
        if (isset($this->config['cachePath']))
        {
            return $this->config['cachePath'];
        }

        return $this->assetPath() . '/cache';
    }

    /**
     * Converts the group array to use Assetic's asset objects. Sticks them all in an AsseticCollection.
     * @param  string $group the group to deal with
     * @param  string $type  Either AssetManager::CSS or AssetManager::JAVASCRIPT
     * @return AssetCollection        an Assetic collection of GlobAssets/HttpAssets
     */
    protected function arrayToCollection($group, $type) {
        if (!isset($group['files'])) {
            throw new \OutOfBoundsException('Tried to render a group that has no files.');
        }

        $files = array();
        foreach($group['files'] as $file) {

            $url = $this->parseFileConfig($file, $type);
            if (
                $this->strStartsWith($url, '//') or 
                $this->strStartsWith($url, 'http://') or 
                $this->strStartsWith($url, 'https://') 
            ) {
                $files[] = new HttpAsset($url);
            } else if($this->strStartsWith($url, '..')) {
                $files[] = new GlobAsset(realpath($url));
            } else {
                $files[] = new GlobAsset(substr($url,1));
            }
        }

        return new AssetCollection($files);
    }

    protected function strStartsWith($haystack, $needle)
    {
        return !strncmp($haystack, $needle, strlen($needle));
    }

    /**
     * Parses an item in the files array and returns it's path relative to the assets directory
     * 
     * @param  string $path The path to a file either relative to the assets directory or the custom path.
     * @param  string $type either AssetManager::CSS or AssetManager::JAVASCRIPT
     * @return string       the url path of the asset.
     */
    protected function parseFileConfig($path, $type) {
        $config = $this->config;

        if (is_array($path)) {
            $path = $path[0];
        }

        $pos = strpos($path, '::');

        if($pos !== false)
        {
            foreach($config['paths'] as $key => $location)
            {
                if(substr($path, 0, $pos) == $key)
                {

                    
                    if (is_array($location))
                    {
                    $type = Arr::get($location, $type.'_dir', $type .'/');
                        

                        $location = $location['path'];
                    }

                    if ($type and substr($type, -1) != '/')
                    {
                        $type .= '/';
                    }

                    // a little something for google web fonts
                    if(substr($path, $pos+2,1) == '?')
                    {
                        $type = rtrim($type, '/');
                    }
                    $path = $location . $type .substr($path, $pos+2);

                    break;
                }
            }
        }
        else
        {
            $path = $this->assetUrl() . '/'.$type.'/'.$path;
        }

        return $path;
    }

    protected static function cssTag($url) {
        return '<link rel="stylesheet" href="'.$url.'">';
    }

    protected static function jsTag($url) {
        return '<script type="text/javascript" src="'.$url.'"></script>';
    }
}