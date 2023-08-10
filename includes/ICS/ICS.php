<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

class ICS
{
    const DT_FORMAT = 'Ymd\THis\Z';

    protected $data = [];

    private $availableProperties = [
        'uid',
        'description',
        'dtend',
        'dtstart',
        'location',
        'summary'
    ];

    public function __construct(array $data)
    {
        $this->setData($data);
    }

    public function setData(array $data)
    {
        foreach ($data as $postId => $props) {
            $this->data[$postId] = $this->setProps($props);
        }
    }

    private function setProps(array $props)
    {
        $properties = [];
        foreach ($props as $key => $value) {
            if (in_array($key, $this->availableProperties)) {
                $properties[$key] = $this->sanitizeValue($value, $key);
            }
        }
        return $properties;
    }

    public function build()
    {
        $rows = $this->render();
        return implode("\r\n", $rows);
    }

    private function render()
    {
        $locale = strtoupper(substr(get_locale(), 0, 2));
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
