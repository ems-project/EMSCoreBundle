<?php

namespace EMS\CoreBundle\Service\Storage;

use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use function fopen;
use function is_resource;
use function ssh2_auth_pubkey_file;
use function ssh2_sftp_unlink;

class SftpStorage implements StorageInterface
{

    private $host;
    private $path;
    private $port;

    private $username;
    private $publicKeyFile;
    private $privateKeyFile;
    private $passwordPhrase;

    private $connection;
    private $sftp;

    private $contextSupport;

    public function __construct(string $host, string $path, string $username, string $publicKeyFile, string $privateKeyFile, bool $contextSupport = false, $passwordPhrase = null, int $port = 22)
    {
        $this->host = $host;
        $this->path = $path;
        $this->port = $port;

        $this->username = $username;
        $this->publicKeyFile = $publicKeyFile;
        $this->privateKeyFile = $privateKeyFile;
        $this->passwordPhrase = $passwordPhrase;

        $this->contextSupport = $contextSupport;

        $this->connection = false;
        $this->sftp = false;
    }

    /**
     * @param string $hash
     * @param string $filename
     * @param bool $cacheContext
     * @return bool
     * @throws Exception
     */
    public function create($hash, $filename, $cacheContext = false)
    {
        if ($cacheContext && !$this->contextSupport) {
            return false;
        }
        $this->init();
        if (is_resource($this->connection)) {
            return ssh2_scp_send($this->connection, $filename, $this->getPath($hash, $cacheContext), 0644);
        }
        return false;
    }

    /**
     * @throws Exception
     */
    private function init()
    {

        if (!function_exists('ssh2_connect')) {
            throw new Exception("PHP functions Secure Shell are required by $this. (ssh2)");
        }

        if ($this->connection === false) {
            $this->connection = @ssh2_connect($this->host, $this->port);
            if (!$this->connection) {
                throw new Exception("Could not connect to $this->host on port $this->port.");
            }
            ssh2_auth_pubkey_file($this->connection, $this->username, $this->publicKeyFile, $this->privateKeyFile, $this->passwordPhrase);
        }

        if ($this->sftp === false) {
            $this->sftp = @ssh2_sftp($this->connection);
        }
    }

    /**
     * @param $hash
     * @param null $cacheContext
     * @return string
     * @throws Exception
     */
    private function getPath($hash, $cacheContext = null)
    {
        $out = $this->path;
        if ($cacheContext) {
            $out .= DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $cacheContext;
        }
        $out .= DIRECTORY_SEPARATOR . substr($hash, 0, 3);

        $this->init();
        if (is_resource($this->sftp)) {
            @ssh2_sftp_mkdir($this->sftp, $out, 0777, true);
        } else {
            throw new Exception('EMS was not able to initiate the sftp connection');
        }

        return $out . DIRECTORY_SEPARATOR . $hash;
    }

    /**
     * @return bool
     */
    public function supportCacheStore()
    {
        return $this->contextSupport;
    }

    /**
     * @param string $hash
     * @param bool $cacheContext
     * @return bool|resource
     * @throws Exception
     */
    public function read($hash, $cacheContext = false)
    {
        if ($cacheContext && !$this->contextSupport) {
            return false;
        }
        $this->init();
        return fopen('ssh2.sftp://' . intval($this->sftp) . $this->getPath($hash, $cacheContext), 'r');
    }

    /**
     * @param string $hash
     * @param bool $cacheContext
     * @return bool|int
     * @throws Exception
     */
    public function getLastUpdateDate($hash, $cacheContext = false)
    {
        if ($cacheContext && !$this->contextSupport) {
            return false;
        }
        $this->init();

        if (is_resource($this->sftp)) {
            $statisticalInformation = ssh2_sftp_stat($this->sftp, $this->getPath($hash, $cacheContext));
            if ($statisticalInformation) {
                return $statisticalInformation['mtime'];
            }
        }
        return false;
    }

    /**
     * @param string $hash
     * @param bool $cacheContext
     * @return bool|int
     * @throws Exception
     */
    public function getSize($hash, $cacheContext = false)
    {
        if ($cacheContext && !$this->contextSupport) {
            return false;
        }
        $this->init();

        if (is_resource($this->sftp)) {
            $statisticalInformation = ssh2_sftp_stat($this->sftp, $this->getPath($hash, $cacheContext));
            if ($statisticalInformation) {
                return $statisticalInformation['size'];
            }
        }
        return false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return SftpStorage::class . " ($this->host)";
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function clearCache()
    {
        $this->init();
        $fileSystem = new Filesystem();
        $fileSystem->remove('ssh2.sftp://' . intval($this->sftp) . $this->path . DIRECTORY_SEPARATOR . 'cache');
        return false;
    }

    /**
     * @param $hash
     * @return bool
     * @throws Exception
     */
    public function remove($hash)
    {
        if ($this->head($hash)) {
            if (is_resource($this->sftp)) {
                ssh2_sftp_unlink($this->sftp, $this->getPath($hash));
            }
        }
        $finder = new Finder();
        $finder->name($hash);
        foreach ($finder->in('ssh2.sftp://' . intval($this->sftp) . $this->path . DIRECTORY_SEPARATOR . 'cache') as $file) {
            if (is_resource($this->sftp)) {
                ssh2_sftp_unlink($this->sftp, $file);
            }
        }
        return true;
    }

    /**
     * @param string $hash
     * @param bool $cacheContext
     * @return bool
     * @throws Exception
     */
    public function head($hash, $cacheContext = false)
    {
        if ($cacheContext && !$this->contextSupport) {
            return false;
        }
        $this->init();
        try {
            if ($this->sftp && is_resource($this->sftp)) {
                if (is_resource($this->sftp)) {
                    $statisticalInformation = @ssh2_sftp_stat($this->sftp, $this->getPath($hash, $cacheContext));
                    return $statisticalInformation ? true : false;
                }
            }
        } catch (Exception $e) {
        }
        return false;
    }
}
