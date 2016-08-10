<?php

class RRZE_Calendar_Rules {

    protected $weekdays = array('MO' => 'monday', 'TU' => 'tuesday', 'WE' => 'wednesday', 'TH' => 'thursday', 'FR' => 'friday', 'SA' => 'saturday', 'SU' => 'sunday');
    protected $knownRules = array('month', 'weekno', 'day', 'monthday', 'yearday', 'hour', 'minute');
    protected $ruleModifiers = array('wkst');
    protected $simpleMode = true;
    protected $rules = array('freq' => 'yearly', 'interval' => 1);
    protected $start = 0;
    protected $freq = '';
    protected $excluded;
    protected $added;
    protected $cache;

    public function __construct($rule, $start, $excluded = array(), $added = array(), $exrule = false) {
        $this->start = $start;
        $this->excluded = array();

        $rules = array();
        foreach (explode(';', $rule) AS $v) {
            if (strpos($v, '=') === false)
                continue;

            list($k, $v) = explode('=', $v);
            $this->rules[strtolower($k)] = $v;
        }

        if (isset($this->rules['until']) && is_string($this->rules['until'])) {
            $this->rules['until'] = strtotime($this->rules['until']);
        }
        $this->freq = strtolower($this->rules['freq']);

        foreach ($this->knownRules AS $rule) {
            if (isset($this->rules['by' . $rule])) {
                if ($this->is_prerule($rule, $this->freq)) {
                    $this->simpleMode = false;
                }
            }
        }

        if (!$this->simpleMode) {
            if (!(isset($this->rules['byday']) || isset($this->rules['bymonthday']) || isset($this->rules['byyearday']))) {
                $this->rules['bymonthday'] = date('d', $this->start);
            }
        }

        if (isset($this->rules['count'])) {
            if ($exrule)
                $this->rules['count'] ++;

            $cache[$ts] = $ts = $this->start;
            for ($n = 1; $n < $this->rules['count']; $n++) {
                $ts = $this->find_next($ts);
                $cache[$ts] = $ts;
            }
            $this->rules['until'] = $ts;

            if (!empty($excluded)) {
                foreach ($excluded as $ts) {
                    unset($cache[$ts]);
                }
            }

            if (!empty($added)) {
                $cache = $cache + $added;
                asort($cache);
            }

            $this->cache = array_values($cache);
        }

        $this->excluded = $excluded;
        $this->added = $added;
    }

    public function get_all_occurrences() {
        if (empty($this->cache)) {

            $next = $this->first_occurrence();
            while ($next) {
                $cache[] = $next;
                $next = $this->find_next($next);
            }
            if (!empty($this->added)) {
                $cache = $cache + $this->added;
                asort($cache);
            }
            $this->cache = $cache;
        }
        return $this->cache;
    }

    public function previous_occurrence($offset) {
        if (!empty($this->cache)) {
            $t2 = $this->start;
            foreach ($this->cache as $ts) {
                if ($ts >= $offset)
                    return $t2;
                $t2 = $ts;
            }
        } else {
            $ts = $this->start;
            while (($t2 = $this->find_next($ts)) < $offset) {
                if ($t2 == false) {
                    break;
                }
                $ts = $t2;
            }
        }
        return $ts;
    }

    public function next_occurrence($offset) {
        if ($offset < $this->start)
            return $this->first_occurrence();
        return $this->find_next($offset);
    }

    public function first_occurrence() {
        $t = $this->start;
        if (is_array($this->excluded) && in_array($t, $this->excluded))
            $t = $this->find_next($t);
        return $t;
    }

    public function last_occurrence() {
        $this->get_all_occurrences();
        return end($this->cache);
    }

