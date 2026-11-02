<?php
/**
 * Offline export regressions using the production exporter and bundled parsers.
 *
 * Run: php tests/ics-export.php
 * WordPress storage and text helpers are controlled boundaries. No database,
 * network request, event mutation or email delivery is performed.
 */
declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/');
$loader = require ABSPATH . 'vendor/autoload.php';
$loader->addPsr4('RRZE\\Calendar\\', ABSPATH . 'includes/', true);
/**
 * Fail on PHP warnings as well as assertion errors during the offline checks.
 *
 * @param int $severity PHP error severity.
 * @param string $message Error message.
 * @param string $file Source filename.
 * @param int $line Source line.
 * @return never
 */
function failOnPhpError(int $severity, string $message, string $file, int $line): never
{
    throw new ErrorException($message, 0, $severity, $file, $line);
}
set_error_handler('failOnPhpError');
date_default_timezone_set('UTC');
$GLOBALS['test_meta'] = [];
$GLOBALS['test_timezone'] = 'Europe/Berlin';
$GLOBALS['test_checks'] = 0;

/**
 * @return DateTimeZone The configured fixture timezone.
 */
function wp_timezone(): DateTimeZone { return new DateTimeZone($GLOBALS['test_timezone']); }
/**
 * @return string A stable locale for feed metadata.
 */
function get_locale(): string { return 'en_US'; }
/**
 * @param mixed $value Fixture integer.
 * @return int Absolute integer.
 */
function absint($value): int { return abs((int) $value); }
/**
 * @return string A synthetic site URL.
 */
function site_url(): string { return 'https://example.test'; }
/**
 * @param mixed $value Already decoded fixture metadata.
 * @return mixed The unchanged value.
 */
function maybe_unserialize($value) { return $value; }
/**
 * @param string $text Fixture text.
 * @param int $flags Decode flags.
 * @return string Decoded text.
 */
function wp_specialchars_decode(string $text, int $flags): string { return html_entity_decode($text, $flags, 'UTF-8'); }
/**
 * @param string $text Fixture text.
 * @return string Plain text.
 */
function sanitize_text_field(string $text): string { return trim(strip_tags($text)); }
/**
 * @param string $hook Hook name.
 * @param mixed $value Fixture value.
 * @return mixed The unchanged value.
 */
function apply_filters(string $hook, $value) { return $value; }
/**
 * @param string $text Fixture HTML.
 * @param bool $removeBreaks Unused fixture flag.
 * @return string Plain text.
 */
function wp_strip_all_tags(string $text, bool $removeBreaks = false): string { return strip_tags($text); }
/**
 * @param int $id Fixture post ID.
 * @param string $key Optional key.
 * @param bool $single Select scalar value.
 * @return mixed Fixture metadata.
 */
function get_post_meta(int $id, string $key = '', bool $single = false)
{
    $meta = $GLOBALS['test_meta'][$id] ?? [];
    return $key === '' ? $meta : ($single ? ($meta[$key][0] ?? '') : ($meta[$key] ?? []));
}

/**
 * @param mixed $expected Expected value.
 * @param mixed $actual Observed value.
 * @param string $label Assertion context.
 * @return void
 */
