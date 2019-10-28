<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use DateTime;

class Export
{
    /**
     * [$urlHost description]
     * @var string
     */
    protected $urlHost;

    /**
     * [protected description]
     * @var string
     */
    protected $urlPath;

    /**
     * [protected description]
     * @var array
     */
    protected $data;

    /**
     * [__construct description]
     */
    public function __construct() {
        $this->data = [];
        $this->urlHost = parse_url(site_url(), PHP_URL_HOST);
        $this->urlPath = parse_url(site_url(), PHP_URL_PATH);
    }

    public function createCalendar() {
        $prodId= sprintf(
            '%1$s%2$s',
            $this->urlHost,
            $this->urlPath ? '/' . $this->urlPath : ''
        );

        $this->data[] = "BEGIN:VCALENDAR";
        $this->data[] = "VERSION:2.0";
        $this->data[] = "PRODID:{$prodId}";        
    }

    public function addVevent($event)
    {
        $this->data[] = "BEGIN:VEVENT";
        $this->data[] = "UID:{$event->uid}";
        $this->data[] = "DTSTART:{$this->formatDate($event->start)}";
        $this->data[] = "DTEND:{$this->formatDate($event->end)}";
        $this->data[] = "DTSTAMP:{$this->formatDate($event->start)}";
        $this->data[] = "CREATED:{$this->formatDate('now')}";
        $this->data[] = "DESCRIPTION:{$this->getEscapedValue($event->description)}";
        $this->data[] = "LAST-MODIFIED:{$this->getEscapedValue($event->start)}";
        $this->data[] = "LOCATION:{$this->location}";
        $this->data[] = "SUMMARY:{$this->getEscapedValue($event->summary)}";
        $this->data[] = ($event->rrule) ? "RRULE:{$event->rrule}" : '';
        $this->data[] = ($event->exrule) ? "EXRULE:{$event->exrule}" : '';
        $this->data[] = ($event->rdate) ? "RDATE:{$event->rdate}" : '';
        $this->data[] = ($event->exdate) ? "EXDATE:{$event->exdate}" : '';
        $this->data[] = "SEQUENCE:0";
        $this->data[] = "STATUS:CONFIRMED";
        $this->data[] = "TRANSP:OPAQUE";
        $this->data[] = "END:VEVENT";
    }

    /**
     * Get the start time set for the even
     * @return string
     */
    protected function formatDate($value)
    {
        $date = new DateTime($value);
        return $date->format('Ymd\THis\Z');
    }
    
    public function output() {
        $filename = sprintf(
            '%1$s%2$s.ics',
            $this->urlHost,
            $this->urlPath ? '-' . $this->urlPath : ''
        );

        header('Content-Description: ICS');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Type: text/calendar; charset=' . get_option('blog_charset'), true);
        echo $this->render();
        exit;
    }

    /**
     * Renders an array containing the lines of the iCal file.
     *
     * @return array
     */
    protected function build()
    {
        $ret = [];

        $this->data[] = "END:VCALENDAR";

        foreach ($this->data as $line) {
            foreach ($this->fold($line) as $l) {
                $ret[] = $l;
            }
        }

        return $ret;
    }

    /**
     * Renders the output.
     *
     * @return string
     */
    public function render()
    {
        return implode("\r\n", $this->build());
    }

    /**
     * Folds a single line.
     *
     * According to RFC 5545, all lines longer than 75 characters should be folded
     *
     * @see https://tools.ietf.org/html/rfc5545#section-5
     * @see https://tools.ietf.org/html/rfc5545#section-3.1
     *
     * @param string $string
     * @return array
     */
    public static function fold($string)
    {
        $lines = [];

        if (function_exists('mb_strcut')) {
            while (strlen($string) > 0) {
                if (strlen($string) > 75) {
                    $lines[] = mb_strcut($string, 0, 75, 'utf-8');
                    $string = ' ' . mb_strcut($string, 75, strlen($string), 'utf-8');
                } else {
                    $lines[] = $string;
                    $string = '';
                    break;
                }
            }
        } else {
            $array = preg_split('/(?<!^)(?!$)/u', $string);
            $line = '';
            $lineNo = 0;
            foreach ($array as $char) {
                $charLen = strlen($char);
                $lineLen = strlen($line);
                if ($lineLen + $charLen > 75) {
                    $line = ' ' . $char;
                    ++$lineNo;
                } else {
                    $line .= $char;
                }
                $lines[$lineNo] = $line;
            }
        }

        return $lines;
    }

    public function getEscapedValue($value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\\"', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace("\n", '\\n', $value);
        $value = str_replace([
            "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
            "\x08", "\x09", /* \n*/ "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
            "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
            "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F",
            "\x7F",
        ], '', $value);

        return $value;
    }    
}
