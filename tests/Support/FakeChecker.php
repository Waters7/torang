<?php

namespace Tests\Support;

use App\Models\Monitor;
use App\Services\Monitoring\Checkers\MonitorChecker;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorConfig;
use RuntimeException;

/**
 * Checker palsu: mengembalikan hasil yang sudah diantrekan, berurutan.
 */
final class FakeChecker implements MonitorChecker
{
    /** @var list<CheckResult> */
    private array $results;

    public int $calls = 0;

    public function __construct(CheckResult ...$results)
    {
        $this->results = $results;
    }

    public function push(CheckResult ...$results): self
    {
        array_push($this->results, ...$results);

        return $this;
    }

    public function check(Monitor $monitor, MonitorConfig $config): CheckResult
    {
        $this->calls++;

        return array_shift($this->results) ?? throw new RuntimeException('FakeChecker kehabisan hasil.');
    }
}
