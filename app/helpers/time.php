<?php

if (!defined('PORTAL_TIMEZONE_STORAGE')) {
    define('PORTAL_TIMEZONE_STORAGE', 'UTC');
}

if (!defined('PORTAL_TIMEZONE_DISPLAY')) {
    define('PORTAL_TIMEZONE_DISPLAY', 'America/Manaus');
}

if (!function_exists('portal_utc_now')) {
    function portal_utc_now(string $format = 'Y-m-d H:i:s'): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(PORTAL_TIMEZONE_STORAGE)))->format($format);
    }
}

if (!function_exists('portal_datetime')) {
    function portal_datetime($value, string $sourceTimezone = PORTAL_TIMEZONE_STORAGE): ?DateTimeImmutable
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return (new DateTimeImmutable('@' . (int)$value))->setTimezone(new DateTimeZone(PORTAL_TIMEZONE_STORAGE));
            }

            $text = trim((string)$value);
            if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $text)) {
                return (new DateTimeImmutable($text))->setTimezone(new DateTimeZone(PORTAL_TIMEZONE_STORAGE));
            }

            if (preg_match('#^\d{2}/\d{2}/\d{4}(?:\s+\d{2}:\d{2}(?::\d{2})?)?$#', $text)) {
                $format = strlen($text) === 10 ? 'd/m/Y' : (substr_count($text, ':') === 1 ? 'd/m/Y H:i' : 'd/m/Y H:i:s');
                $dt = DateTimeImmutable::createFromFormat('!' . $format, $text, new DateTimeZone($sourceTimezone));
                return $dt instanceof DateTimeImmutable ? $dt : null;
            }

            return new DateTimeImmutable($text, new DateTimeZone($sourceTimezone));
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('portal_to_utc_sql')) {
    function portal_to_utc_sql($value, string $sourceTimezone = PORTAL_TIMEZONE_DISPLAY): ?string
    {
        $dt = portal_datetime($value, $sourceTimezone);
        return $dt ? $dt->setTimezone(new DateTimeZone(PORTAL_TIMEZONE_STORAGE))->format('Y-m-d H:i:s') : null;
    }
}

if (!function_exists('portal_format_manaus')) {
    function portal_format_manaus($value, string $format = 'd/m/Y H:i:s', string $sourceTimezone = PORTAL_TIMEZONE_STORAGE): string
    {
        $dt = portal_datetime($value, $sourceTimezone);
        return $dt ? $dt->setTimezone(new DateTimeZone(PORTAL_TIMEZONE_DISPLAY))->format($format) : '-';
    }
}

if (!function_exists('portal_manaus_day_utc_bounds')) {
    function portal_manaus_day_utc_bounds(?string $date = null): array
    {
        $date = trim((string)($date ?: portal_format_manaus(portal_utc_now(), 'Y-m-d')));
        $tz = new DateTimeZone(PORTAL_TIMEZONE_DISPLAY);
        $start = new DateTimeImmutable($date . ' 00:00:00', $tz);
        $end = new DateTimeImmutable($date . ' 23:59:59', $tz);
        return [
            $start->setTimezone(new DateTimeZone(PORTAL_TIMEZONE_STORAGE))->format('Y-m-d H:i:s'),
            $end->setTimezone(new DateTimeZone(PORTAL_TIMEZONE_STORAGE))->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('portal_log_timestamp')) {
    function portal_log_timestamp(): string
    {
        return portal_utc_now('Y-m-d H:i:s') . ' UTC';
    }
}
