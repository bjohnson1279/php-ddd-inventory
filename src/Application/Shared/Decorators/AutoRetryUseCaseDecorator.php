<?php

namespace InventoryApp\Application\Shared\Decorators;

use InventoryApp\Domain\Inventory\Exceptions\ConcurrencyException;
use Exception;

class AutoRetryUseCaseDecorator
{
    /**
     * @param object $useCase The underlying use case instance to execute.
     * @param int $maxRetries Maximum number of retry attempts.
     * @param int $baseDelayMs Base delay in milliseconds for exponential backoff.
     */
    public function __construct(
        private readonly object $useCase,
        private readonly int $maxRetries = 3,
        private readonly int $baseDelayMs = 100
    ) {}

    /**
     * Executes the wrapped use case, retrying on ConcurrencyException.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws Exception
     */
    public function execute(...$args): mixed
    {
        $attempts = 0;
        while (true) {
            try {
                return $this->useCase->execute(...$args);
            } catch (Exception $e) {
                $isConcurrency = $e instanceof ConcurrencyException;

                if ($isConcurrency && $attempts < $this->maxRetries) {
                    $attempts++;
                    $delay = $this->baseDelayMs * (2 ** ($attempts - 1));
                    
                    // Log warning (or write to error log in PHP)
                    error_log(sprintf(
                        '[AutoRetry] Concurrency exception in %s. Retrying (attempt %d/%d) in %dms...',
                        get_class($this->useCase),
                        $attempts,
                        $this->maxRetries,
                        $delay
                    ));

                    // usleep expects microseconds (1 ms = 1000 microseconds)
                    usleep($delay * 1000);
                    continue;
                }
                throw $e;
            }
        }
    }
}
