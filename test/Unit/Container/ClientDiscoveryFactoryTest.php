<?php

declare(strict_types=1);

namespace GSteel\Akismet\Test\Container;

use GSteel\Akismet\AkismetClient;
use GSteel\Akismet\Container\ClientDiscoveryFactory;
use GSteel\Akismet\Exception\GenericException;
use GSteel\Akismet\Exception\RuntimeError;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function PHPUnit\Framework\assertInstanceOf;

class ClientDiscoveryFactoryTest extends TestCase
{
    private MockObject|ContainerInterface $container;
    /** @var iterable|string[] */
    private iterable $restoreStrategies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);
        $this->restoreStrategies = Psr17FactoryDiscovery::getStrategies();
    }

    protected function tearDown(): void
    {
        Psr17FactoryDiscovery::setStrategies($this->restoreStrategies);

        parent::tearDown();
    }

    public function testThatAnExceptionIsThrownForAMissingKeyWhenThereIsNoConfig(): void
    {
        $this->container->expects(self::once())
            ->method('has')
            ->with('config')
            ->willReturn(false);

        $factory = new ClientDiscoveryFactory();
        $this->expectException(GenericException::class);
        $this->expectExceptionMessage('An Akismet API Key has not been configured');
        $factory($this->container);
    }

    /** @param array<string, mixed> $config */
    private function containerHasConfig(array $config): void
    {
        $this->container->expects(self::once())
            ->method('has')
            ->with('config')
            ->willReturn(true);

        $this->container->expects(self::once())
            ->method('get')
            ->with('config')
            ->willReturn($config);
    }

    public function testThatAnInvalidWebsiteUrlWillCauseAnError(): void
    {
        $this->containerHasConfig([
            'akismet' => [
                'key' => 'any non empty key is valid',
                'website' => 'bar',
            ],
        ]);

        $factory = new ClientDiscoveryFactory();
        $this->expectException(GenericException::class);
        $this->expectExceptionMessage('The website address has not been configured or is invalid');
        $factory($this->container);
    }

    public function testThatADiscoveryFailureWillBeWrapped(): void
    {
        Psr17FactoryDiscovery::setStrategies([]);

        $this->containerHasConfig([
            'akismet' => [
                'key' => 'key',
                'website' => 'https://www.example.com',
            ],
        ]);

        $factory = new ClientDiscoveryFactory();
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('HTTP Client or message related factory discovery failed');
        $factory($this->container);
    }

    public function testThatAnInstanceCanBeCreated(): void
    {
        $this->containerHasConfig([
            'akismet' => [
                'key' => 'key',
                'website' => 'https://www.example.com',
            ],
        ]);

        $factory = new ClientDiscoveryFactory();
        assertInstanceOf(AkismetClient::class, $factory($this->container));
    }
}
