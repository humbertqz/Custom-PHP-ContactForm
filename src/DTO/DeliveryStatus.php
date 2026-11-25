<?php

declare(strict_types=1);

namespace ContactModule\DTO;

enum DeliveryStatus: string
{
    case Completed = 'completed';
    case Temporary = 'temporary';
    case Failed = 'failed';
    case Ambiguous = 'ambiguous';
}