    public function find_next($offset) {
        if (!empty($this->cache)) {
            foreach ($this->cache as $ts) {
                if ($ts > $offset)
                    return $ts;
            }
        }

        $debug = false;

        if ($offset === false || (isset($this->rules['until']) && $offset > $this->rules['until'])) {
            if ($debug)
                echo 'STOP: ' . date('r', $offset) . "\n";
            return false;
        }

        $found = true;

        if ($debug)
            echo 'O: ' . date('r', $offset) . "\n";
        $hour = (in_array($this->freq, array('hourly', 'minutely')) && $offset > $this->start) ? date('H', $offset) : date('H', $this->start);
        $minute = (($this->freq == 'minutely' || isset($this->rules['byminute'])) && $offset > $this->start) ? date('i', $offset) : date('i', $this->start);
        $t = mktime($hour, $minute, date('s', $this->start), date('m', $offset), date('d', $offset), date('Y', $offset));
        if ($debug)
            echo 'START: ' . date('r', $t) . "\n";

        if ($this->simpleMode) {
            if ($offset < $t) {
                $ts = $t;
                if ($ts && in_array($ts, $this->excluded))
                    $ts = $this->find_next($ts);
            } else {
                $ts = $this->find_starting_point($t, $this->rules['interval'], false);
                if (!$this->valid_date($ts)) {
                    $ts = $this->find_next($ts);
                }
            }
            return $ts;
        }

        $eop = $this->find_end_of_period($offset);
        if ($debug)
            echo 'EOP: ' . date('r', $eop) . "\n";

        foreach ($this->knownRules AS $rule) {
            if ($found && isset($this->rules['by' . $rule])) {
                if ($this->is_prerule($rule, $this->freq)) {
                    $subrules = explode(',', $this->rules['by' . $rule]);
                    $_t = null;
                    foreach ($subrules AS $subrule) {
                        $imm = call_user_func_array(array($this, 'rule_by_' . $rule), array($subrule, $t));
                        if ($imm === false) {
                            break;
                        }
                        if ($debug)
                            echo strtoupper($rule) . ': ' . date('r', $imm) . ' A: ' . ((int) ($imm > $offset && $imm < $eop)) . "\n";
                        if ($imm > $offset && $imm < $eop && ($_t == null || $imm < $_t)) {
                            $_t = $imm;
                        }
                    }
                    if ($_t !== null) {
                        $t = $_t;
                    } else {
                        $found = $this->valid_date($t);
                    }
                }
            }
        }

        if ($offset < $this->start && $this->start < $t) {
            $ts = $this->start;
        } else if ($found && ($t != $offset)) {
            if ($this->valid_date($t)) {
                if ($debug)
                    echo 'OK' . "\n";
                $ts = $t;
            } else {
                if ($debug)
                    echo 'Invalid' . "\n";
                $ts = $this->find_next($t);
            }
        } else {
            if ($debug)
                echo 'Not found' . "\n";
            $ts = $this->find_next($this->find_starting_point($offset, $this->rules['interval']));
        }
        if (is_array($this->excluded) && $ts && in_array($ts, $this->excluded))
            return $this->find_next($ts);

        return $ts;
    }

    private function find_starting_point($offset, $interval, $truncate = true) {
        $_freq = ($this->freq == 'daily') ? 'day__' : $this->freq;
        $t = '+' . $interval . ' ' . substr($_freq, 0, -2) . 's';
        if ($_freq == 'monthly' && $truncate) {
            if ($interval > 1) {
                $offset = strtotime('+' . ($interval - 1) . ' months ', $offset);
            }
            $t = '+' . (date('t', $offset) - date('d', $offset) + 1) . ' days';
        }

        $sp = strtotime($t, $offset);

        if ($truncate) {
            $sp = $this->truncate_to_period($sp, $this->freq);
        }

        return $sp;
    }

    public function find_end_of_period($offset) {
        return $this->find_starting_point($offset, 1);
    }

    private function truncate_to_period($time, $freq) {
        $date = getdate($time);
        switch ($freq) {
            case "yearly":
                $date['mon'] = 1;
            case "monthly":
                $date['mday'] = 1;
            case "daily":
                $date['hours'] = 0;
            case 'hourly':
                $date['minutes'] = 0;
            case "minutely":
                $date['seconds'] = 0;
                break;
            case "weekly":
                if (date('N', $time) == 1) {
                    $date['hours'] = 0;
                    $date['minutes'] = 0;
                    $date['seconds'] = 0;
                } else {
                    $date = getdate(strtotime("last monday 0:00", $time));
                }
                break;
        }
        $d = mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']);
        return $d;
    }

    private function rule_by_day($rule, $t) {
        $dir = ($rule{0} == '-') ? -1 : 1;
        $dir_t = ($dir == 1) ? 'next' : 'last';


        $d = $this->weekdays[substr($rule, -2)];
        $s = $dir_t . ' ' . $d . ' ' . date('H:i:s', $t);

        if ($rule == substr($rule, -2)) {
            if (date('l', $t) == ucfirst($d)) {
                $s = 'today ' . date('H:i:s', $t);
            }

            $_t = strtotime($s, $t);

            if ($_t == $t && in_array($this->freq, array('monthly', 'yearly'))) {
                $s = 'next ' . $d . ' ' . date('H:i:s', $t);
                $_t = strtotime($s, $_t);
            }

            return $_t;
        } else {
            $_f = $this->freq;
            if (isset($this->rules['bymonth']) && $this->freq == 'yearly') {
                $this->freq = 'monthly';
            }
            if ($dir == -1) {
                $_t = $this->find_end_of_period($t);
            } else {
                $_t = $this->truncate_to_period($t, $this->freq);
            }
            $this->freq = $_f;

            $c = preg_replace('/[^0-9]/', '', $rule);
            $c = ($c == '') ? 1 : $c;

            $n = $_t;
            while ($c > 0) {
                if ($dir == 1 && $c == 1 && date('l', $t) == ucfirst($d)) {
                    $s = 'today ' . date('H:i:s', $t);
                }
                $n = strtotime($s, $n);
                $c--;
            }

            return $n;
        }
    }

