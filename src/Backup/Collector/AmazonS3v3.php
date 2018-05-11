<?php
namespace phpbu\App\Backup\Collector;

use Aws\S3\S3Client;
use phpbu\App\Backup\Collector;
use phpbu\App\Backup\File\AmazonS3v3 as AwsFile;
use phpbu\App\Backup\Target;
use phpbu\App\Util;

/**
 * AmazonS3v3 class.
 *
 * @package    phpbu
 * @subpackage Backup
 * @author     Sebastian Feldmann <sebastian@phpbu.de>
 * @author     Vitaly Baev <hello@vitalybaev.ru>
 * @copyright  Sebastian Feldmann <sebastian@phpbu.de>
 * @license    https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link       http://phpbu.de/
 * @since      Class available since Release 5.1.0
 */
class AmazonS3v3 extends Collector
{
    /**
     * @var \Aws\S3\S3Client
     */
    protected $client;

    /**
     * AmazonS3 bucket name
     *
     * @var string
     */
    protected $bucket;

    /**
     * OpenStack constructor.
     *
     * @param \phpbu\App\Backup\Target $target
     * @param S3Client                 $client
     * @param string                   $bucket
     * @param string                   $path
     * @param int                      $time
     */
    public function __construct(Target $target, S3Client $client, string $bucket, string $path, int $time)
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPath($path, $time);
        $this->setUp($target);
    }

    /**
     * Get all created backups.
     *
     * @return \phpbu\App\Backup\File[]
     */
    public function getBackupFiles() : array
    {
        $result = $this->client->listObjects([
            'Bucket'    => $this->bucket,
            'Prefix'    => $this->getPrefix($this->pathNotChanging),
        ]);

        if (!isset($result['Contents']) || !$result['Contents'] || !is_array($result['Contents'])) {
            return [];
        }

        foreach ($result['Contents'] as $object) {
            // skip currently created backup
            if ($object['Key'] == $this->getPrefix() . $this->target->getFilename()) {
                continue;
            }
            if ($this->isFileMatch($object['Key'])) {
                $this->files[] = new AwsFile($this->client, $this->bucket, $object);
            }
        }

        return $this->files;
    }

    /**
     * Return prefix for querying remote files and folders
     *
     * @param string|null $path
     * @return string
     */
    protected function getPrefix($path = null): string
    {
        $path = $path ?: $this->path;
        $prefix = Util\Path::withoutLeadingSlash($path);
        $prefix = $prefix ? Util\Path::withTrailingSlash($prefix) : '';
        return $prefix;
    }
}
