<?php

namespace Modules\Administration\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class AdministrationServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Administration';

    protected string $nameLower = 'administration';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
