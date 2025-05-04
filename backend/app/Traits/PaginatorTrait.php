<?php

namespace App\Traits;

use Illuminate\Pagination\LengthAwarePaginator;

trait PaginatorTrait
{
    public array $data = [];

    public function setupPagination(LengthAwarePaginator $pagination, string $resourceClass = null, array $append = []): self
    {
        $items = $pagination->items();

        if ($resourceClass) {
            // Validate the class exists and is a proper resource
            if (!class_exists($resourceClass)) {
                throw new \InvalidArgumentException("Resource class '$resourceClass' not found.");
            }

            // If the resource is a Laravel resource collection, use it properly
            $items = new $resourceClass($items);
        }

        $this->data['data'] = $items;
        $this->data['current_page'] = $pagination->currentPage();
        $this->data['last_page']    = $pagination->lastPage();
        $this->data['total']        = $pagination->total();

        foreach ($append as $key => $value) {
            $this->data[$key] = $value;
        }

        return $this;
    }
}
