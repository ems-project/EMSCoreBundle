<?php

namespace EMS\CoreBundle\Service\Storage;

interface StorageInterface {

    /**
     * @param string $hash
     * @param bool|string $cacheContext
     * @return bool
     */
	public function head($hash, $cacheContext=false);

    /**
     * @param string $hash
     * @param string $filename
     * @param bool|string $cacheContext
     * @return bool
     */
	public function create($hash, $filename, $cacheContext=false);

    /**
     * @param string $hash
     * @param bool|string $cacheContext
     * @return resource|bool
     */
	public function read($hash, $cacheContext=false);

    /**
     * @param string $hash
     * @param bool|string $cacheContext
     * @return integer
     */
    public function getLastUpdateDate($hash, $cacheContext=false);

    /**
     * @param string $hash
     * @param bool|string $cacheContext
     * @return integer
     */
    public function getSize($hash, $cacheContext=false);

    /**
     * @return bool
     */
	public function supportCacheStore();

    /**
     * @return bool
     */
	public function clearCache();
}