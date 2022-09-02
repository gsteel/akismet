<?php

declare(strict_types=1);

namespace GSteel\Akismet\Container;

use GSteel\Akismet\AkismetClient;
use GSteel\Akismet\Assert;
use GSteel\Akismet\Client;
use GSteel\Akismet\Exception\RuntimeError;
use Http\Discovery\Exception as DiscoveryFailure;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Container\ContainerInterface;

final class ClientDiscoveryFactory
{
    public function __invoke(ContainerInterface $container): AkismetClient
    {
        $config = $container->has('config') ? $container->get('config') : [];
        Assert::isArray($config);
        $options = $config['akismet'] ?? [];
        Assert::isArray($options);
        $key = $options['key'] ?? null;
        $website = $options['website'] ?? null;
        Assert::stringNotEmpty($key, 'An Akismet API Key has not been configured in `config.akismet.key`');
        Assert::stringNotEmpty(
            $website,
            'The website address has not been configured or is invalid in `config.akismet.website`'
        );
        Assert::url($website, 'The website address has not been configured or is invalid in `config.akismet.website`');

        /**
         * @link https://github.com/php-http/discovery/pull/207
         *
         * @psalm-suppress InvalidCatch
         */
        try {
            return new Client(
                $key,
                $website,
                Psr18ClientDiscovery::find(),
                Psr17FactoryDiscovery::findRequestFactory(),
                Psr17FactoryDiscovery::findStreamFactory()
            );
        } catch (DiscoveryFailure $failure) {
            throw RuntimeError::discoveryFailed($failure);
        }
    }
}
