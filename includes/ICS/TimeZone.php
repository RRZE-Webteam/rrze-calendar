<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

/**
 * Build VTIMEZONE observances from PHP's timezone database.
 *
 * Exact transition dates avoid assuming that every region changes its clocks
 * on the same weekday each year. The range includes the exported rule's UNTIL
 * and at least the next century for subscriptions without an end date.
 */
final class TimeZone
{
    /**
     * Build a timezone component, including the offset in effect at the start.
     *
     * @param \DateTimeZone $timezone The timezone referenced by event properties.
     * @param int $start Earliest exported start timestamp.
     * @param int $end Latest explicitly known event or recurrence timestamp.
     * @return string[] Unfolded iCalendar content lines.
     */
    public static function build(\DateTimeZone $timezone, int $start, int $end): array
    {
        $from = (new \DateTimeImmutable('@' . $start))->modify('-1 year')->getTimestamp();
        $until = max(
            (new \DateTimeImmutable('@' . $end))->modify('+1 year')->getTimestamp(),
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+100 years')->getTimestamp()
        );
        $transitions = $timezone->getTransitions($from, $until);
        if ($transitions === false) {
            $transitions = [[
                'ts' => $from,
                'offset' => $timezone->getOffset(new \DateTimeImmutable('@' . $from)),
                'isdst' => false,
                'abbr' => $timezone->getName(),
            ]];
        }

        $groups = [];
        $previousOffset = $transitions[0]['offset'];
        foreach ($transitions as $transition) {
            $type = $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';
            $key = implode('|', [$type, $previousOffset, $transition['offset'], $transition['abbr']]);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'type' => $type,
                    'from' => self::formatOffset($previousOffset),
                    'to' => self::formatOffset($transition['offset']),
                    'name' => $transition['abbr'],
                    'dates' => [],
                ];
            }
            // VTIMEZONE DTSTART/RDATE use local wall time BEFORE the change.
            $groups[$key]['dates'][] = gmdate('Ymd\THis', $transition['ts'] + $previousOffset);
            $previousOffset = $transition['offset'];
        }

        $lines = ['BEGIN:VTIMEZONE', 'TZID:' . $timezone->getName()];
        foreach ($groups as $group) {
            $lines[] = 'BEGIN:' . $group['type'];
            $lines[] = 'DTSTART:' . array_shift($group['dates']);
            $lines[] = 'TZOFFSETFROM:' . $group['from'];
            $lines[] = 'TZOFFSETTO:' . $group['to'];
            $lines[] = 'TZNAME:' . $group['name'];
            if ($group['dates']) {
                $lines[] = 'RDATE:' . implode(',', $group['dates']);
            }
            $lines[] = 'END:' . $group['type'];
        }
        $lines[] = 'END:VTIMEZONE';
        return $lines;
    }

    /**
     * Encode an RFC 5545 UTC-OFFSET, retaining historical second offsets.
     *
     * @param int $seconds Signed UTC offset in seconds.
     * @return string Offset in +HHMM or +HHMMSS format.
     */
    private static function formatOffset(int $seconds): string
    {
        $absolute = abs($seconds);
        $offset = sprintf('%s%02d%02d', $seconds < 0 ? '-' : '+', intdiv($absolute, 3600), intdiv($absolute % 3600, 60));
        return $absolute % 60 ? $offset . sprintf('%02d', $absolute % 60) : $offset;
    }
}
