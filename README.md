# RabbitLoader PHP SDK (Beta)
RabbitLoader PHP SDK can be used to speed up any website that is built using core PHP or frameworks.

# Installation

```bash
composer install rabbit-loader/php-sdk
```

# Example

Example use, assuming index.php is the file that handles all traffic for the website.
```php
#load vendor/autoload.php

#integrate RabbitLoader
$licenseKey = 'YOUR_LICENSE_KEY'; //get your license key from environment variable
$storageDir = '/cache-disk/rabbitloader'; //storage location where cached files will be stored
$rlSDK = new RabbitLoader\SDK\RabbitLoader($licenseKey, $storageDir);
$rlSDK->process();

#remaining code of the website goes after this ...
echo "<h1>Hello World!</h1>"
```

When a content is modified from admin panel, this can be called to make the cache stale.

```php
#admin.php

$urlModified = 'https://mywebsite.com/modified-page-slug/';
$rlSDK->onContentChange($urlModified);

#if home page needs to be purged too, then-
$rlSDK->onContentChange('https://mywebsite.com/');
```
