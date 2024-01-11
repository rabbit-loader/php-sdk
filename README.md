# RabbitLoader PHP SDK (Beta)
RabbitLoader PHP SDK can be used to speed up any website that is built using core PHP or frameworks.

# Installation

```bash
composer install rabbit-loader/php-sdk
```

# Example

Example use, assuming index.php is the file that handles all traffic for the website.

## Public page example

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

## Admin page example

When a content is modified from admin panel, this can be called to make the cache stale.

```php
#admin.php

$urlModified = 'https://mywebsite.com/modified-page-slug/';
$rlSDK->onContentChange($urlModified);

#if home page needs to be purged too, then-
$rlSDK->onContentChange('https://mywebsite.com/');
```

## Skipping some pages from caching and optimization
```php
//skip caching if path starts with admin
$rlSDK->skipForPaths(['/admin*']);

//skip caching if a cookie is found with name cookie1 
$rlSDK->skipForCookies(['cookie1']);

//all the above options should come before the process() call
$rlSDK->process();
```


# License Key
A license key is required to run the SDK. This guide explains [how to get the license key](https://rabbitloader.com/kb/setting-up-rabbitloader-on-custom-php-website/).

# Support
[Contact RabbitLoader team here](https://rabbitloader.local/contact/).