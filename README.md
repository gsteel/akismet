# Akismet Client Library

[![Continuous Integration](https://github.com/gsteel/akismet/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/gsteel/akismet/actions/workflows/continuous-integration.yml)
[![Psalm Type Coverage](https://shepherd.dev/github/gsteel/akismet/coverage.svg)](https://shepherd.dev/github/gsteel/akismet)
[![Latest Stable Version](https://poser.pugx.org/gsteel/akismet/v/stable)](https://packagist.org/packages/gsteel/akismet)
[![Total Downloads](https://poser.pugx.org/gsteel/akismet/downloads)](https://packagist.org/packages/gsteel/akismet)

## Introduction

Provides a straight-forward way of using the [Akismet](https://akismet.com) anti-spam service in any-old PHP application.

## Installation & Requirements

Requires PHP `~8.0 || ~8.1 || ~8.2`

The library does not include an HTTP client, so if your project does not already have a [PSR-18 HTTP Client](https://www.php-fig.org/psr/psr-18/) installed, you will need to install one in order to use it. There are many [HTTP clients to choose](https://packagist.org/providers/psr/http-client-implementation) from, for example the popular libraries [Guzzle](https://packagist.org/packages/guzzlehttp/guzzle) or [HttpPlug](https://packagist.org/packages/php-http/curl-client).

The library also requires that you have a [PSR-17 (HTTP Factories) library](https://www.php-fig.org/psr/psr-17/) installed, again, you can find implementations on [Packagist](https://packagist.org/providers/psr/http-factory-implementation) and I personally favour [laminas/laminas-diactoros](https://packagist.org/packages/laminas/laminas-diactoros).

```shell
composer require laminas/laminas-diactoros
composer require php-http/curl-client
composer require gsteel/akismet
```

## Basic Usage

### Construct a client

Once you have the requisite HTTP related libraries installed, they _should_ become discoverable with the shipped [discovery library](https://github.com/php-http/discovery). Provide an Api key, and the default website address that you will be operating with to the constructor, and you’ll have a ready-to-use client:

```php
<?php

use GSteel\Akismet\Client;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

$client = new Client(
    'myApiKey',
    'https://my-website.example.com',
    Psr18ClientDiscovery::find(),
    Psr17FactoryDiscovery::findRequestFactory(),
    Psr17FactoryDiscovery::findStreamFactory()
);

// Check the validity of your API key

$result = $client->verifyKey();
assert($result === true);

```

### Comment Parameters & Checking Content

The primary concern of the library is to check requests such as comments and form submissions to ascertain whether the content is "Spam" or "Ham".

The Akismet API has a number of parameters available to improve the accuracy of the check which have been encapsulated into an immutable value object `\Gsteel\Akismet\CommentParameters`.

If you are familiar with the parameter names, you can pass in an array to the constructor of this object, otherwise you can call various methods to build an object to include all the information you wish to provide.

```php
<?php

use GSteel\Akismet\AkismetClient;
use GSteel\Akismet\CommentParameters;
use GSteel\Akismet\CommentType;

assert($client instanceof AkismetClient);

$parameters = (new CommentParameters())
    ->withComment('Some comment Content', CommentType::contactForm())
    ->withRequestParams($_SERVER['REMOTE_ADDR']);
    
$result = $client->check($parameters);

assert($result->isSpam());

```

There are a considerable number of additional arguments and methods available that can be used to provide as much context as possible to the API, there is also a named constructor that is useful in an environment that utilises [PSR-7 Messages](https://www.php-fig.org/psr/psr-7/):

```php
<?php

use GSteel\Akismet\CommentParameters;
use Psr\Http\Message\ServerRequestInterface;

assert($request instanceof ServerRequestInterface);

$parameters = CommentParameters::fromRequest($request);

```

### Submitting False Positives or False Negatives

Submitting content that was incorrectly classified as Spam can be achieved by sending the same payload used in `check()` to the method `submitHam()`. Conversely, you can submit incorrectly classified Ham as actual Spam with `submitSpam()`:

```php
<?php

use GSteel\Akismet\AkismetClient;
use GSteel\Akismet\Result;

assert($client instanceof AkismetClient);

$parameters = CommentParameters::fromRequest($request);
$result = $client->check($parameters);

// Result is incorrectly classified as spam:
$client->submitHam($result->parameters());

// Result is incorrectly classified as ham:
$client->submitSpam($result->parameters());

```

You can serialise and un-serialise a `Result` from the clients `check()` method to a JSON string:

```php
<?php

use GSteel\Akismet\Result;

assert($result instanceof Result);

$jsonString = json_encode($result);

$hydratedResult = Result::fromJsonString($jsonString);

```

Coupled with your own method of identifying a specific request, you could create a mechanism to store the result in order to submit it as ham or spam based on user feedback.

## Error Handling

All errors implement `\GSteel\Akismet\GenericException` so it is possible to catch any errors thrown by the library with a single type. Notable concrete exceptions include:

- `ApiError` - Indicating that an api request failed because of an invalid key for example
- `HttpError` - A failure to communicate with the Akismet via HTTP
- `InvalidRequestParameters` - Required values missing, or invalid values given to `CommentParameters`

## PSR-11 Containers

A factory is shipped in `\GSteel\Akismet\Container\ClientDiscoveryFactory` that can be wired up in your DI container of choice that leverages Http Discovery to inject Http clients and factories etc.

It assumes that the container will return an array or something implementing ArrayAccess by asking for `config`
It further assumes that this configuration structure contains the following information:

```php
<?php
$config = [
    'akismet' => [
        'key' => 'Your API Key',
        'website' => 'https://you.example.com',
    ],
];
```

## License

Released under the MIT License - see the [LICENSE](./LICENSE) file for details
