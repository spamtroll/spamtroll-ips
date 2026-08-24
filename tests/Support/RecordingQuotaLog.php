<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

use IPS\spamtroll\Log\QuotaLog;
use Spamtroll\Sdk\Response\CheckSpamResponse;

final class RecordingQuotaLog extends QuotaLog
{
    /** @var array<int, CheckSpamResponse> */
    public array $records = [];

    public function record(CheckSpamResponse $response): void
    {
        $this->records[] = $response;
    }
}
