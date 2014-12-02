# s3-stream

A command line interface to stream data to and from Amazon S3

### Installation

1. Install PHP if you haven't already (http://php.net/)
1. Download s3-stream
   ```
$ curl -OJL https://github.com/jesseschalken/s3-stream/archive/master.zip
$ unzip s3-stream-master.zip
$ cd s3-stream-master
```

2. Install Composer dependencies (https://getcomposer.org/doc/00-intro.md#locally)
    ```
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar install --prefer-dist
```

### Usage

See:

```
$ ./s3-stream.php --help
```

Example:
```
$ ./s3-stream.php --key=KEY --secret=SECRET --region=REGION object read BUCKET KEY
```

Note that because your AWS key and secret are command line arguments, *they will be visible to anyone else on the same system* through the `ps faux` command, and thus I recommand you do not use `s3-stream.php` on shared systems.
