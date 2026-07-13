<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function test_binds_and_resolves(): void
    {
        $container = new Container();
        $container->bind('greeting', fn (): string => 'hello');

        $this->assertSame('hello', $container->get('greeting'));
    }

    public function test_singleton_returns_same_instance(): void
    {
        $container = new Container();
        $container->singleton(SampleService::class, fn (): SampleService => new SampleService());

        $this->assertSame($container->get(SampleService::class), $container->get(SampleService::class));
    }

    public function test_autowires_constructor_dependencies(): void
    {
        $container = new Container();
        $resolved = $container->get(SampleConsumer::class);

        $this->assertInstanceOf(SampleConsumer::class, $resolved);
        $this->assertInstanceOf(SampleService::class, $resolved->service);
    }
}

final class SampleService
{
}

final class SampleConsumer
{
    public function __construct(public SampleService $service)
    {
    }
}
