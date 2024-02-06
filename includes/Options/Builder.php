<?php

namespace RRZE\Calendar\Options;

defined('ABSPATH') || exit;

class Builder
{
    public array $enqueued = [];

    public function add($handle, $callback)
    {
        $this->enqueued[$handle] = $callback;
    }

    public function remove($handle)
    {
        unset($this->enqueued[$handle]);
    }

    public function enqueue()
    {
        foreach ($this->enqueued as $enqueue) {
            $enqueue();
        }
    }
}
