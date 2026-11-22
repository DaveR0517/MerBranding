<?php

namespace App\Models;

/**
 * De 11 statussen van een samenwerking plus Geannuleerd en Niet verschenen (CLAUDE.md hoofdstuk 6).
 */
enum Status: string
{
    case Nieuw = 'nieuw';
    case CreatorZoeken = 'creator_zoeken';
    case CreatorGevonden = 'creator_gevonden';
    case BezoekGepland = 'bezoek_gepland';
    case BezoekGeweest = 'bezoek_geweest';
    case ContentMaken = 'content_maken';
    case TerControle = 'ter_controle';
    case Feedback = 'feedback';
    case Goedgekeurd = 'goedgekeurd';
    case Live = 'live';
    case Afgerond = 'afgerond';
    case Geannuleerd = 'geannuleerd';
    case NietVerschenen = 'niet_verschenen';

    public function number(): ?int
    {
        return match ($this) {
            self::Nieuw => 1, self::CreatorZoeken => 2, self::CreatorGevonden => 3, self::BezoekGepland => 4,
            self::BezoekGeweest => 5, self::ContentMaken => 6, self::TerControle => 7, self::Feedback => 8,
            self::Goedgekeurd => 9, self::Live => 10, self::Afgerond => 11,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Nieuw => 'Nieuw', self::CreatorZoeken => 'Creator zoeken', self::CreatorGevonden => 'Creator gevonden',
            self::BezoekGepland => 'Bezoek gepland', self::BezoekGeweest => 'Bezoek geweest', self::ContentMaken => 'Content maken',
            self::TerControle => 'Ter controle', self::Feedback => 'Feedback', self::Goedgekeurd => 'Goedgekeurd',
            self::Live => 'Live', self::Afgerond => 'Afgerond', self::Geannuleerd => 'Geannuleerd', self::NietVerschenen => 'Niet verschenen',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Afgerond, self::Geannuleerd, self::NietVerschenen], true);
    }

    /** @return list<array{value: string, label: string, number: int|null}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'number' => $s->number()], self::cases());
    }
}
