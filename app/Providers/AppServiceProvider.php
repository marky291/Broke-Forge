<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Global queue failure listener for all jobs
        Queue::failing(function (JobFailed $event) {
            $jobName = $event->job->resolveName();
            $exception = $event->exception;

            $errorType = 'exception';
            $errorMessage = $exception->getMessage();

            if ($exception instanceof TimeoutExceededException) {
                $errorType = 'timeout';
                $errorMessage = 'Job exceeded timeout limit';
            }

            Log::error("Queue job failed: {$jobName}", [
                'job' => $jobName,
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'error_type' => $errorType,
                'error' => $errorMessage,
                'exception_class' => get_class($exception),
                'payload' => $event->job->payload(),
            ]);
        });
    }
}
