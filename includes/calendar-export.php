<?php

class RRZE_Calendar_Export {
    
    protected $rrze_calendar;
    protected static $instance = NULL;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
        $this->rrze_calendar = RRZE_Calendar::instance();
    }

    public function export_events() {
        
        $feed_ids = !empty($_REQUEST['feed-ids']) ? $_REQUEST['feed-ids'] : FALSE;
        $event_ids = !empty($_REQUEST['event-ids']) ? $_REQUEST['event-ids'] : FALSE;
        $filter = array();

        if ($feed_ids) {
            $filter['feed_ids'] = explode(',', $feed_ids);
        }
        
        if ($event_ids) {
            $filter['event_ids'] = explode(',', $event_ids);
        }

        $start = $event_ids !== FALSE ? FALSE : time() - DAY_IN_SECONDS;
        $end = FALSE;

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
            $this->insert_event_in_calendar($event, $c, $export = TRUE);
        }
        
        $str = $c->createCalendar();

        header('Content-type: text/calendar; charset=utf-8');
        echo $str;
        exit;
    }
    
    public function insert_event_in_calendar($event, &$c, $export = FALSE) {
        $tz = get_option('timezone_string');

        $e = & $c->newComponent('vevent');
        $e->setProperty('uid', $event->ical_uid);
        $e->setProperty('url', RRZE_Calendar::endpoint_url($event->slug));
        $e->setProperty('summary', html_entity_decode($event->summary, ENT_QUOTES, 'UTF-8'));
        $e->setProperty('description', $event->description);
        
        if ($event->allday) {
            $dtstart = $dtend = array();
            $dtstart["VALUE"] = $dtend["VALUE"] = 'DATE';

            if ($tz && !$export) {
                $dtstart["TZID"] = $dtend["TZID"] = $tz;
            }

            if ($export) {
                $e->setProperty('dtstart', gmdate("Ymd", RRZE_Calendar_Functions::gmt_to_local($event->start)), $dtstart);
                $e->setProperty('dtend', gmdate("Ymd", RRZE_Calendar_Functions::gmt_to_local($event->end)), $dtend);
            } else {
                $e->setProperty('dtstart', gmdate("Ymd\T", RRZE_Calendar_Functions::gmt_to_local($event->start)), $dtstart);
                $e->setProperty('dtend', gmdate("Ymd\T", RRZE_Calendar_Functions::gmt_to_local($event->end)), $dtend);
            }
        } else {
            $dtstart = $dtend = array();
            if ($tz) {
                $dtstart["TZID"] = $dtend["TZID"] = $tz;
            }
            $e->setProperty('dtstart', gmdate("Ymd\THis\Z", RRZE_Calendar_Functions::gmt_to_local($event->start)), $dtstart);

            $e->setProperty('dtend', gmdate("Ymd\THis\Z",RRZE_Calendar_Functions::gmt_to_local($event->end)), $dtend);
        }
        
        $e->setProperty('location', $event->location);        
        $e->setProperty('contact', '');

        $rrule = array();
        if (!empty($event->recurrence_rules)) {
            $rules = array();
            foreach (explode(';', $event->recurrence_rules) AS $v) {
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
            foreach (explode(';', $event->exception_rules) AS $v) {
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
