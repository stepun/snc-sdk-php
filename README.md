# Store & Cash SDK for PHP
## Add Snc-sdk-php using Composer
* Add file composer.json with content:
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
* Download composer into your project directory and install the dependencies:
```
curl -s https://getcomposer.org/installer | php
php composer.phar install
```
If you don't have access to curl, then install Composer into your project as per the [documentation](https://getcomposer.org/doc/00-intro.md).
