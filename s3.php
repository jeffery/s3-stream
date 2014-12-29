<?php

namespace S3Stream;

use Aws\S3\S3Client;
use Guzzle\Http\EntityBody;
use Guzzle\Log\ClosureLogAdapter;
use Guzzle\Log\MessageFormatter;
use Guzzle\Plugin\Log\LogPlugin;

class S3 {
    private $s3;

    function __construct(S3Client $s3) {
        $this->s3 = $s3;
    }

    function addLogger(\Closure $closure) {
        $this->s3->addSubscriber(new LogPlugin(new ClosureLogAdapter($closure), MessageFormatter::SHORT_FORMAT));
    }

    /**
     * @return \string[]
     */
    function listBuckets() {
        $results = array();
        $buckets = $this->s3->listBuckets();
        foreach ($buckets['Buckets'] as $bucket)
            $results[] = $bucket['Name'];
        return $results;
    }

    /**
     * @param string $bucket
     * @param string|null $location
     */
    function createBucket($bucket, $location = null) {
        $params = array('Bucket' => $bucket);
        if ($location !== null)
            $params['LocationConstraint'] = $location;
        $this->s3->createBucket($params);
    }

    /**
     * @param string $bucket
     */
    function deleteBucket($bucket) {
        $this->s3->deleteBucket(array('Bucket' => $bucket));
    }

    /**
     * @param string $bucket
     * @param string $prefix
     * @return \string[]
     */
    function listKeys($bucket, $prefix = '') {
        $keys   = array();
        $params = array('Bucket' => $bucket, 'Prefix' => "$prefix");
        while (true) {
            $response = $this->s3->listObjects($params);

            if (isset($response['Contents']))
                foreach ($response['Contents'] as $object)
                    $keys[] = $object['Key'];

            if ($response['IsTruncated'])
                $params['Marker'] = $keys[count($keys) - 1];
            else
                break;
        }
        return $keys;
    }

    /**
     * @param string $bucket
     * @param string[] $keys
     */
    function deleteKeys($bucket, $keys) {
        $objects = array();
        foreach ($keys as $key)
            $objects[] = array('Key' => $key);
        $this->s3->deleteObjects(array(
            'Bucket'  => $bucket,
            'Objects' => $objects,
        ));
    }

    /**
     * @param string $bucket
     * @param string $key
     * @param resource $resource A stream to read the object into
     */
    function readObject($bucket, $key, $resource) {
        $request = $this->s3->getCommand('GetObject', array(
            'Bucket' => $bucket,
            'Key'    => $key,
        ))->prepare();
        $request->setResponseBody(new EntityBody($resource));
        $request->send();
    }

    /**
     * @param string $bucket
     * @param string $key
     * @return S3Upload
     */
    function newUpload($bucket, $key) {
        return new S3Upload($this->s3, $bucket, $key);
    }

    /**
     * @param string $bucket
     * @param string $key
     * @param resource $resource A stream to read the object from
     */
    function writeObject($bucket, $key, $resource) {
        $upload = $this->newUpload($bucket, $key);
        while (!feof($resource))
            $upload->add(fread($resource, 5 * 1024 * 1024));
        $upload->finish();
    }
}

class S3Upload {
    private $s3;
    private $uploadId, $parts = array(), $partNumber = 1, $buffer = '';
    private $bucket, $key;
    private $done = false;

    /**
     * @param S3Client $s3
     * @param string $bucket
     * @param string $key
     */
    function __construct(S3Client $s3, $bucket, $key) {
        $response = $s3->createMultipartUpload(array(
            'Bucket' => $bucket,
            'Key'    => $key,
        ));

        $this->s3       = $s3;
        $this->bucket   = $bucket;
        $this->key      = $key;
        $this->uploadId = $response['UploadId'];
    }

    function __destruct() {
        if (!$this->done) {
            $this->s3->abortMultipartUpload(array(
                'Bucket'   => $this->bucket,
                'Key'      => $this->key,
                'UploadId' => $this->uploadId,
            ));
        }
    }

    /**
     * @param string $data
     */
    function add($data) {
        $this->buffer .= $data;

        if (strlen($this->buffer) >= 5 * 1024 * 1024)
            $this->sendBuffer();
    }

    function finish() {
        if ($this->done)
            throw new \Exception("this upload has already finished");

        if (strlen($this->buffer) > 0)
            $this->sendBuffer();

        $this->s3->completeMultipartUpload(array(
            'Bucket'   => $this->bucket,
            'Key'      => $this->key,
            'Parts'    => $this->parts,
            'UploadId' => $this->uploadId,
        ));

        $this->done = true;
    }

    private function sendBuffer() {
        $partNumber = $this->partNumber++;
        $response   = $this->s3->uploadPart(array(
            'Bucket'     => $this->bucket,
            'Key'        => $this->key,
            'PartNumber' => $partNumber,
            'UploadId'   => $this->uploadId,
            'Body'       => $this->buffer,
        ));

        $this->buffer  = '';
        $this->parts[] = array(
            'ETag'       => $response['ETag'],
            'PartNumber' => $partNumber,
        );
    }
}
