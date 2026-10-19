<?php

use App\Services\ConfigStore;

afterEach(fn () => putenv('DPLY_TOKEN'));

it('prefers DPLY_TOKEN over the stored login', function () {
    $home = sys_get_temp_dir().'/dply-cli-test-'.uniqid();
    $store = new ConfigStore($home);
    $store->setHost('https://dply.test', 'stored-token');

    expect($store->tokenFor('https://dply.test'))->toBe('stored-token');

    putenv('DPLY_TOKEN=env-token');
    expect($store->tokenFor('https://dply.test'))->toBe('env-token')
        ->and($store->tokenFor('https://other.test'))->toBe('env-token');
});
