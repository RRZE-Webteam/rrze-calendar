<?php

class RRZE_Calendar_Event {

    public $id;
    public $start;
    public $end;
    public $start_truncated;
    public $end_truncated;
    public $allday;
    public $recurrence_rules;
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
                return (RRZE_Calendar_Functions::get_long_date($this->start) != RRZE_Calendar_Functions::get_long_date($this->end - 1));

            case "multiday_end_day":
                return RRZE_Calendar_Functions::get_multiday_end_day($this->end - 1);

            case 'short_start_time':
                return RRZE_Calendar_Functions::get_short_time($this->start);

            case 'short_end_time':
                return RRZE_Calendar_Functions::get_short_time($this->end);

            case 'short_start_date':
                return RRZE_Calendar_Functions::get_short_date($this->start);

            case 'short_end_date':
                return RRZE_Calendar_Functions::get_short_date($this->end - 1);

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

    public function is_whole_day() {
        return (bool) $this->allday;
    }

    public function get_start() {
        return $this->start;
    }

    public function get_end() {
        return $this->end;
    }

    public function get_rules($excluded = array()) {
        require_once(plugin_dir_path(RRZE_Calendar::$plugin_file) . 'includes/calendar-rules.php');
        return new RRZE_Calendar_Rules($this->recurrence_rules, $this->start, $excluded);
    }

    public function get_duration() {
        return $this->end - $this->start;
    }

}
