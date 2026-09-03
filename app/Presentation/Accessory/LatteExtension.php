<?php declare(strict_types=1);

namespace App\Presentation\Accessory;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use Latte\Extension;

final class LatteExtension extends Extension
{
	public function getFilters(): array
	{
		return [
			'czechMonth' => [$this, 'filterCzechMonth'],
		];
	}

    /**
     * @throws DateMalformedStringException
     */
    public function filterCzechMonth(mixed $date): string
	{
		if (is_string($date)) {
			$date = new DateTimeImmutable($date);
		}

		if (!$date instanceof DateTimeInterface) {
			return '???';
		}

		$months = [
			1 => 'ledna',
			2 => 'února',
			3 => 'března',
			4 => 'dubna',
			5 => 'května',
			6 => 'června',
			7 => 'července',
			8 => 'srpna',
			9 => 'září',
			10 => 'října',
			11 => 'listopadu',
			12 => 'prosince',
		];

		return $months[(int) $date->format('n')] ?? '???';
	}

    /**
     * @return array|callable[]
     */
    public function getFunctions(): array
	{
		return [];
	}
}
