<?php

namespace App\Traits;

use Illuminate\Pagination\LengthAwarePaginator;

trait NewPaginationTrait
{

    public array $data = [];

    private $page = 'page';
    private $items = 'items';
    private $total_page = 'page';
    private $total_items = 'total_items';


    public function setDataKey(array $keys)
    {
        $this->page = array_key_exists($this->page, $keys) ? $keys[$this->page] : $this->page;
        $this->items = array_key_exists($this->items, $keys) ? $keys[$this->items] : $this->items;
        $this->total_page = array_key_exists($this->total_page, $keys) ? $keys[$this->total_page] : $this->total_page;
        $this->total_items = array_key_exists($this->total_items, $keys) ? $keys[$this->total_items] : $this->total_items;
        return $this;
    }

    public function makePaginationResponse(LengthAwarePaginator $pagination, callable $call, array $append = []): self
    {
        $items = $pagination->items();

        $items = $call($items);

        $this->data['items'] = $items;
        $this->data[$this->page] = $pagination->currentPage();
        $this->data['total_page']    = $pagination->lastPage();
        $this->data['total_items']        = $pagination->total();

        foreach ($append as $key => $value) {
            $this->data[$key] = $value;
        }

        return $this;
    }
}
