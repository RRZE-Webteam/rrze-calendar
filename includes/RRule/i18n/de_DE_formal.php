<?php

/**
 * Translation file for German language.
 */
return array(
	'yearly' => array(
		'1' => 'jährlich',
		'else' => 'alle %{interval} Jahre'
	),
	'monthly' => array(
		'1' => 'monatlich',
		'else' => 'alle %{interval} Monaten'
	),
	'weekly' => array(
		'1' => 'wöchentlich',
		'2' => 'jede andere Woche',
		'else' => 'alle %{interval} Wochen'
	),
	'daily' => array(
		'1' => 'täglich',
		'2' => 'jeder andere Tag',
		'else' => 'alle %{interval} Tage'
	),
	'hourly' => array(
		'1' => 'stündlich',
		'else' => 'alle %{interval} Stunden'
	),
	'minutely' => array(
		'1' => 'minütlich',
		'else' => 'alle %{interval} Minuten'
	),
	'secondly' => array(
		'1' => 'sekündlich',
		'else' => 'alle %{interval} Sekunden'
	),
	'dtstart' => ', ab %{date}',
	'infinite' => ', ständig',
	'until' => ', bis %{date}',
	'count' => array(
		'1' => ', einmal',
		'else' => ', %{count} mal'
	),
	'and' => 'and',
	'x_of_the_y' => array(
		'yearly' => '%{x} des Jahres', // e.g. the first Monday of the year, or the first day of the year
		'monthly' => '%{x} des Monats',
	),
	'bymonth' => ' in %{months}',
	'months' => array(
		1 => 'Januar',
		2 => 'Februar',
		3 => 'März',
		4 => 'April',
		5 => 'May',
		6 => 'Juni',
		7 => 'July',
		8 => 'August',
		9 => 'September',
		10 => 'Oktober',
		11 => 'November',
		12 => 'Dezember',
	),
	'byweekday' => ' am %{weekdays}',
	'weekdays' => array(
		1 => 'Montag',
		2 => 'Dienstag',
		3 => 'Mittwoch',
		4 => 'Donnerstag',
		5 => 'Freitag',
		6 => 'Samstag',
		7 => 'Sonntag',
	),
	'nth_weekday' => array(
		'1' => 'der erste %{weekday}', // e.g. the first Monday
		'2' => 'der zweite %{weekday}',
		'3' => 'der dritte %{weekday}',
		'else' => 'der %{n}. %{weekday}'
	),
	'-nth_weekday' => array(
		'-1' => 'den letzten %{weekday}', // e.g. the last Monday
		'-2' => 'den vorletzten %{weekday}',
		'-3' => 'den drittletzten %{weekday}',
		'else' => 'den %{n}. bis zum letzten %{weekday}'
	),
	'byweekno' => array(
		'1' => ' am KW %{weeks}',
		'else' => ' am KW %{weeks}'
	),
	'nth_weekno' => '%{n}',
	'bymonthday' => ' am %{monthdays}',
	'nth_monthday' => array(
		'1' => 'der erste',
		'2' => 'der zweite',
		'3' => 'der dritte',
		'21' => 'der 21.',
		'22' => 'der 22.',
		'23' => 'der 23.',
		'31' => 'der 31.',
		'else' => 'der %{n}.'
	),
	'-nth_monthday' => array(
		'-1' => 'der letzte Tag',
		'-2' => 'der vorletzte Tag',
		'-3' => 'der drittletzte Tag',
		'-21' => 'der 21. bis zum letzten Tag',
		'-22' => 'der 22. bis zum letzten Tag',
		'-23' => 'der 23. bis zum letzten Tag',
		'-31' => 'der 31. bis zum letzten Tag',
		'else' => 'der %{n}. bis zum letzten Tag'
	),
	'byyearday' => array(
		'1' => ' am %{yeardays} Tag',
		'else' => ' am %{yeardays} Tage'
	),
	'nth_yearday' => array(
		'1' => 'der erste',
		'2' => 'der zweite',
		'3' => 'der dritte',
		'else' => 'der %{n}.'
	),
	'-nth_yearday' => array(
		'-1' => 'den letzten',
		'-2' => 'den vorletzten',
		'-3' => 'den drittletzten',
		'else' => 'den %{n}. bis zum letzten'
	),
	'byhour' => array(
		'1' => ' bei Stunde %{hours}',
		'else' => ' bei Stunde %{hours}'
	),
	'nth_hour' => '%{n}.',
	'byminute' => array(
		'1' => ' bei Minute %{minutes}',
		'else' => ' bei Minute %{minutes}'
	),
	'nth_minute' => '%{n}.',
	'bysecond' => array(
		'1' => ' bei Sekunde %{seconds}',
		'else' => ' bei Sekunde %{seconds}'
	),
	'nth_second' => '%{n}',
	'bysetpos' => ', aber nur %{setpos} Instanz dieses Satzes',
	'nth_setpos' => array(
		'1' => 'der Erste',
		'2' => 'der Zeite',
		'3' => 'der Dritte',
		'else' => 'der %{n}.'
	),
	'-nth_setpos' => array(
		'-1' => 'das Letzte',
		'-2' => 'das vorletzte',
		'-3' => 'das drittletzte',
		'else' => 'das %{n}. bis zum letzten'
	)
);