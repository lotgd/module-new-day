<?php

namespace LotGD\Module\NewDay;

use LotGD\Core\Models\Scene;
use LotGD\Core\Events\EventContextData;
use LotGD\Core\Exceptions\ArgumentException;


class EventNewDayData extends EventContextData
{
    public function __construct(array $data)
    {
        if (!isset($data["redirect"])) {
            throw new ArgumentException("A new EventNewDayData event must have a redirect parameter.");
        }

        if ($data["redirect"] !== 0 and !$data["redirect"] instanceof Scene) {
            throw new ArgumentException("Redirect parameter must be either 0 or an instance of a Scene.");
        }

        parent::__construct($data);
    }
}