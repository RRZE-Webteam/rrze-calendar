<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

class ICS
{
    public const DT_FORMAT = 'Ymd\THis\Z';

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
        return implode("\r\n", $rows);
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
            case 'summary':
            case 'description':
            case 'location':
                $value = $this->escStr($value, true);
                break;
            default:
                $value = $this->escStr($value);
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
     * Escape special characters.
     *
     * @param string $input
     * @param boolean $specialChars
     * @return string
     */
    private function escStr(string $input, $specialChars = false): string
    {
        if ($specialChars) {
            $input = preg_replace('/([\,;])/', '\\\$1', $input);
        }
        $input = str_replace("\n", "\\n", $input);
        $input = str_replace("\r", "\\r", $input);
        return $input;
    }

    /**
     * Split content lines.
     * RFC-5545 (3.1. Content Lines)
     *
     * @param string $input
     * @param integer $lineLimit
     * @return string
     */
    private function split(string $input, int $lineLimit = 70): string
    {
        $output = '';
        $line = '';
        $pos = 0;

        while ($pos < mb_strlen($input)) {
            // Find newlines
            $newLinepos = mb_strpos($input, "\n", $pos + 1);
            if (!$newLinepos) {
                $newLinepos = mb_strlen($input);
            }
            $line = mb_substr($input, $pos, $newLinepos - $pos);

            if (mb_strlen($line) <= $lineLimit) {
                $output .= $line;
            } else {
                // The line break limit of the first line is $lineLimit
                $output .= mb_substr($line, 0, $lineLimit);
                $line = mb_substr($line, $lineLimit);

                // Subsequent line break limit is $lineLimit - 1 due to leading whitespace
                $output .= "\n " . mb_substr($line, 0, $lineLimit - 1);

                while (mb_strlen($line) > $lineLimit - 1) {
                    $line = mb_substr($line, $lineLimit - 1);
                    $output .= "\n " . mb_substr($line, 0, $lineLimit - 1);
                }
            }
            $pos = $newLinepos;
        }

        return $output;
    }
}
