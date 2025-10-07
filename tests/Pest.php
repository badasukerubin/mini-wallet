<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use Symfony\Component\Process\Process;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature/Auth', 'Feature/Settings', 'Feature/Transactions/Controllers');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->in('Feature/Transactions/Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function transferProcessEnv(): array
{
    return array_merge($_ENV, [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => env('DB_CONNECTION'),
        'DB_HOST' => env('DB_HOST'),
        'DB_PORT' => env('DB_PORT'),
        'DB_DATABASE' => env('DB_DATABASE'),
        'DB_USERNAME' => env('DB_USERNAME'),
        'DB_PASSWORD' => env('DB_PASSWORD'),
    ]);
}

function spawnTransferProcess(int $senderId, int $receiverId, string $amount): Process
{
    $cmd = [
        PHP_BINARY,
        base_path('artisan'),
        'app:create-transactions',
        '--sender_id='.$senderId,
        '--receiver_id='.$receiverId,
        '--amount='.$amount,
        '--env=testing',
    ];

    $process = new Process($cmd);
    $process->setEnv(transferProcessEnv());
    $process->setTimeout(60);
    $process->start();

    return $process;
}

function waitAllProcesses(array $processes): array
{
    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        $results[] = [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'exit' => $process->getExitCode(),
        ];
    }

    return $results;
}
