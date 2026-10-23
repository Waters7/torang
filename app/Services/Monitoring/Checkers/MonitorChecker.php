<?php

namespace App\Services\Monitoring\Checkers;

use App\Models\Monitor;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorConfig;

interface MonitorChecker
{
    public function check(Monitor $monitor, MonitorConfig $config): CheckResult;
}
