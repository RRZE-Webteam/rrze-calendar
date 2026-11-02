<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

/**
 * iCalendar data array.
 */
class ICS
{
    /**
     * Date and time format.
     * RFC-5545 (3.3.5. Date-Time)
     */
    public const DT_FORMAT = 'Ymd\THis\Z';

    /**
     * Carriage return and line feed.
     * RFC-5545 (3.1. Content Lines)
     */
    const CRLF = "\r\n";

    /**
     * Line length.
     * RFC-5545 (3.1. Content Lines)
     */
    const LINE_LENGTH = 70;

    /**
     * iCalendar data array.
     * @var array
     */
    protected $data = [];

    /**
     * Available iCalendar properties.
     * @see https://tools.ietf.org/html/rfc5545#section-3.8
     * @var array
     * @return void
     */
    private $availableProperties = [
        'uid',
        'description',
        'dtend',
        'dtstart',
        'location',
        'summary',
        'rrule',
        'exdate',
        'rdate',
        'timezone'
    ];

    /**
     * Constructor.
     * @param array $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->setData($data);
    }

    /**
     * Create an array with iCalendar data.
     * @param array $data
     * @return void
     */
    public function setData(array $data)
    {
        foreach ($data as $postId => $props) {
            $this->data[$postId] = $this->setProps($props);
        }
    }

    /**
     * Set the iCalendar properties.
     * @param array $props
     * @return array
     */
    private function setProps(array $props): array
    {
        $properties = [];
        foreach ($props as $key => $value) {
            if (in_array($key, $this->availableProperties)) {
                if (empty($value)) {
                    continue;
                }
                $properties[$key] = $this->sanitizeValue($value, $key);
            }
        }
        return $properties;
    }

    /**
     * Build the ICS file content.
     * @return string
     */
    public function build(): string
    {
        $rows = array_map(
            fn($row) => $this->split($row),
            $this->render()
        );
        return implode(self::CRLF, $rows);
    }

    /**
     * Create an array with iCalendar properties.
     * @return array
     */
    private function render(): array
    {
        $locale = strtoupper(mb_substr(get_locale(), 0, 2));
        $prodid = str_replace('.', '-', parse_url(site_url(), PHP_URL_HOST)) . '//Events//' . $locale;
        $icsProps = [
            'BEGIN:VCALENDAR',
            'METHOD:PUBLISH',
            'PRODID:' . $this->split($prodid),
            'VERSION:2.0'
        ];

        foreach ($this->timezoneComponents() as $line) {
            $icsProps[] = $line;
        }

        if (!empty($this->data)) {
            foreach ($this->data as $properties) {
                $icsProps[] = 'BEGIN:VEVENT';
                $props = [];
                $timezone = !empty($properties['timezone']) && $properties['timezone'] !== 'UTC'
                    ? new \DateTimeZone($properties['timezone']) : null;
                foreach ($properties as $k => $v) {
                    if ($k === 'timezone') {
                        continue;
                    }
                    $key = strtoupper($k);
                    if ($timezone && in_array($k, ['dtstart', 'dtend'], true)) {
                        $v = (new \DateTimeImmutable($v, new \DateTimeZone('UTC')))
                            ->setTimezone($timezone)->format('Ymd\THis');
                        $key .= ';TZID="' . $timezone->getName() . '"';
                    }
                    $props[$key] = $v;
                }
                $props['DTSTAMP'] = $this->formatTimestamp('now');
                foreach ($props as $k => $v) {
                    $icsProps[] = "$k:$v";
                }
                $icsProps[] = 'END:VEVENT';
            }
        }

        $icsProps[] = 'END:VCALENDAR';
        return $icsProps;
    }

    /**
     * Collect one timezone definition per zone used by recurring events.
     *
     * @return string[] Unfolded timezone component lines.
     */
    private function timezoneComponents(): array
    {
        $ranges = [];
        foreach ($this->data as $properties) {
            $zone = $properties['timezone'] ?? 'UTC';
            if ($zone === 'UTC' || empty($properties['dtstart'])) {
                continue;
            }
            $start = (new \DateTimeImmutable($properties['dtstart']))->getTimestamp();
            $end = (new \DateTimeImmutable($properties['dtend'] ?? $properties['dtstart']))->getTimestamp();
            if (preg_match('/(?:^|;)UNTIL=(\d{8}T\d{6}Z?)(?:;|$)/', $properties['rrule'] ?? '', $matches)) {
                $end = max($end, (new \DateTimeImmutable($matches[1], new \DateTimeZone($zone)))->getTimestamp());
            }
            foreach (['exdate', 'rdate'] as $property) {
                foreach (array_filter(explode(',', $properties[$property] ?? '')) as $date) {
                    $timestamp = (new \DateTimeImmutable($date))->getTimestamp();
                    $start = min($start, $timestamp);
                    $end = max($end, $timestamp);
                }
            }
            $ranges[$zone] = [
                min($start, $ranges[$zone][0] ?? $start),
                max($end, $ranges[$zone][1] ?? $end),
            ];
        }

        $lines = [];
        foreach ($ranges as $zone => [$start, $end]) {
            $lines = array_merge($lines, TimeZone::build(new \DateTimeZone($zone), $start, $end));
        }
        return $lines;
    }

    /**
     * Sanitize a string value.
     * @param string $value
     * @param string $key
     * @return string
     */
    private function sanitizeValue(string $value, string $key = '')
    {
        switch ($key) {
            case 'dtend':
            case 'dtstamp':
            case 'dtstart':
                $value = $this->formatTimestamp($value);
                break;
            case 'description':
            case 'location':
                $value = $this->escStr($value);
                break;
            default:
                $value = $value;
        }

        return $value;
    }

    /**
     * Format the date and time.
     * @param string $timestamp
     * @return string
     */
    private function formatTimestamp(string $timestamp)
    {
        $dt = new \DateTimeImmutable($timestamp, new \DateTimeZone('UTC'));
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format(self::DT_FORMAT);
    }

    /**
     * Escapes , and ; characters in text type fields.
     * @param string $value The string to escape
     * @return string
     */
    private function escStr(string $value): string
    {
        $value = preg_replace('/((?<!\\\),|(?<!\\\);)/', '\\\$1', $value);
        $value = preg_replace('/((?<!\\\)\\\(?!,|;|n|N|\\\))/', '\\\\$1', $value);
        return $value;
    }

    /**
     * Splits a string into new lines if necessary.
     * RFC-5545 (3.1. Content Lines)
     * @param string $value
     * @return string
     */
    private function split(string $value): string
    {
        $value = str_replace("\n", "\\n", str_replace("\r\n", "\n", $value));
        $length = strlen($value);

        if ($length <= 75) {
            return $value;
        }

        $output = '';
        $start = 0;

        while ($start < $length) {
            $cut = mb_strcut($value, $start, self::LINE_LENGTH, 'UTF-8');
            $output .= $cut;
            $start += strlen($cut);

            if ($start < $length) {
                $output .= self::CRLF . ' ';
            }
        }

        return $output;
    }
}
