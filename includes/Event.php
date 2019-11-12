<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

use RRZE_Calendar;
use \RRZE\Calendar\Util;
use \DateTime;

class Event {

    public $id;
    public $start;
    public $end;    
    public $allday;
    public $recurrence_rules;
    public $rrules_human_readable;
    public $exception_rules;
    public $recurrence_dates;
    public $exception_dates;
    public $summary;
    public $description;
    public $location;
    public $slug;
    public $ical_feed_id;
    public $ical_feed_url;
    public $ical_source_url;
    public $ical_uid;
    public $category;
    public $tags;
    public $feed;

    public function __construct($data) 
    {
        if (is_array($data) && !empty($data)) {
            foreach ($this as $property => $value) {
                if (array_key_exists($property, $data)) {
                    $this->{$property} = $data[$property];
                    unset($data[$property]);                  
                }
            }
        }
    }

    public function __set($name, $value) {
        switch ($name) {
            default:
                $this->{$name} = $value;
                break;
        }
    }

    public function __get($name) {
        switch ($name) {
            case 'uid':
                return $this->ical_feed_id . '@' . bloginfo('url');
            case 'endpoint_url':
                return RRZE_Calendar::endpoint_url($this->slug);
            case 'subscribe_url':
                return Util::webCalUrl(['event-ids' => $this->id]);
            case "multiday":
                return $this->is_multiday();
            case 'short_start_time':
                return Util::getShortTime(strtotime($this->start));
            case 'short_end_time':
                return Util::getShortTime(strtotime($this->end));             
            case 'short_start_date':
                return Util::getShortDate(strtotime($this->start));
            case 'short_end_date':
                return Util::getShortDate(strtotime($this->end));
            case 'start_time':
                return Util::getMediumTime(strtotime($this->start));
            case 'end_time':
                return Util::getMediumTime(strtotime($this->end));
            case 'long_start_time':
                return Util::getLongTime(strtotime($this->start));
            case 'long_end_time':
                return Util::getLongTime(strtotime($this->end));
            case 'long_start_date':
                return Util::getLongDate(strtotime($this->start));
            case 'long_end_date':
                return Util::getLongDate(strtotime($this->end));             
            case 'start_year':
            case 'start_year_html':
                return Util::getYearDate(strtotime($this->start));
            case 'start_month':
            case 'start_month_html':
                return Util::getMonthDate(strtotime($this->start));
            case 'start_day':
            case 'start_day_html':
                return Util::getDayDate(strtotime($this->start));
            default:
                return '';
        }
    }

    public function get_property($property) {
        return $this->property;
    }
    
    public function get_start() {
        return $this->start;
    }

    public function get_end() {
        return $this->end;
    }

    public function get_duration() {
        return $this->end - $this->start;
    }
    
    public function is_multiday() {
        $start = Util::gmtToLocal(strtotime($this->start));
        $end = Util::gmtToLocal(strtotime($this->end));

        $startDate = new DateTime();
        $startDate->setTimestamp($start);
        $startDate->setTime(0, 0, 0);

        $endDate = new DateTime();
        $endDate->setTimestamp($end);
        $endDate->setTime(0, 0, 0);

        $diff = $startDate->diff($endDate);
        
        return ($diff->format('%d') > 1 || (!$this->allday && $startDate->format('Y-m-d') != $endDate->format('Y-m-d')));        
    }
    
}
