<?php

namespace Modules\FormBuilder\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class FormBuilderServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'FormBuilder';

    protected string $nameLower = 'formbuilder';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
