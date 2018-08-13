<?php

namespace EMS\CoreBundle\Service\Storage;

interface StorageInterface {

    /**
     * @param string $sha1
     * @param bool|string $cacheContext
     * @return bool
     */
	public function head($sha1, $cacheContext=false);

    /**
     * @param string $sha1
     * @param string $filename
     * @param bool|string $cacheContext
     * @return bool
     */
	public function create($sha1, $filename, $cacheContext=false);

    /**
     * @param string $sha1
     * @param bool|string $cacheContext
     * @return resource|bool
     */
	public function read($sha1, $cacheContext=false);

    /**
     * @param string $sha1
     * @param bool|string $cacheContext
     * @return integer
     */
    public function getLastUpdateDate($sha1, $cacheContext=false);

    /**
     * @param string $sha1
     * @param bool|string $cacheContext
     * @return integer
     */
    public function getSize($sha1, $cacheContext=false);

    /**
     * @return bool
     */
	public function supportCacheStore();
}