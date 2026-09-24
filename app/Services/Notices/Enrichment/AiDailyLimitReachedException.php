<?php

namespace App\Services\Notices\Enrichment;

/** NOTICES_AI_DAILY_LIMIT reached - retry after midnight UTC (when free-tier quotas reset). */
class AiDailyLimitReachedException extends TransientAiException {}
