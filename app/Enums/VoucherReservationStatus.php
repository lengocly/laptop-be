<?php

namespace App\Enums;

enum VoucherReservationStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Used = 'used';
}
