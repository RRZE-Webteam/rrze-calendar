<?php

class RRZE_Calendar_Export
{
    protected $rrze_calendar;
    protected static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->rrze_calendar = RRZE_Calendar::instance();
    }

    public function export_events()
    {
        $feed_ids = !empty($_REQUEST['feed-ids']) ? $_REQUEST['feed-ids'] : false;
        $event_ids = !empty($_REQUEST['event-ids']) ? $_REQUEST['event-ids'] : false;
        $filter = array();

        if ($feed_ids) {
            $filter['feed_ids'] = explode(',', $feed_ids);
        }

        if ($event_ids) {
            $filter['event_ids'] = explode(',', $event_ids);
        }

        $start = $event_ids !== false ? false : time() - DAY_IN_SECONDS;
        $end = false;

        $events = $this->rrze_calendar->get_matching_events($start, $end, $filter);

        $c = new vcalendar();
        $c->setProperty('calscale', 'GREGORIAN');
        $c->setProperty('method', 'PUBLISH');
        // MS Outlook problem workaround
        //$c->setProperty('X-WR-CALNAME', get_bloginfo('name'));
        //$c->setProperty('X-WR-CALDESC', get_bloginfo('description'));

        $tz = get_option('timezone_string');
        if ($tz) {
            $c->setProperty('X-WR-TIMEZONE', $tz);
            $tz_xprops = array('X-LIC-LOCATION' => $tz);
            iCalUtilityFunctions::createTimezone($c, $tz, $tz_xprops);
        }

        foreach ($events as $event) {
            $this->insert_event_in_calendar($event, $c);
        }

        $str = $c->createCalendar();

        $url_host = parse_url(site_url(), PHP_URL_HOST);
        $url_path = parse_url(site_url(), PHP_URL_PATH);
        $filename = sprintf('%1$s%2$s.ics', $url_host, $url_path ? '.' . $url_path : '');

        header('Content-Description: ICS');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Type: text/calendar; charset=' . get_option('blog_charset'), true);

        echo $str;
        exit;
    }

    public function insert_event_in_calendar($event, &$c)
    {
        $tz = get_option('timezone_string');

        $e = & $c->newComponent('vevent');
        $e->setProperty('uid', $event->ical_uid);
        $e->setProperty('url', RRZE_Calendar::endpoint_url($event->slug));
        $e->setProperty('summary', html_entity_decode($event->summary, ENT_QUOTES, 'UTF-8'));
        $e->setProperty('description', $event->description);

        if ($event->allday) {
            $dtstart = $dtend = array();
            $dtstart["VALUE"] = $dtend["VALUE"] = 'DATE';
            $dtstart["TZID"] = $dtend["TZID"] = $tz;

            $e->setProperty('dtstart', gmdate("Ymd", RRZE_Calendar_Functions::gmt_to_local($event->start)), $dtstart);
            $e->setProperty('dtend', gmdate("Ymd", RRZE_Calendar_Functions::gmt_to_local($event->end)), $dtend);
        } else {
            $dtstart = $dtend = array();
            $dtstart["TZID"] = $dtend["TZID"] = $tz;

            $e->setProperty('dtstart', gmdate("Ymd\THis", RRZE_Calendar_Functions::gmt_to_local($event->start)), $dtstart);
            $e->setProperty('dtend', gmdate("Ymd\THis", RRZE_Calendar_Functions::gmt_to_local($event->end)), $dtend);
        }

        $e->setProperty('location', $event->location);
        $e->setProperty('contact', '');

        $rrule = array();
        if (!empty($event->recurrence_rules)) {
            $rules = array();
            foreach (explode(';', $event->recurrence_rules) as $v) {
                if (strpos($v, '=') === false) {
                    continue;
                }

                list($k, $v) = explode('=', $v);

                switch ($k) {
                    case 'BYSECOND':
                    case 'BYMINUTE':
                    case 'BYHOUR':
                    case 'BYDAY':
                    case 'BYMONTHDAY':
                    case 'BYYEARDAY':
                    case 'BYWEEKNO':
                    case 'BYMONTH':
                    case 'BYSETPOS':
                        $exploded = explode(',', $v);
                        break;
                    default:
                        $exploded = $v;
                        break;
                }

                if ($k == 'BYDAY') {
                    $v = array();
                    foreach ($exploded as $day) {
                        $v[] = array('DAY' => $day);
                    }
                } else {
                    $v = $exploded;
                }
                $rrule[$k] = $v;
            }
        }

        $exrule = array();
        if (!empty($event->exception_rules)) {
            $rules = array();
            foreach (explode(';', $event->exception_rules) as $v) {
                if (strpos($v, '=') === false) {
                    continue;
                }

                list($k, $v) = explode('=', $v);

                switch ($k) {
                    case 'BYSECOND':
                    case 'BYMINUTE':
                    case 'BYHOUR':
                    case 'BYDAY':
                    case 'BYMONTHDAY':
                    case 'BYYEARDAY':
                    case 'BYWEEKNO':
                    case 'BYMONTH':
                    case 'BYSETPOS':
                        $exploded = explode(',', $v);
                        break;
                    default:
                        $exploded = $v;
                        break;
                }

                if ($k == 'BYDAY') {
                    $v = array();
                    foreach ($exploded as $day) {
                        $v[] = array('DAY' => $day);
                    }
                } else {
                    $v = $exploded;
                }
                $exrule[$k] = $v;
            }
        }

        if (!empty($rrule)) {
            $e->setProperty('rrule', $rrule);
        }

        if (!empty($exrule)) {
            $e->setProperty('exrule', $exrule);
        }

        if (!empty($event->exception_dates)) {
            $e->setProperty('exdate', explode(',', $event->exception_dates));
        }
    }
}
