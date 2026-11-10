<?php

namespace App\Observers;

use App\Models\Notice;
use App\Models\OptionQuestion;
use App\Models\Question;
use App\Services\FrontendCache;
use Illuminate\Database\Eloquent\Model;

/** Queues a frontend cache refresh when content shown on public pages changes. */
class FrontendCacheObserver
{
    public function saved(Model $model): void
    {
        $this->changed($model);
    }

    public function deleted(Model $model): void
    {
        $this->changed($model);
    }

    private function changed(Model $model): void
    {
        match (true) {
            $model instanceof Question => FrontendCache::questionChanged($model->slug),
            $model instanceof OptionQuestion => FrontendCache::questionChanged($model->question?->slug),
            $model instanceof Notice => FrontendCache::invalidate('notices'),
            default => FrontendCache::invalidate('public-data'),
        };
    }
}
