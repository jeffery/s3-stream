#!/bin/php
<?php

namespace S3Stream;

use Aws\Common\Enum\ClientOptions;
use Aws\S3\S3Client;

require_once __DIR__ . '/vendor/autoload.php';

try {
    date_default_timezone_set('Australia/Melbourne');

    $args = \Docopt::handle(<<<'s'
Usage:
  s3-stream.php [options] bucket list
  s3-stream.php [options] bucket create <bucket> [--location=<location>]
  s3-stream.php [options] bucket delete <bucket>
  s3-stream.php [options] object read <bucket> <key>
  s3-stream.php [options] object write <bucket> <key>
  s3-stream.php [options] object delete <bucket> <keys>...
  s3-stream.php [options] object list <bucket> [--prefix=<prefix>]

Options:
  --key=<key>             AWS access key ID
  --secret=<secret>       AWS access key secret
  --region=<region>       AWS region
  --base-url=<base-url>   Override "https://s3.amazonaws.com" base URL
  --verbose               Enable verbose logging
  --prefix=<prefix>       To only list the objects with the specified prefix
  --location=<location>   Create the bucket with this location. Usually this needs to be set the same as --region.
s
    );

    $params = array();

    if (isset($args['--key']))
        $params[ClientOptions::KEY] = $args['--key'];
    if (isset($args['--secret']))
        $params[ClientOptions::SECRET] = $args['--secret'];
    if (isset($args['--region']))
        $params[ClientOptions::REGION] = $args['--region'];
    if (isset($args['--base-url']))
        $params[ClientOptions::BASE_URL] = $args['--base-url'];

    $s3 = new S3(S3Client::factory($params));

    if ($args['--verbose'])
        $s3->addLogger(function ($x) { fwrite(STDERR, "$x\n"); });

    /**
     * @param string[] $lines
     * @return string
     */
    function joinLines(array $lines) {
        return $lines ? join("\n", $lines) . "\n" : '';
    }

    if ($args['bucket']) {
        if ($args['list'])
            print joinLines($s3->listBuckets());
        if ($args['create'])
            $s3->createBucket($args['<bucket>'], $args['--location']);
        if ($args['delete'])
            $s3->deleteBucket($args['<bucket>']);
    }

    if ($args['object']) {
        $bucket = $args['<bucket>'];

        if ($args['read'])
            $s3->readObject($bucket, $args['<key>'], STDOUT);
        if ($args['write'])
            $s3->writeObject($bucket, $args['<key>'], STDIN);
        if ($args['list'])
            print joinLines($s3->listKeys($bucket, $args['--prefix']));
        if ($args['delete'])
            $s3->deleteKeys($bucket, $args['<keys>']);
    }
} catch (\Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
