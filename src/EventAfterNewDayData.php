<?php

namespace LotGD\Module\NewDay;

use LotGD\Core\Models\Scene;
use LotGD\Core\Events\EventContextData;
use LotGD\Core\Exceptions\ArgumentException;
use LotGD\Core\Models\Viewpoint;


class EventAfterNewDayData extends EventContextData
{
    public function __construct(array $data)
    {
        if (!isset($data["viewpoint"]) or $data["viewpoint"] instanceof Viewpoint !== true) {
            throw new ArgumentException("A new EventNewDayData event must have a valid viewpoint parameter.");
        }

        parent::__construct($data);
    }
}