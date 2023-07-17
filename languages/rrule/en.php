<?php

namespace RRZE\Calendar;

defined('ABSPATH') || exit;

/**
 * Translation file for English language.
 *
 * Most strings can be an array, with a value as the key. The system will
 * pick the translation corresponding to the key. The key "else" will be picked
 * if no matching value is found. This is useful for plurals.
 *
 * Licensed under the MIT license.
 *
 * For the full copyright and license information, please view the LICENSE file.
 *
 * @author Rémi Lanvin <remi@cloudconnected.fr>
 * @link https://github.com/rlanvin/php-rrule
 */
return [
    'yearly' => [
        '1' => 'yearly',
        'else' => 'every %{interval} years'
    ],
    'monthly' => [
        '1' => 'monthly',
        'else' => 'every %{interval} months'
    ],
    'weekly' => [
        '1' => 'weekly',
        '2' => 'every other week',
        'else' => 'every %{interval} weeks'
    ],
    'daily' => [
        '1' => 'daily',
        '2' => 'every other day',
        'else' => 'every %{interval} days'
    ],
    'hourly' => [
        '1' => 'hourly',
        'else' => 'every %{interval} hours'
    ],
    'minutely' => [
        '1' => 'minutely',
        'else' => 'every %{interval} minutes'
    ],
    'secondly' => [
        '1' => 'secondly',
        'else' => 'every %{interval} seconds'
    ],
    'dtstart' => ', starting from %{date}',
    'infinite' => '',
    'until' => ', until %{date}',
    'count' => [
        '1' => ', one time',
        'else' => ', %{count} times'
    ],
    'and' => 'and ',
    'x_of_the_y' => [
        'yearly' => '%{x} of the year', // e.g. the first Monday of the year, or the first day of the year
        'monthly' => '%{x} of the month',
    ],
    'bymonth' => ' in %{months}',
    'months' => [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ],
    'byweekday' => ' on %{weekdays}',
    'weekdays' => [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ],
    'nth_weekday' => [
        '1' => 'the first %{weekday}', // e.g. the first Monday
        '2' => 'the second %{weekday}',
        '3' => 'the third %{weekday}',
        'else' => 'the %{n}th %{weekday}'
    ],
    '-nth_weekday' => [
        '-1' => 'the last %{weekday}', // e.g. the last Monday
        '-2' => 'the penultimate %{weekday}',
        '-3' => 'the antepenultimate %{weekday}',
        'else' => 'the %{n}th to the last %{weekday}'
    ],
    'byweekno' => [
        '1' => ' on week %{weeks}',
        'else' => ' on weeks number %{weeks}'
    ],
    'nth_weekno' => '%{n}',
    'bymonthday' => ' on %{monthdays}',
    'nth_monthday' => [
        '1' => 'the 1st',
        '2' => 'the 2nd',
        '3' => 'the 3rd',
        '21' => 'the 21st',
        '22' => 'the 22nd',
        '23' => 'the 23rd',
        '31' => 'the 31st',
        'else' => 'the %{n}th'
    ],
    '-nth_monthday' => [
        '-1' => 'the last day',
        '-2' => 'the penultimate day',
        '-3' => 'the antepenultimate day',
        '-21' => 'the 21st to the last day',
        '-22' => 'the 22nd to the last day',
        '-23' => 'the 23rd to the last day',
        '-31' => 'the 31st to the last day',
        'else' => 'the %{n}th to the last day'
    ],
    'byyearday' => [
        '1' => ' on %{yeardays} day',
        'else' => ' on %{yeardays} days'
    ],
    'nth_yearday' => [
        '1' => 'the first',
        '2' => 'the second',
        '3' => 'the third',
        'else' => 'the %{n}th'
    ],
    '-nth_yearday' => [
        '-1' => 'the last',
        '-2' => 'the penultimate',
        '-3' => 'the antepenultimate',
        'else' => 'the %{n}th to the last'
    ],
    'byhour' => [
        '1' => ' at %{hours}',
        'else' => ' at %{hours}'
    ],
    'nth_hour' => '%{n}h',
    'byminute' => [
        '1' => ' at minute %{minutes}',
        'else' => ' at minutes %{minutes}'
    ],
    'nth_minute' => '%{n}',
    'bysecond' => [
        '1' => ' at second %{seconds}',
        'else' => ' at seconds %{seconds}'
    ],
    'nth_second' => '%{n}',
    'bysetpos' => ', but only %{setpos} instance of this set',
    'nth_setpos' => [
        '1' => 'the first',
        '2' => 'the second',
        '3' => 'the third',
        'else' => 'the %{n}th'
    ],
    '-nth_setpos' => [
        '-1' => 'the last',
        '-2' => 'the penultimate',
        '-3' => 'the antepenultimate',
        'else' => 'the %{n}th to the last'
    ]
];
