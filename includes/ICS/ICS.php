<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

class ICS
{
    // Date and time format
    public const DT_FORMAT = 'Ymd\THis\Z';

    // Carriage return and line feed
    const CRLF = "\r\n";

    // Length of line in bytes
    const LINE_LENGTH = 70;

    /**
     * iCalendar data array.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Available iCalendar properties.
     *
     * @var array
     */
    private $availableProperties = [
        'uid',
        'description',
        'dtend',
        'dtstart',
        'location',
        'summary',
        'rrule',
        'exdate;value=date', // For excluding dates
        'rdate;value=date'   // For including dates
    ];

    /**
     * Construct method.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->setData($data);
    }

    /**
     * Create an array with iCalendar data.
     *
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
     *
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
     *
     * @return string
     */
    public function build(): string
    {
        $rows = array_map(
            fn ($row) => $this->split($row),
            $this->render()
        );
        return implode(self::CRLF, $rows);
    }

    /**
     * Create an array with iCalendar properties.
     *
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

        if (!empty($this->data)) {
            foreach ($this->data as $properties) {
                $icsProps[] = 'BEGIN:VEVENT';
                $props = [];
                foreach ($properties as $k => $v) {
                    $props[strtoupper($k)] = $v;
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
     * Sanitize a string value.
     *
     * @param string $value
     * @param string $key
     * @return string
     */
    private function sanitizeValue(string $value, string $key = ''): string
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
     *
     * @param string $timestamp
     * @return string
     */
    private function formatTimestamp(string $timestamp): string
    {
        $dt = new \DateTime($timestamp);
        return $dt->format(self::DT_FORMAT);
    }

    /**
     * Escapes , and ; characters in text type fields.
     *
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
     *
     * @param string $value
     * @return string
     */
    private function split(string $value): string
    {
        // Newlines need to be converted to literal \n
        $value = str_replace("\n", "\\n", str_replace("\r\n", "\n", $value));

        // Get number of bytes
        $length = strlen($value);

        $output = '';

        if ($length > 75) {
            $start = 0;

            while ($start < $length) {
                $cut = mb_strcut($value, $start, self::LINE_LENGTH, 'UTF-8');
                $output .= $cut;
                $start = $start + strlen($cut);

                // Add space if not last line
                if ($start < $length) {
                    $output .= self::CRLF . ' ';
                }
            }
        } else {
            $output = $value;
        }

        return $output;
    }
}
