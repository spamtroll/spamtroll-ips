<?php

declare(strict_types=1);

namespace IPS\spamtroll\Tests\Support;

use IPS\spamtroll\Log\Recorder;
use IPS\spamtroll\Scanner\Decision;

/**
 * Captures what would have gone into `spamtroll_logs`.
 */
final class RecordingRecorder extends Recorder
{
    /** @var array<int, array{decision: Decision, memberId: int|null, contentType: string, contentId: int|null, ip: string|null, preview: string|null}> */
    public array $rows = [];

    public function record(
        Decision $decision,
        ?int $memberId,
        string $contentType,
        ?int $contentId,
        ?string $ipAddress,
        ?string $contentPreview = null,
    ): void {
        $this->rows[] = [
            'decision' => $decision,
            'memberId' => $memberId,
            'contentType' => $contentType,
            'contentId' => $contentId,
            'ip' => $ipAddress,
            'preview' => $contentPreview,
        ];
    }
}
