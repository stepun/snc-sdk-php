# 2can Clode SDK for PHP
* URL [my2can.com](https://my2can.com)
* API [Documentation](https://gist.github.com/stepun/8c09fa528356c4de8fb8)

## Add Snc-sdk-php using Composer
* Add file composer.json with content in WEBROOT directory:
```JSON
{
    "homepage": "http://example.com/",
    "require": {
        "php": ">=5.3.3",
        "stepun/snc-sdk-php": "dev-master"
    },
    "repositories":[
        {
            "type":"git",
            "url":"https://github.com/stepun/snc-sdk-php.git"
        }
    ]
}
```
* Download composer into your project directory and install the dependencies in WEBROOT directory:
```
curl -s https://getcomposer.org/installer | php
php composer.phar install
```
If you don't have access to curl, then install Composer into your project as per the [documentation](https://getcomposer.org/doc/00-intro.md).

Example usage. Create index.php file in WEBROOT/public directory:
```PHP
<?php

if (file_exists('./../vendor/autoload.php')) {
    $loader = include './../vendor/autoload.php';
}

use Snc\Srg\SrgClient;

class SyncSnc
{
    /** @var \Snc\Srg\SrgClient  */
    protected $client;


    protected $config = [
        'snc' => [
            'base_url'  => 'https://my2can.com/api/',
            'key'       => '7d1ef***',
            'secret'    => '813c4***',
        ]
    ];

    function __construct($config = null)
    {
        if ($config) {
            $this->config = array_merge($this->config, $config);
        }
        $this->client = SrgClient::factory($this->config['snc']);
    }

    /**
     * @param \Snc\Srg\SrgClient $client
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return \Snc\Srg\SrgClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param array $config
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}

$client = new SyncSnc([
    'snc' => [
        'key'       => 'b1795746efcd98e9685a5215906de492',
        'secret'    => '***********0955c5448e6db4f65a',
    ]
]);

$response = $client->getClient()->getAPIData('v1_storage.json');
echo print_r(
    json_decode($response->getBody(true), true), 
    true
);
```
