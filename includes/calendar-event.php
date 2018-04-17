<?php

class RRZE_Calendar_Event {

    public $id;
    public $start;
    public $end;
    public $e_start;
    public $e_end;    
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

    public function __construct($data = NULL) {
        if ($data == NULL) {
            return;
        }
        if (is_array($data)) {
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
                return $this->$ical_feed_id . '@' . bloginfo('url');

            case 'endpoint_url':
                return RRZE_Calendar::endpoint_url($this->slug);

            case 'subscribe_url':
                return RRZE_Calendar::webcal_url(array('event-ids' => $this->id));
            case "multiday":
                return $this->is_multiday();
            case 'short_start_time':
                return RRZE_Calendar_Functions::get_short_time($this->start);
            case 'short_end_time':
                return RRZE_Calendar_Functions::get_short_time($this->end);
            case 'short_e_start_time':
                return RRZE_Calendar_Functions::get_short_time($this->e_start);
            case 'short_e_end_time':
                return RRZE_Calendar_Functions::get_short_time($this->e_end);                
            case 'short_start_date':
                return RRZE_Calendar_Functions::get_short_date($this->start);
            case 'short_end_date':
                return RRZE_Calendar_Functions::get_short_date($this->end);
            case 'start_time':
                return RRZE_Calendar_Functions::get_medium_time($this->start);
            case 'end_time':
                return RRZE_Calendar_Functions::get_medium_time($this->end);
            case 'long_start_time':
                return RRZE_Calendar_Functions::get_long_time($this->start);
            case 'long_end_time':
                return RRZE_Calendar_Functions::get_long_time($this->end);
            case 'long_start_date':
                return RRZE_Calendar_Functions::get_long_date($this->start);
            case 'long_end_date':
                return RRZE_Calendar_Functions::get_long_date($this->end - 1);
            case 'long_e_start_date':
                return RRZE_Calendar_Functions::get_long_date($this->e_start);
            case 'long_e_end_date':
                return RRZE_Calendar_Functions::get_long_date($this->e_end);                
            case 'start_year':
            case 'start_year_html':
                return RRZE_Calendar_Functions::get_year_date($this->start);

            case 'start_month':
            case 'start_month_html':
                return RRZE_Calendar_Functions::get_month_date($this->start);
            case 'start_day':
            case 'start_day_html':
                return RRZE_Calendar_Functions::get_day_date($this->start);
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
        $e_start = RRZE_Calendar_Functions::gmt_to_local($this->e_start) - date('Z', $this->e_start);
        $e_end = RRZE_Calendar_Functions::gmt_to_local($this->e_end) - date('Z', $this->e_end);
        
        $start_date = new DateTime();
        $start_date->setTimestamp($e_start);
        $start_date->setTime(0, 0, 0);

        $end_date = new DateTime();
        $end_date->setTimestamp($e_end);
        $end_date->setTime(0, 0, 0);
        
        $diff = $start_date->diff($end_date);
        
        return ($diff->days > 0 && !$this->recurrence_rules);        
    }
    
}
