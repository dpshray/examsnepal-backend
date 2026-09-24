<?php

namespace App\Services\Notices\Enrichment;

use RuntimeException;

/** Rate limit / overload / network failure - the queued job should retry later. */
class TransientAiException extends RuntimeException {}
