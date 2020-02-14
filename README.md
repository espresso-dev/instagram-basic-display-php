# Instagram Basic Display PHP API

A simple PHP wrapper for the Instagram Basic Display API. Based on the [Instagram-PHP-API](https://github.com/cosenary/Instagram-PHP-API) by [Christian Metz](http://metzweb.net)

[![Latest Stable Version](http://img.shields.io/packagist/v/espresso-dev/instagram-basic-display-php.svg?style=flat)](https://packagist.org/packages/espresso-dev/instagram-basic-display-php)
[![License](https://img.shields.io/packagist/l/espresso-dev/instagram-basic-display-php.svg?style=flat)](https://packagist.org/packages/espresso-dev/instagram-basic-display-php)
[![Total Downloads](http://img.shields.io/packagist/dt/espresso-dev/instagram-basic-display-php.svg?style=flat)](https://packagist.org/packages/espresso-dev/instagram-basic-display-php)

> [Composer](#installation) package available.

## Requirements

- PHP 7 or higher
- cURL
- Facebook Developer Account
- Facebook App

## Get started

To use the [Instagram Basic Display API](https://developers.facebook.com/docs/instagram-basic-display-api), you will need to register a Facebook app and configure Instagram Basic Display. Follow the [getting started guide](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started).

### Installation

I strongly advice using [Composer](https://getcomposer.org) to keep updates as smooth as possible.

```
$ composer require espresso-dev/instagram-basic-display-php
```

### Initialize the class

```php
use EspressoDev\InstagramBasicDisplay\InstagramBasicDisplay;

$instagram = new InstagramBasicDisplay([
    'appId' => 'YOUR_APP_ID',
    'appSecret' => 'YOUR_APP_SECRET',
    'redirectUri' => 'YOUR_APP_REDIRECT_URI'
]);

echo "<a href='{$instagram->getLoginUrl()}'>Login with Instagram</a>";
```

### Authenticate user (OAuth2)

```php
// Get the OAuth callback code
$code = $_GET['code'];

// Get the short lived access token (valid for 1 hour)
$token = $instagram->getOAuthToken($code, true);

// Exchange this token for a long lived token (valid for 60 days)
$token = $instagram->getLongLivedToken($token, true);

echo 'Your token is: ' . $token;
```

### Get user profile

```php
// Set user access token
$instagram->setAccessToken($token);

// Get the users profile
$profile = $instagram->getUserProfile();

echo '<pre>';
print_r($profile);
echo '<pre>';
```

**All methods return the API data as `json_decode()` - so you can directly access the data.**

## Available methods

### Setup Instagram

`new Instagram(<array>/<string>);`

`array` if you want to perform oAuth:

```php
new InstagramBasicDisplay([
    'appId' => 'YOUR_APP_ID',
    'appSecret' => 'YOUR_APP_SECRET',
    'redirectUri' => 'YOUR_APP_REDIRECT_URI'
]);
```

`string` once you have a token and just want to return *read-only* data:

```php
new InstagramBasicDisplay('ACCESS_TOKEN');
```

### Get login URL

`getLoginUrl(<array>, <string>)`

```php
getLoginUrl(
    array(
        'user_profile', 
        'user_media'
    ),
    'state'
);
```

### Get OAuth token (Short lived valid for 1 hour)

`getOAuthToken($code, <true>/<false>)`

`true` : Returns only the OAuth token  
`false` *[default]* : Returns OAuth token and profile data of the authenticated user

### Exchange the OAuth token for a Long lived token (valid for 60 days)

`getLongLivedToken($token, <true>/<false>)`

`true` : Returns only the OAuth token  
`false` *[default]* : Returns OAuth token and profile data of the authenticated user

### Refresh access token for another 60 days before it expires

`refreshToken($token, <true>/<false>)`

`true` : Returns only the OAuth token  
`false` *[default]* : Returns OAuth token and expiry data of the token

### Set / Get access token

- Set the access token, for further method calls: `setAccessToken($token)`
- Get the access token, if you want to store it for later usage: `getAccessToken()`

### User methods

**Authenticated methods**

- `getUserProfile()`
- `getUserMedia(<$id>, <$limit>)`
    - if an `$id` isn't defined or equals `'me'`, it returns the media of the logged in user

### Media methods

**Authenticated methods**

- `getMedia($id)`
- `getMediaChildren()`


## Pagination

The `getUserMedia` endpoint has a maximum range of results, so increasing the `limit` parameter above the limit of 99 won't help.You can use pagination to return more results for this endpoint.

Pass an object into the `pagination()` method and receive your next dataset:

```php
$media = $instagram->getUserMedia();

$moreMedia = $instagram->pagination($media);
```
