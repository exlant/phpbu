<?php
namespace phpbu\Backup\Sync;

use phpseclib;
use phpbu\App\Result;
use phpbu\Backup\Sync;
use phpbu\Backup\Target;

class Sftp implements Sync
{
    /**
     * Host to connect to
     *
     * @var string
     */
    protected $host;

    /**
     * User to connect with
     *
     * @var string
     */
    protected $user;

    /**
     * Password to authenticate user
     *
     * @var string
     */
    protected $password;

    /**
     * Remote path where to put the backup
     *
     * @var string
     */
    protected $remotePath;

    public function setup(array $config)
    {
        if (!class_exists('\\phpseclib\\Net\\SFTP')) {
            throw new Exception('phpseclib not installed - use composer to install "phpseclib/phpseclib" version 2.x');
        }
        if (empty($config['host'])) {
            throw new Exception('option \'host\' is missing');
        }
        if (!isset($config['user'])) {
            throw new Exception('option \'user\' is missing');
        }
        if (!isset($config['password'])) {
            throw new Exception('option \'password\' is missing');
        }
        $this->host       = $config['host'];
        $this->user       = $config['user'];
        $this->password   = $config['password'];
        $this->remotePath = !empty($config['path']) ? $config['path'] : '';
    }

    public function sync(Target $target, Result $result)
    {
        // silence phpseclib
        $old  = error_reporting(0);
        $sftp = new phpseclib\Net\SFTP($this->host);
        if (!$sftp->login($this->user, $this->password)) {
            error_reporting($old);
            throw new Exception(
                sprintf(
                    'authentication failed for %s@%s%s',
                    $this->user,
                    $this->host,
                    empty($this->password) ? '' : ' with password ****'
                )
            );
        }
        error_reporting($old);

        $remoteFilename = $target->getFilenameCompressed();
        $localFile      = $target->getPathname(true);

        if ('' !== $this->remotePath) {
            $remoteDirs = explode('/', $this->remotePath);
            foreach ($remoteDirs as $dir) {
                if (!$sftp->is_dir($dir)) {
                    $result->debug(sprintf('creating remote dir \'%s\'', $dir));
                    $sftp->mkdir($dir);
                }
                $result->debug(sprintf('change to remoted dir \'%s\'', $dir));
                $sftp->chdir($dir);
            }
        }
        $result->debug(sprintf('store file \'%s\' as \'%s\'', $localFile, $remoteFilename));
        $result->debug(sprintf('last error \'%s\'', $sftp->getLastSFTPError()));
        if (!$sftp->put($remoteFilename, $localFile, phpseclib\Net\SFTP::SOURCE_LOCAL_FILE)) {
            throw new Exception(sprintf('error uploading file: %s - %s', $localFile, $sftp->getLastSFTPError()));
        }
    }
}