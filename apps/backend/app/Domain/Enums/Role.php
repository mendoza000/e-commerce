<?php

namespace App\Domain\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
