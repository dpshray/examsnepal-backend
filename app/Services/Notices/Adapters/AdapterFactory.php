<?php

namespace App\Services\Notices\Adapters;

use App\Models\NoticeSource;
use InvalidArgumentException;

class AdapterFactory
{
    public function for(NoticeSource $source): NoticeSourceAdapter
    {
        // A per-source custom adapter wins for sites too irregular for the
        // generic ones. Restricted to this namespace so an admin can't point
        // the pipeline at an arbitrary class.
        if ($source->adapter_class) {
            $class = __NAMESPACE__.'\\Custom\\'.class_basename($source->adapter_class);
            if (! class_exists($class) || ! is_subclass_of($class, NoticeSourceAdapter::class)) {
                throw new InvalidArgumentException("Unknown custom adapter {$source->adapter_class}");
            }

            return app($class);
        }

        return match ($source->fetch_type) {
            'html_list' => app(HtmlListAdapter::class),
            'rss' => app(RssAdapter::class),
            'json_api' => app(JsonApiAdapter::class),
            'pdf_list' => app(PdfListAdapter::class),
            default => throw new InvalidArgumentException("Source {$source->id} is manual - nothing to fetch"),
        };
    }
}
