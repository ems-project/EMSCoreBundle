<?php

namespace EMS\CoreBundle\Service\Storage;



use Exception;
use function fopen;
use function ssh2_auth_pubkey_file;
use function ssh2_sftp_unlink;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class SftpStorage implements StorageInterface {

    private $host;
    private $path;
    private $port;

    private $username;
    private $pubkeyfile;
    private $privkeyfile;
    private $passphrase;

    private $connection;
    private $sftp;

    private $contextSupport;

	public function __construct(string $host, string $path, string $username , string $pubkeyfile , string $privkeyfile, bool $contextSupport = false, $passphrase = null, int $port=22)
    {
        $this->host = $host;
        $this->path = $path;
        $this->port = $port;

        $this->username = $username;
        $this->pubkeyfile = $pubkeyfile;
        $this->privkeyfile = $privkeyfile;
        $this->passphrase = $passphrase;

        $this->contextSupport = $contextSupport;

        $this->connection = false;
        $this->sftp = false;
	}

    /**
     * @throws Exception
     */
	private function init()
    {

        if(!function_exists('ssh2_connect')){
            throw new Exception("PHP fonctions Shell are required by $this.");
        }

        if($this->connection === false)
        {
            $this->connection = @ssh2_connect($this->host, $this->port);
            if (! $this->connection)
            {
                throw new Exception("Could not connect to $this->host on port $this->port.");
            }
            ssh2_auth_pubkey_file($this->connection, $this->username, $this->pubkeyfile, $this->privkeyfile, $this->passphrase);
        }

        if($this->sftp === false)
        {
            $this->sftp = @ssh2_sftp($this->connection);
        }
    }

    /**
     * @param $hash
     * @param null $cacheContext
     * @return string
     * @throws Exception
     */
	private function getPath($hash, $cacheContext=null){
		$out = $this->path;
		if($cacheContext) {
			$out .= DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.$cacheContext;
		}
		$out.= DIRECTORY_SEPARATOR.substr($hash, 0, 3);

        $this->init();
        if(is_resource($this->sftp)){
		    @ssh2_sftp_mkdir($this->sftp, $out, 0777, true);
        }
        else{
            throw new Exception('EMS was not able to initiate the sftp connection');
        }

		return $out.DIRECTORY_SEPARATOR.$hash;
	}

    /**
     * @param string $hash
     * @param bool $cacheContext
     * @return bool
     * @throws Exception
     */
	public function head($hash, $cacheContext=false) {
        if($cacheContext && !$this->contextSupport)
        {
            return false;
        }
        $this->init();
        try
        {
            if($this->sftp)
            {
                $statinfo = @ssh2_sftp_stat($this->sftp, $this->getPath($hash, $cacheContext));
                return $statinfo?true:false;
            }
        }
        catch (Exception $e)
        {
        }
        return false;
	}

    /**
     * @param string $hash
     * @param string $filename
     * @param bool $cacheContext
     * @return bool
     * @throws Exception
     */
	public function create($hash, $filename, $cacheContext=false){
        if($cacheContext && !$this->contextSupport)
        {
            return false;
        }
        $this->init();
	    return ssh2_scp_send($this->connection, $filename, $this->getPath($hash, $cacheContext), 0644);
	}
	
	public function supportCacheStore() {
		return $this->contextSupport;
	}

	public function read($hash, $cacheContext=false)
    {
        if($cacheContext && !$this->contextSupport)
        {
            return false;
        }
        $this->init();
        return fopen('ssh2.sftp://' . intval($this->sftp) . $this->getPath($hash, $cacheContext), 'r');
	}
	
	public function getLastUpdateDate($hash, $cacheContext=false)
    {
        if($cacheContext && !$this->contextSupport)
        {
            return false;
        }
        $this->init();

        $statinfo = ssh2_sftp_stat($this->sftp, $this->getPath($hash, $cacheContext));
		if($statinfo) {
			return $statinfo['mtime'];
		}
		return false;
	}

	public function getSize($hash, $cacheContext = false)
    {
        if($cacheContext && !$this->contextSupport)
        {
            return false;
        }
        $this->init();

        $statinfo = ssh2_sftp_stat($this->sftp, $this->getPath($hash, $cacheContext));
        if($statinfo) {
            return $statinfo['size'];
        }
        return false;
    }

    public function __toString()
    {
        return SftpStorage::class." ($this->host)";
    }

    /**
     * @return bool
     */
    public function clearCache()
    {
        $this->init();
        $fileSystem = new Filesystem();
        $fileSystem->remove('ssh2.sftp://' . intval($this->sftp).$this->path.DIRECTORY_SEPARATOR.'cache');
        return false;
    }

    public function remove($hash)
    {
        if($this->head($hash))
        {
            ssh2_sftp_unlink($this->sftp, $this->getPath($hash));
        }
        $finder = new Finder();
        $finder->name($hash);
        foreach ($finder->in('ssh2.sftp://' . intval($this->sftp).$this->path.DIRECTORY_SEPARATOR.'cache') as $file) {
            ssh2_sftp_unlink($this->sftp, $file);
        }
        return true;
    }
}
