<?php

namespace App\Services\Notices\Adapters;

use App\Models\NoticeSource;

interface NoticeSourceAdapter
{
    /** @return RawNoticeItem[] */
    public function fetchList(NoticeSource $source): array;

    /**
     * Hash of the structural part of the last fetched page, used to spot
     * site redesigns (items drop to zero while the page itself changed).
     */
    public function lastSnapshotHash(): ?string;
}