function check($expected, $actual, string $label): void
{
    ++$GLOBALS['test_checks'];
    if ($expected !== $actual) {
        throw new RuntimeException($label . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

/**
 * @param array $values Flat event metadata.
 * @param int $id Fixture post ID.
 * @return void
 */
function fixture(array $values, int $id = 1): void
{
    $GLOBALS['test_meta'][$id] = [];
    foreach ($values as $key => $value) {
        $GLOBALS['test_meta'][$id][$key] = [$value];
    }
}

/**
 * @param string $start Local start.
 * @param string $end Local end.
 * @param array $rule Recurrence arguments.
 * @return array Native event metadata.
 */
function nativeEvent(string $start, string $end, array $rule = []): array
{
    $startTs = (new DateTimeImmutable($start, wp_timezone()))->getTimestamp();
    return [
        'start' => $startTs,
        'end' => (new DateTimeImmutable($end, wp_timezone()))->getTimestamp(),
        'event-uid' => 'timezone-regression@example.test',
        'location' => 'Room A',
        'description' => 'Fixture description',
        'event-rrule-args' => $rule ? json_encode(['DTSTART' => '@' . $startTs] + $rule) : '',
    ];
}

/**
 * @param int[] $ids Event IDs.
 * @return string The real exporter and builder output.
 */
function exportEvents(array $ids = [1]): string
{
    $posts = [];
    foreach ($ids as $id) {
        $posts[] = (object) ['ID' => $id, 'post_title' => 'Calendar fixture'];
    }
    $class = new ReflectionClass(\RRZE\Calendar\ICS\Export::class);
    $export = $class->newInstanceWithoutConstructor();
    $method = $class->getMethod('getEvents');
    $data = $method->invoke($export, $posts);
    return (new \RRZE\Calendar\ICS\ICS($data))->build();
}

/**
 * @param string $ics Exported calendar.
 * @return array Parsed start/end times in Berlin, sorted by start.
 */
function localTimes(string $ics): array
{
    $parser = new \ICal\ICal(false, ['defaultTimeZone' => 'Europe/Berlin', 'disableCharacterReplacement' => true]);
    $parser->initString($ics);
    $rows = [];
    foreach ($parser->events() as $event) {
        $start = (new DateTimeImmutable('@' . $event->dtstart_array[2]))->setTimezone(new DateTimeZone('Europe/Berlin'));
        $end = (new DateTimeImmutable('@' . $event->dtend_array[2]))->setTimezone(new DateTimeZone('Europe/Berlin'));
        $rows[] = $start->format('Y-m-d H:i') . '–' . $end->format('H:i');
    }
    sort($rows);
    return $rows;
}

fixture(nativeEvent('2026-10-07 10:00', '2026-10-07 11:30', ['FREQ' => 'MONTHLY', 'BYDAY' => '1WE', 'COUNT' => 3]));
check([
    '2026-10-07 10:00–11:30', '2026-11-04 10:00–11:30', '2026-12-02 10:00–11:30',
], localTimes(exportEvents()), 'Monthly recurrence across winter-time transition');

fixture(nativeEvent('2027-03-24 10:00', '2027-03-24 10:45', ['FREQ' => 'WEEKLY', 'COUNT' => 3]));
check([
    '2027-03-24 10:00–10:45', '2027-03-31 10:00–10:45', '2027-04-07 10:00–10:45',
], localTimes(exportEvents()), 'Weekly recurrence across summer-time transition');

/**
 * Create imported metadata and retain the parser's original feed items.
 *
 * @param string $start DTSTART content line.
 * @param string $end DTEND content line.
 * @param string $rule RRULE value.
 * @return array Imported event metadata in the WordPress storage shape.
 */
function importedEvent(string $start, string $end, string $rule): array
{
    $parser = new \ICal\ICal(false, ['defaultTimeZone' => 'Europe/Berlin', 'disableCharacterReplacement' => true]);
    $parser->initString(implode("\r\n", [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Regression//EN', 'BEGIN:VEVENT',
        'UID:import@example.test', 'SUMMARY:Imported fixture', 'DTSTAMP:20260101T000000Z',
        $start, $end, 'RRULE:' . $rule, 'END:VEVENT', 'END:VCALENDAR', '',
    ]));
    $events = $parser->events();
    fixture(['ical_events_items' => $events], 50);
    $event = $events[0];
    return [
        'start' => $event->dtstart_array[2], 'end' => $event->dtend_array[2],
        'event-uid' => $event->uid, 'ics_feed_id' => 50,
        'ics_event_meta' => ['timezone' => 'Europe/Berlin', 'rrule' => $rule],
    ];
}

/**
 * Read a VTIMEZONE offset without using the parser's IANA timezone lookup.
 *
 * @param string $ics Full calendar payload.
 * @param string $zone Expected component identifier.
 * @param string $utc Date-time at which to evaluate observances.
 * @return int The UTC offset defined by the serialized observances.
 */
function componentOffset(string $ics, string $zone, string $utc): int
{
    $ics = preg_replace('/\r\n[ \t]/', '', $ics);
    preg_match_all('/BEGIN:VTIMEZONE\r\n(.*?)END:VTIMEZONE/s', $ics, $components);
    $transitions = [];
    foreach ($components[1] as $component) {
        if (!str_contains($component, 'TZID:' . $zone . "\r\n")) {
            continue;
        }
        preg_match_all('/BEGIN:(STANDARD|DAYLIGHT)\r\n(.*?)END:\1/s', $component, $observances, PREG_SET_ORDER);
        foreach ($observances as $observance) {
            preg_match('/TZOFFSETFROM:([^\r]+)/', $observance[2], $from);
            preg_match('/TZOFFSETTO:([^\r]+)/', $observance[2], $to);
            preg_match_all('/(?:DTSTART|RDATE):([^\r]+)/', $observance[2], $dates);
            foreach ($dates[1] as $list) {
                foreach (explode(',', $list) as $local) {
                    $instant = new DateTimeImmutable($local . $from[1]);
                    $transitions[$instant->getTimestamp()] = (new DateTimeZone($to[1]))->getOffset($instant);
                }
            }
        }
    }
    ksort($transitions);
    $offset = null;
    $timestamp = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();
    foreach ($transitions as $instant => $value) {
        if ($instant > $timestamp) {
            break;
        }
        $offset = $value;
    }
    if ($offset === null) {
        throw new RuntimeException('Missing VTIMEZONE observance for ' . $zone);
    }
    return $offset;
}

// Monthly CISO-shaped imported rule, including its exact final occurrence.
fixture(importedEvent('DTSTART;TZID=Europe/Berlin:20261007T100000', 'DTEND;TZID=Europe/Berlin:20261007T113000', 'FREQ=MONTHLY;UNTIL=20270303T090000Z;INTERVAL=1;BYDAY=1WE'));
$before = serialize($GLOBALS['test_meta']);
$ics = exportEvents();
check([
    '2026-10-07 10:00–11:30', '2026-11-04 10:00–11:30', '2026-12-02 10:00–11:30',
    '2027-01-06 10:00–11:30', '2027-02-03 10:00–11:30', '2027-03-03 10:00–11:30',
], localTimes($ics), 'Imported monthly series preserves Berlin time and UNTIL');
check($before, serialize($GLOBALS['test_meta']), 'Export leaves event and feed metadata unchanged');
check(7200, componentOffset($ics, 'Europe/Berlin', '2026-10-25 00:59:59'), 'VTIMEZONE before winter transition');
check(3600, componentOffset($ics, 'Europe/Berlin', '2026-10-25 01:00:00'), 'VTIMEZONE after winter transition');
check(3600, componentOffset($ics, 'Europe/Berlin', '2027-03-28 00:59:59'), 'VTIMEZONE before summer transition');
check(7200, componentOffset($ics, 'Europe/Berlin', '2027-03-28 01:00:00'), 'VTIMEZONE after summer transition');

// A UTC source must stay UTC despite the Berlin display setting.
fixture(importedEvent('DTSTART:20261007T080000Z', 'DTEND:20261007T093000Z', 'FREQ=MONTHLY;BYDAY=1WE;COUNT=3'));
$ics = exportEvents();
check(['2026-10-07 10:00–11:30', '2026-11-04 09:00–10:30', '2026-12-02 09:00–10:30'], localTimes($ics), 'Explicit UTC recurrence remains UTC');
check(false, str_contains($ics, 'BEGIN:VTIMEZONE'), 'UTC events need no VTIMEZONE');

// US and European DST change on different dates.
fixture(importedEvent('DTSTART;TZID=America/New_York:20261021T100000', 'DTEND;TZID=America/New_York:20261021T113000', 'FREQ=WEEKLY;COUNT=3'));
$ics = exportEvents();
check(['2026-10-21 16:00–17:30', '2026-10-28 15:00–16:30', '2026-11-04 16:00–17:30'], localTimes($ics), 'Imported New York series retains its own transition dates');
check(-14400, componentOffset($ics, 'America/New_York', '2026-11-01 05:59:59'), 'US offset before transition');
check(-18000, componentOffset($ics, 'America/New_York', '2026-11-01 06:00:00'), 'US offset after transition');

fixture(importedEvent('DTSTART;TZID=W. Europe Standard Time:20261021T100000', 'DTEND;TZID=W. Europe Standard Time:20261021T104500', 'FREQ=WEEKLY;COUNT=2'));
check(['2026-10-21 10:00–10:45', '2026-10-28 10:00–10:45'], localTimes(exportEvents()), 'Windows timezone IDs are resolved');

$legacy = nativeEvent('2026-10-07 10:00', '2026-10-07 11:30');
$legacy['ics_feed_id'] = 51;
$legacy['ics_event_meta'] = ['timezone' => 'Europe/Berlin', 'rrule' => 'FREQ=MONTHLY;BYDAY=1WE;COUNT=2'];
fixture($legacy);
check(['2026-10-07 10:00–11:30', '2026-11-04 10:00–11:30'], localTimes(exportEvents()), 'Legacy imports use their recorded display timezone');

// Native exceptions and additions follow the series time, not midnight or PHP UTC.
$native = nativeEvent('2026-10-07 10:00', '2026-10-07 11:30', ['FREQ' => 'MONTHLY', 'BYDAY' => '1WE', 'COUNT' => 3]);
$native['exceptions'] = "2026-11-04\n2026-12-02";
$native['additions'] = "2026-11-05\r\n2026-12-03";
fixture($native);
$ics = preg_replace('/\r\n[ \t]/', '', exportEvents());
check(true, str_contains($ics, 'EXDATE:20261104T090000Z,20261202T090000Z'), 'Newline-separated exceptions use occurrence offsets');
check(true, str_contains($ics, 'RDATE:20261105T090000Z,20261203T090000Z'), 'Newline-separated additions use occurrence offsets');
check(['2026-10-07 10:00–11:30'], localTimes($ics), 'Parser excludes the specified winter instances');

// The independent RRule engine used by calendar views also reads additions.
// Select VEVENT only, because VTIMEZONE also contains DTSTART/RDATE properties.
preg_match('/BEGIN:VEVENT\r\n(.*?)END:VEVENT/s', $ics, $eventBlock);
$setLines = [];
foreach (explode("\r\n", $eventBlock[1]) as $line) {
    if (preg_match('/^(DTSTART|RRULE|EXDATE|RDATE)[:;]/', $line)) {
        $setLines[] = str_replace('TZID="Europe/Berlin"', 'TZID=Europe/Berlin', $line);
    }
}
$exportedSet = new \RRule\RSet(implode("\n", $setLines));
$exportedDates = [];
foreach ($exportedSet->getOccurrences() as $date) {
    $exportedDates[] = $date->getTimestamp();
}
$calendarDates = [];
foreach (\RRZE\Calendar\Utils::makeRRuleSet(1) as $date) {
    $calendarDates[] = $date->getTimestamp();
}
check($calendarDates, $exportedDates, 'Exported recurrence set matches calendar view generation');

$import = importedEvent('DTSTART;TZID=Europe/Berlin:20261021T100000', 'DTEND;TZID=Europe/Berlin:20261021T104500', 'FREQ=WEEKLY;COUNT=3');
$import['ics_event_meta']['exdate_array'] = [
    ['TZID' => 'Europe/Berlin'], ['20261028T100000'], [], ['20261104T090000Z'],
];
$import['ics_event_meta']['rdate_array'] = [
    ['TZID' => 'Europe/Berlin'], '20261029T100000,20261105T100000',
];
fixture($import);
$ics = exportEvents();
check(true, str_contains($ics, 'EXDATE:20261028T090000Z,20261104T090000Z'), 'Imported EXDATE handles TZID, UTC and repeated lines');
check(true, str_contains($ics, 'RDATE:20261029T090000Z,20261105T090000Z'), 'Imported comma-separated RDATE values survive');
check(['2026-10-21 10:00–10:45'], localTimes($ics), 'Imported exceptions still match');

// Control cases for unchanged single, overnight and all-day event timestamps.
fixture(nativeEvent('2026-11-04 10:00', '2026-11-04 11:30'));
$single = exportEvents();
check(true, str_contains($single, 'DTSTART:20261104T090000Z'), 'Single event remains UTC');
check(false, str_contains($single, 'BEGIN:VTIMEZONE'), 'Single event adds no timezone component');
check(['2026-11-04 10:00–11:30'], localTimes($single), 'Single event time is unchanged');
fixture(nativeEvent('2026-10-24 23:00', '2026-10-25 04:00'));
check(['2026-10-24 23:00–04:00'], localTimes(exportEvents()), 'Overnight event across DST preserves both instants');
$allDay = nativeEvent('2026-11-04 00:00', '2026-11-05 00:00');
$allDay['all-day'] = 'on';
fixture($allDay);
check(true, str_contains(exportEvents(), 'DTSTART:20261103T230000Z'), 'Existing all-day UTC export stays unchanged');
$missingEnd = nativeEvent('2026-11-04 10:00', '2026-11-04 11:30');
unset($missingEnd['end']);
fixture($missingEnd);
check(false, str_contains(exportEvents(), 'DTEND:'), 'Missing end remains omitted');

fixture(nativeEvent('2026-10-07 10:00', '2026-10-07 11:30', ['FREQ' => 'MONTHLY', 'BYDAY' => '1WE', 'COUNT' => 3]));
fixture(nativeEvent('2026-10-08 10:00', '2026-10-08 11:30', ['FREQ' => 'WEEKLY', 'COUNT' => 3]), 2);
check(1, substr_count(exportEvents([1, 2]), 'BEGIN:VTIMEZONE'), 'Timezone definitions are deduplicated');

// The builder must not label server-local timestamps as UTC.
date_default_timezone_set('America/Los_Angeles');
check(['2026-10-07 10:00–11:30', '2026-11-04 10:00–11:30', '2026-12-02 10:00–11:30'], localTimes(exportEvents()), 'PHP default timezone does not affect the export');
date_default_timezone_set('UTC');

$GLOBALS['test_timezone'] = '+05:30';
fixture(nativeEvent('2026-10-07 10:00', '2026-10-07 11:30', ['FREQ' => 'DAILY', 'COUNT' => 2]));
$ics = exportEvents();
check(true, str_contains($ics, 'DTSTART;TZID="+05:30":20261007T100000'), 'Fixed-offset WordPress timezone is quoted correctly');
check(19800, componentOffset($ics, '+05:30', '2026-11-04 00:00:00'), 'Fixed-offset VTIMEZONE preserves fractional-hour offset');
$GLOBALS['test_timezone'] = 'Europe/Berlin';

// Ensure line folding is lossless and within the iCalendar byte limit.
foreach (explode("\r\n", $ics) as $line) {
    if (strlen($line) > 75) {
        throw new RuntimeException('iCalendar line exceeds 75 octets');
    }
}
check(true, true, 'iCalendar line length');

// Daily and yearly rules retain local time too.
fixture(nativeEvent('2026-10-24 10:00', '2026-10-24 11:00', ['FREQ' => 'DAILY', 'COUNT' => 3]));
check(['2026-10-24 10:00–11:00', '2026-10-25 10:00–11:00', '2026-10-26 10:00–11:00'], localTimes(exportEvents()), 'Daily recurrence across winter transition');
fixture(nativeEvent('2026-03-28 10:00', '2026-03-28 11:00', ['FREQ' => 'YEARLY', 'COUNT' => 2]));
check(['2026-03-28 10:00–11:00', '2027-03-28 10:00–11:00'], localTimes(exportEvents()), 'Yearly recurrence crosses different DST start dates');

// Native UNTIL is serialized in UTC and remains inclusive.
fixture(nativeEvent('2026-10-07 10:00', '2026-10-07 11:30', [
    'FREQ' => 'MONTHLY', 'BYDAY' => '1WE', 'UNTIL' => '@' . strtotime('2026-11-04 09:00:00 UTC'),
]));
check(['2026-10-07 10:00–11:30', '2026-11-04 10:00–11:30'], localTimes(exportEvents()), 'Native UNTIL keeps its exact UTC boundary');

$native = nativeEvent('2026-10-07 10:00', '2026-10-07 11:30', ['FREQ' => 'MONTHLY', 'BYDAY' => '1WE', 'COUNT' => 3]);
$native['exceptions'] = '2026-11-04,2026-12-02';
$native['additions'] = "invalid\n2026-02-30\n2026-11-05 14:15";
fixture($native);
$ics = exportEvents();
check(true, str_contains($ics, 'EXDATE:20261104T090000Z,20261202T090000Z'), 'Legacy comma-separated native exceptions');
check(true, str_contains($ics, "RDATE:20261105T131500Z\r\n"), 'Invalid dates are skipped and explicit addition time is preserved');

fixture(importedEvent('DTSTART:20261021T100000', 'DTEND:20261021T104500', 'FREQ=WEEKLY;COUNT=2'));
check(['2026-10-21 10:00–10:45', '2026-10-28 10:00–10:45'], localTimes(exportEvents()), 'Floating source uses the import display timezone');

$import = importedEvent('DTSTART;TZID=Europe/Berlin:20261021T100000', 'DTEND;TZID=Europe/Berlin:20261021T104500', 'FREQ=WEEKLY;COUNT=2');
$override = (object) ['uid' => 'import@example.test', 'dtstart' => '20261028T090000Z', 'recurrence_id' => '20261028T100000'];
$GLOBALS['test_meta'][50]['ical_events_items'][0][] = $override;
fixture($import);
check(['2026-10-21 10:00–10:45', '2026-10-28 10:00–10:45'], localTimes(exportEvents()), 'A UTC recurrence override does not replace the series timezone');

// Multi-zone feed, stable UID/text and UTF-8 folding.
$berlin = nativeEvent('2026-10-07 10:00', '2026-10-07 11:30', ['FREQ' => 'MONTHLY', 'BYDAY' => '1WE', 'COUNT' => 2]);
$berlin['location'] = str_repeat('Grüße; Raum, ', 15);
fixture($berlin, 1);
fixture(importedEvent('DTSTART;TZID=America/New_York:20261021T100000', 'DTEND;TZID=America/New_York:20261021T113000', 'FREQ=WEEKLY;COUNT=2'), 2);
$ics = exportEvents([1, 2]);
check(2, substr_count($ics, 'BEGIN:VTIMEZONE'), 'Mixed source timezones receive separate components');
check(2, substr_count($ics, 'BEGIN:VEVENT'), 'Event count is unchanged');
check(true, str_contains($ics, 'UID:timezone-regression@example.test'), 'Stable event UID');
check(true, str_contains($ics, 'DESCRIPTION:Fixture description'), 'Description survives');
foreach (explode("\r\n", $ics) as $line) {
    if (strlen($line) > 75 || !mb_check_encoding($line, 'UTF-8')) {
        throw new RuntimeException('Invalid folded UTF-8 line');
    }
}
check(true, true, 'Long UTF-8 content and timezone RDATE lists fold correctly');

// A rule whose explicit end exceeds the default timezone horizon is covered.
$lines = \RRZE\Calendar\ICS\TimeZone::build(new DateTimeZone('Europe/Berlin'), strtotime('2026-01-01 UTC'), strtotime('2200-12-31 UTC'));
$farFuture = implode("\r\n", $lines);
check(7200, componentOffset($farFuture, 'Europe/Berlin', '2200-07-01 00:00:00'), 'Timezone covers explicit distant end dates');
check(3600, componentOffset($farFuture, 'Europe/Berlin', '2200-12-01 00:00:00'), 'Distant winter offset is covered');

// Empty export stays a valid empty calendar.
check(0, substr_count(exportEvents([]), 'BEGIN:VEVENT'), 'Empty feed remains empty');

restore_error_handler();
echo 'OK: ' . $GLOBALS['test_checks'] . " checks\n";
