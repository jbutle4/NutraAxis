<?php

declare(strict_types=1);

final class StatusUpdateDeckSpec
{
    public const EDITABLE_SLIDES = [2, 3, 4, 5, 6, 7, 8];

    public const RAG_TABLE_ROWS = 13;

    public const RAG_TABLE_COLS = 6;

    public const CHIP_STATUSES = ['ON TRACK', 'AT RISK', 'ON HOLD', 'COMPLETED'];

    public const RAG_STATUSES = ['Green', 'Amber', 'Red', 'On Hold'];

    /**
     * @return list<string>
     */
    public static function shapeNamesForSlide(int $slide): array
    {
        return match ($slide) {
            2 => ['TextBox 2', 'Eyebrow'],
            3 => self::marketingShapes(),
            4 => self::accountingItShapes(),
            5 => self::businessDevShapes(),
            6 => self::upcomingApShapes(),
            7 => ['RAG Matrix', 'Eyebrow', 'Legend Red', 'Legend On Hold'],
            8 => ['RAG Matrix', 'On hold note', 'Eyebrow', 'Legend Red', 'Legend On Hold'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private static function marketingShapes(): array
    {
        $names = ['Cancelled note', 'Eyebrow'];
        for ($i = 1; $i <= 3; $i++) {
            $names[] = 'Chip ' . $i;
            $names[] = 'Card title ' . $i;
            $names[] = 'Key date ' . $i;
            $names[] = 'Key date label ' . $i;
            $names[] = 'Card body ' . $i;
        }

        return $names;
    }

    private static function accountingItShapes(): array
    {
        $names = ['Eyebrow'];
        foreach (['A1', 'A2', 'I1', 'I2'] as $prefix) {
            $names[] = 'Chip ' . $prefix;
            $names[] = 'Card title ' . $prefix;
            $names[] = 'Card body ' . $prefix;
        }

        return $names;
    }

    private static function businessDevShapes(): array
    {
        $names = ['Eyebrow'];
        $names[] = 'Chip S1';
        $names[] = 'Card title S1';
        $names[] = 'Card body S1';
        foreach (['B1', 'B2', 'B3', 'B4'] as $prefix) {
            $names[] = 'Chip ' . $prefix;
            $names[] = 'Card title ' . $prefix;
            $names[] = 'Card body ' . $prefix;
        }

        return $names;
    }

    private static function upcomingApShapes(): array
    {
        $names = ['Eyebrow', 'Chip AP', 'AP vendor', 'AP amount', 'AP bill', 'AP body'];
        foreach (['U1', 'U2', 'U3'] as $prefix) {
            $names[] = 'Chip ' . $prefix;
            $names[] = 'Card title ' . $prefix;
            $names[] = 'Card body ' . $prefix;
        }

        return $names;
    }

    public static function inferFieldKind(string $fieldKey, string $shapeName): string
    {
        if (str_contains($fieldKey, 'RAG Matrix:r')) {
            $parts = explode(':', $fieldKey);
            $cell = end($parts) ?: '';
            if (preg_match('/r\d+c5$/', $cell) === 1) {
                return 'rag';
            }
            if (preg_match('/r0c/', $cell) === 1) {
                return 'readonly';
            }

            return 'text';
        }

        if (str_starts_with($shapeName, 'Chip ') || $shapeName === 'Chip AP') {
            return 'chip';
        }

        if (str_contains($shapeName, 'Key date') && !str_contains($shapeName, 'label')) {
            return 'date';
        }

        return 'text';
    }
}