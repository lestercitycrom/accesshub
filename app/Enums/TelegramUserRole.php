<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramUserRole: string
{
	case Admin = 'admin';
	case Operator = 'operator';
}



