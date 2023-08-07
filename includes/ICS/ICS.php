<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;

class ICS
{
    const DT_FORMAT = 'Ymd\THis\Z';

    protected $properties = [];

    private $availableProperties = [
        'uid',
        'description',
        'dtend',
        'dtstart',
        'location',
        'summary',
        'url'
    ];

    public function __construct($props)
    {
        $this->set($props);
    }

    public function set($key, $value = false)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
        } else {
            if (in_array($key, $this->availableProperties)) {
                $this->properties[$key] = $this->sanitizeValue($value, $key);
            }
        }
    }

    public function make()
    {
        $rows = $this->build();
        return implode("\r\n", $rows);
    }

    private function build()
    {
        $icsProps = [
            'BEGIN:VCALENDAR',
            'METHOD:PUBLISH',
            'PRODID:' . str_replace('.', '-', parse_url(site_url(), PHP_URL_HOST)) . '//Events',
            'VERSION:2.0',
            'BEGIN:VEVENT'
        ];

        $props = [];
        foreach ($this->properties as $k => $v) {
            $props[strtoupper($k . ($k === 'url' ? ';VALUE=URI' : ''))] = $v;
        }

        $props['DTSTAMP'] = $this->formatTimestamp('now');

        foreach ($props as $k => $v) {
            $icsProps[] = "$k:$v";
        }

        $icsProps[] = 'END:VEVENT';
        $icsProps[] = 'END:VCALENDAR';

        return $icsProps;
    }

    private function sanitizeValue($value, $key = false)
    {
        switch ($key) {
            case 'dtend':
            case 'dtstamp':
            case 'dtstart':
                $value = $this->formatTimestamp($value);
                break;
            default:
                $value = $this->escStr($value);
                $value = str_replace("\r\n", "\\n", $value);
                $value = $this->split($value);
        }

        return $value;
    }

    private function formatTimestamp($timestamp)
    {
        $dt = new \DateTime($timestamp);
        return $dt->format(self::DT_FORMAT);
    }

    private function escStr($str)
    {
        return preg_replace('/([\,;])/', '\\\$1', $str);
    }

    private function split($value)
    {
        $value = trim($value);
        $lines = array();
        while (strlen($value) > (75)) {
            $line = mb_substr($value, 0, 75);
            $llength = mb_strlen($line);
            $lines[] = $line . chr(13) . chr(10) . chr(32);
            $value = mb_substr($value, $llength);
        }
        if (!empty($value)) {
            $lines[] = $value;
        }
        return (implode($lines));
    }
}
