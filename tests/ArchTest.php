<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('strict types are used')
    ->expect('Vlados\LaravelRelatedContent')
    ->toUseStrictTypes();

arch('contracts are interfaces')
    ->expect('Vlados\LaravelRelatedContent\Contracts')
    ->toBeInterfaces();

arch('handlers implement EmbeddingProvider')
    ->expect('Vlados\LaravelRelatedContent\Handlers')
    ->toImplement('Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider');

arch('models extend Eloquent Model')
    ->expect('Vlados\LaravelRelatedContent\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('jobs implement ShouldQueue')
    ->expect('Vlados\LaravelRelatedContent\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('commands extend Artisan Command')
    ->expect('Vlados\LaravelRelatedContent\Commands')
    ->toExtend('Illuminate\Console\Command');

arch('service provider extends base')
    ->expect('Vlados\LaravelRelatedContent\RelatedContentServiceProvider')
    ->toExtend('Spatie\LaravelPackageTools\PackageServiceProvider');

arch('events use Dispatchable trait')
    ->expect('Vlados\LaravelRelatedContent\Events')
    ->toUseTrait('Illuminate\Foundation\Events\Dispatchable');
