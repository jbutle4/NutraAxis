<?php

declare(strict_types=1);

final class StatusPalette
{
    /** @var array<string, array{fill: string, font: string}> */
    private const CHIP = [
        'ON TRACK'   => ['fill' => '3C8D5A', 'font' => 'FFFFFF'],
        'AT RISK'    => ['fill' => 'E8A317', 'font' => '1A2E2D'],
        'ON HOLD'    => ['fill' => '8C9A9A', 'font' => 'FFFFFF'],
        'COMPLETED'  => ['fill' => '477C7F', 'font' => 'FFFFFF'],
    ];

    /** @var array<string, string> */
    private const RAG_FILL = [
        'Green'   => '3C8D5A',
        'Amber'   => 'E8A317',
    ];

    public static function chipColors(string $status): ?array
    {
        $key = strtoupper(trim($status));

        return self::CHIP[$key] ?? null;
    }

    public static function ragFill(string $status, ?string $legendRed = null, ?string $legendOnHold = null): ?string
    {
        $status = trim($status);
        if ($status === 'Green' || $status === 'Amber') {
            return self::RAG_FILL[$status] ?? null;
        }
        if ($status === 'Red' && $legendRed !== null && $legendRed !== '') {
            return strtoupper(ltrim($legendRed, '#'));
        }
        if ($status === 'On Hold' && $legendOnHold !== null && $legendOnHold !== '') {
            return strtoupper(ltrim($legendOnHold, '#'));
        }

        return null;
    }

    public static function normalizeChip(string $raw): ?string
    {
        $key = strtoupper(trim($raw));

        return isset(self::CHIP[$key]) ? $key : null;
    }

    public static function normalizeRag(string $raw): ?string
    {
        foreach (StatusUpdateDeckSpec::RAG_STATUSES as $allowed) {
            if (strcasecmp($raw, $allowed) === 0) {
                return $allowed;
            }
        }

        return null;
    }
}