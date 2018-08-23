<?php

namespace EMS\CoreBundle\Service\Storage;



use Exception;
use function filesize;
use function fopen;
use function get_class;
use function ssh2_auth_pubkey_file;

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
	
	private function getPath($hash, $cacheContext){
		$out = $this->path;
		if($cacheContext) {
			$out .= DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.$cacheContext;
		}
		$out.= DIRECTORY_SEPARATOR.substr($hash, 0, 3);

		@ssh2_sftp_mkdir($this->sftp, $out, 0777, true);

		return $out.DIRECTORY_SEPARATOR.$hash;
	}
	
	public function head($hash, $cacheContext=false) {
        if($cacheContext && !$this->contextSupport)
        {
            return false;
        }
        $this->init();
        try
        {
            $statinfo = @ssh2_sftp_stat($this->sftp, $this->getPath($hash, $cacheContext));
		    return $statinfo?true:false;
        }
        catch (Exception $e)
        {
        }
        return false;
	}
	
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
        //TODO: should probaly be implemented, but how?
        return false;
    }
}
