<?php

namespace App\Http\Security;

enum TriggerReason: string
{
    case CLEAN = 'clean';
    case MISSING = 'missing';
    case INVALID = 'invalid';
    case MISMATCH = 'mismatch';
    case EXPLICIT_RESET = 'explicit_reset';
}
