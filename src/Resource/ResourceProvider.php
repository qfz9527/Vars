<?php

/**
 * This file is part of the m1\vars library
 *
 * (c) m1 <hello@milescroxford.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package     m1/vars
 * @version     0.1.0
 * @author      Miles Croxford <hello@milescroxford.com>
 * @copyright   Copyright (c) Miles Croxford <hello@milescroxford.com>
 * @license     http://github.com/m1/vars/blob/master/LICENSE
 * @link        http://github.com/m1/vars/blob/master/README.MD Documentation
 */

namespace M1\Vars\Resource;

use M1\Vars\Loader\DirectoryLoader;
use M1\Vars\Vars;

/**
 * Vars Resource Provider to separate the different formats of config (array/file/dir) into one resource
 *
 * @since 0.1.0
 */
class ResourceProvider extends AbstractResource
{

    /**
     * The configuration entity -- could be a file, array or dir
     *
     * @var array|string
     */
    private $entity;

    /**
     * If the import was not relative then the content will be stored in this array
     *
     * @var array
     */
    private $parent_content = array();

    /**
     * Is the import relative
     *
     * @var bool
     */
    private $relative;

    /**
     * The parent Vars class
     *
     * @var \M1\Vars\Vars
     */
    public $vars;

    /**
     * The ResourceProvider constructor creates the content from the entity
     *
     * @param \M1\Vars\Vars $vars     The calling Vars class
     * @param string|array  $entity   The configuration entity
     * @param bool          $relative Is the entity relative to the calling entity or class
     *
     * @throws \InvalidArgumentException If the entity passed is not a string or array
     */
    public function __construct(Vars $vars, $entity, $relative = true)
    {
        if (!is_string($entity) && !is_array($entity)) {
            throw new \InvalidArgumentException('You can only pass strings or arrays as Resources');
        }

        $this->vars = $vars;
        $this->entity = $entity;
        $this->relative = $relative;

        $this->createContent($entity);
    }

    /**
     * Creates the content from the entity
     *
     * @param string|array $entity The configuration entity
     */
    private function createContent($entity)
    {
        $type = gettype($entity);
        $resources = $this->processEntity($entity, $type);

        if ($resources && !empty($resources)) {
            foreach ($resources as $resource) {
                if ($type === "string") {
                    $this->vars->pathsLoadedCheck($resource);

                    if ($this->vars->cache->checkCache()) {
                        return;
                    }

                    if ($this->vars->resourceImported($resource)) {
                        continue;
                    }
                    $pos = $this->vars->addResource($resource);
                    $resource = new FileResource($this, $resource);
                    $this->vars->updateResource($resource, $pos);
                } else {
                    $resource = new ResourceProvider($this->vars, $resource);
                }

                $this->addContent($resource->getContent());
            }
        }
    }

    /**
     * Creates the content from the entity
     *
     * @param string|array $entity The configuration entity
     * @param string       $type   The type of entity
     *
     * @throws \InvalidArgumentException If the entity is not array|file, is readable or exists
     *
     * @returns array The array of resources
     */
    private function processEntity($entity, $type)
    {
        $resources = $entity;

        if ($type === 'string') {
            if (is_file($entity)) {
                $resources = array($entity);
            } elseif (is_dir($entity)) {
                $resources = $this->getSupportedFilesInDir();
            } else {
                throw new \InvalidArgumentException(sprintf("'%s' does not exist or is not readable", $entity));
            }
        }

        return $resources;
    }

    /**
     * Adds content to the parent contents
     *
     * @param array $content The content from the resource
     */
    private function addContent($content)
    {
        if ($this->relative) {
            $this->content = $this->mergeContents($this->content, $content);
        } else {
            $this->parent_content = $this->mergeContents($this->parent_content, $content);
        }
    }

    /**
     * Returns the supported files using the extensions from the loaders in the entity which is a directory
     *
     * @see \M1\Vars\Loader\LoaderProvider::getExtensions() \M1\Vars\Loader\LoaderProvider::getExtensions()
     * @see \M1\Vars\Loader\LoaderProvider::makeLoaders() \M1\Vars\Loader\LoaderProvider::makeLoaders()
     *
     * @return array|bool Returns the supported files or false if no files were found
     */
    private function getSupportedFilesInDir()
    {
        $dir_loader = new DirectoryLoader($this->entity);
        $dir_loader->setSupports($this->vars->loader->getExtensions());
        $dir_loader->load();

        return $dir_loader->getContent();
    }

    /**
     * Merges various configuration contents into one array
     *
     * @param mixed $contents,... Optional number of arrays to merge
     *
     * @return array The merged contents
     */
    private function mergeContents()
    {
        $contents = func_get_args();
        return call_user_func_array('array_replace_recursive', $contents);
    }

    /**
     * Adds content to the parent content array
     *
     * @param array $content The content to add
     */
    public function addParentContent(array $content)
    {
        $this->parent_content = array_merge_recursive($this->parent_content, $content);
    }

    /**
     * Merges the content and the parent content together
     *
     * @return \M1\Vars\Resource\ResourceProvider
     */
    public function mergeParentContent()
    {
        $this->content = $this->mergeContents($this->content, $this->parent_content);

        return $this;
    }

    /**
     * Returns the parent content
     *
     * @return mixed The parent content
     */
    public function getParentContent()
    {
        return $this->parent_content;
    }
}