    private function rule_by_month($rule, $t) {
        $_t = mktime(date('H', $t), date('i', $t), date('s', $t), $rule, date('d', $t), date('Y', $t));
        if ($t == $_t && isset($this->rules['byday'])) {
            return false;
        } else {
            return $_t;
        }
    }

    private function rule_by_month_day($rule, $t) {
        if ($rule < 0) {
            $rule = date('t', $t) + $rule + 1;
        }
        return mktime(date('H', $t), date('i', $t), date('s', $t), date('m', $t), $rule, date('Y', $t));
    }

    private function rule_by_year_day($rule, $t) {
        if ($rule < 0) {
            $_t = $this->find_end_of_period();
            $d = '-';
        } else {
            $_t = $this->truncate_to_period($t, $this->freq);
            $d = '+';
        }
        $s = $d . abs($rule - 1) . ' days ' . date('H:i:s', $t);
        return strtotime($s, $_t);
    }

    private function rule_by_week_no($rule, $t) {
        if ($rule < 0) {
            $_t = $this->find_end_of_period();
            $d = '-';
        } else {
            $_t = $this->truncate_to_period($t, $this->freq);
            $d = '+';
        }

        $sub = (date('W', $_t) == 1) ? 2 : 1;
        $s = $d . abs($rule - $sub) . ' weeks ' . date('H:i:s', $t);
        $_t = strtotime($s, $_t);

        return $_t;
    }

    private function rule_by_hour($rule, $t) {
        $_t = mktime($rule, date('i', $t), date('s', $t), date('m', $t), date('d', $t), date('Y', $t));
        return $_t;
    }

    private function rule_by_minute($rule, $t) {
        $_t = mktime(date('h', $t), $rule, date('s', $t), date('m', $t), date('d', $t), date('Y', $t));
        return $_t;
    }

    private function valid_date($t) {
        if (isset($this->rules['until']) && $t > $this->rules['until']) {
            return false;
        }

        if (is_array($this->excluded) && in_array($t, $this->excluded)) {
            return false;
        }

        if (isset($this->rules['bymonth'])) {
            $months = explode(',', $this->rules['bymonth']);
            if (!in_array(date('m', $t), $months)) {
                return false;
            }
        }
        if (isset($this->rules['byday'])) {
            $days = explode(',', $this->rules['byday']);
            foreach ($days As $i => $k) {
                $days[$i] = $this->weekdays[preg_replace('/[^A-Z]/', '', $k)];
            }
            if (!in_array(strtolower(date('l', $t)), $days)) {
                return false;
            }
        }
        if (isset($this->rules['byweekno'])) {
            $weeks = explode(',', $this->rules['byweekno']);
            if (!in_array(date('W', $t), $weeks)) {
                return false;
            }
        }
        if (isset($this->rules['bymonthday'])) {
            $weekdays = explode(',', $this->rules['bymonthday']);
            foreach ($weekdays As $i => $k) {
                if ($k < 0) {
                    $weekdays[$i] = date('t', $t) + $k + 1;
                }
            }
            if (!in_array(date('d', $t), $weekdays)) {
                return false;
            }
        }
        if (isset($this->rules['byhour'])) {
            $hours = explode(',', $this->rules['byhour']);
            if (!in_array(date('H', $t), $hours)) {
                return false;
            }
        }

        return true;
    }

    private function is_prerule($rule, $freq) {
        if ($rule == 'year')
            return false;
        if ($rule == 'month' && $freq == 'yearly')
            return true;
        if ($rule == 'monthday' && in_array($freq, array('yearly', 'monthly')) && !isset($this->rules['byday']))
            return true;
        if ($rule == 'yearday' && $freq == 'yearly')
            return true;
        if ($rule == 'weekno' && $freq == 'yearly')
            return true;
        if ($rule == 'day' && in_array($freq, array('yearly', 'monthly', 'weekly')))
            return true;
        if ($rule == 'hour' && in_array($freq, array('yearly', 'monthly', 'weekly', 'daily')))
            return true;
        if ($rule == 'minute')
            return true;

        return false;
    }

}
