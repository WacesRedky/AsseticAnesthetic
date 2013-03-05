<?php

/**
* Part of the FuelPHP framework.
*
* @package    RobMcCann\AsseticAnesthetic
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
        $set = array();

        switch($type) {
            case static::CSS:
                $set["groups.css.$groupName.enabled"] = true;
                break;
            case static::JAVASCRIPT:
                $set["groups.js.$groupName.enabled"] = true;
                break;
            case null:
                $set["groups.css.$groupName.enabled"] = true;
                $set["groups.js.$groupName.enabled"] = true;
                break;
            default:
                throw new \OutOfBoundsException('The provided type does not exist');
        }

        Arr::set($this->config, $set);
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
        $set = array();

        switch($type) {
            case static::CSS:
                $set["groups.css.$groupName.enabled"] = false;
                break;
            case static::JAVASCRIPT:
                $set["groups.js.$groupName.enabled"] = false;
                break;
            case null:
                $set["groups.css.$groupName.enabled"] = false;
                $set["groups.js.$groupName.enabled"] = false;
                break;
            default:
                throw new \OutOfBoundsException('The provided type does not exist');
        }

        Arr::set($this->config, $set);
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
        return $this->render($group, static::CSS);
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
        return $this->render($group, static::JAVASCRIPT);
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
       
        foreach ($this->config['groups'] as $configType) {
            if (!isset($this->config['groups'][$configType])) {
                continue;
            }

            if ($type !== null and $type !== $configType) {
                continue;
            }

            foreach($this->config['groups']['type'] as $name => $group) {
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
            return '';
        }
        
        // has the group already been rendered?
        if (isset($this->rendered[$type][$name]) and $this->rendered[$type][$name]) {
            return '';
        }

        $this->rendered[$type][$name] = true;

        $assetCollection = $this->arrayToCollection($params, $type);

        $html = '';

        $tagFunctionName = $type.'Tag';
        // no filters or compression, just spit out the tags
        if (!isset($params['filters']) or !$params['filters']) {

            foreach($assetCollection->all() as $asset) {
                $html .= $this->$tagFunctionName($asset->path());
            }

            return $html;
        }


        foreach ($filters as $filter) {
            if ('?' != $filter[0]) {
                $collection->ensureFilter($factory->getFilterManager()->get($filter));
            } elseif (!$config['debug']) {
                $collection->ensureFilter($factory->getFilterManager()->get(substr($filter, 1)));
            }
        }
        $collection->setTargetPath(
            str_replace(
                '*',
                $factory->generateAssetName($config['groups'][$key][$title]['files'], $filters, $options),
                $options['output']
            )
        );
        

        $cache = new AssetCache(
            $collection,
            new FilesystemCache(self::_cache_path().'/cache')
        );

        self::getAssetWriter()->writeAsset($cache);
        $out .= static::$func('/assets/cache/'.$collection->getTargetPath());
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
        
        return realpath(__DIR__ . '/../../public/assets');
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

            $url = $this->parseFileConfig($file, $key);

            if (
                $this->strStartsWith($url, '//') or 
                $this->strStartsWith($url, 'http://') or 
                $this->strStartsWith($url, 'https://') 
            ) {
                $files[] = new HttpAsset($url);
            } else {
                $files[] = new GlobAsset(substr($url,1));
            }
        }

        return new AssetCollection($files);
    }

    /**
     * Parses an item in the files array and returns it's path relative to the assets directory
     * 
     * @param  string $path The path to a file either relative to the assets directory or the custom path.
     * @param  string $type either AssetManager::CSS or AssetManager::JAVASCRIPT
     * @return string       the url path of the asset.
     */
    protected function parseFileConfig($path, $type) {
        $config = self::$config;

        $pos = strpos($path, '::');

        if($pos !== false)
        {
            foreach($config['paths'] as $key => $location)
            {
                if(substr($path, 0, $pos) == $key)
                {
                    if (is_array($location))
                    {
                        $type = Arr::get($location, $type.'_dir', $type);

                        if ($type and substr($type, -1) != '/')
                        {
                            $type .= '/';
                        }

                        $location = $location['path'];
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

    protected static function javascriptTag($url) {
        return '<script type="text/javascript" src="'.$url.'"></script>';
    }
}