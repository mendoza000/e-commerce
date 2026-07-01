<?php

namespace App\Domain\Enums;

enum DocumentType: string
{
    case Cedula = 'V';
    case CedulaExtranjera = 'E';
    case Rif = 'RIF';
}
