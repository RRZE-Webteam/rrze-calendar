<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Calendar\CPT\CalendarFeed;
use ICal\ICal;
use RRule\RRule;
use function RRZE\Calendar\plugin;

/**
 * Export ICS feeds for calendar events.
 *
 * Responsibilities:
 * - Listen to GET requests (?ical-plugin={slug}&action=export&ids=...&cats=...&tags=...)
 * - Query events by IDs and/or taxonomy filters
 * - Build ICS content retaining the timezone of recurring timed events
 * - Stream the .ics file with safe headers
 */
class Export
{
    /**
     * ICS vCalendar payload.
     *
     * @var string
     */
    private $vcalendar = '';

    /**
     * Hook request handler on init.
     */
    public function __construct()
    {
        add_action('init', [$this, 'request']);
    }

    /**
     * Handle export requests from query vars.
     * Example: /?ical-plugin=rrze-calendar&action=export&ids=1,2,3
     *
     * Security: export feeds are typically public; we sanitize all inputs.
     */
    public function request(): void
    {
        $plugin  = isset($_GET['ical-plugin']) ? sanitize_title((string) $_GET['ical-plugin']) : '';
        $action  = isset($_GET['action']) ? sanitize_text_field((string) $_GET['action']) : '';
        $filename = isset($_GET['filename']) ? sanitize_title((string) $_GET['filename']) : '';

        // Default filename falls back to site host slug if not matching.
        $urlHost = sanitize_title((string) parse_url(site_url(), PHP_URL_HOST));
        if (strpos((string) $filename, $urlHost) === false) {
            $filename = $urlHost;
        }

        if ($plugin === sanitize_title(plugin()->getSlug()) && $action === 'export') {
            // Sanitize and normalize filters
            $postIdsCsv = isset($_GET['ids']) ? sanitize_text_field((string) $_GET['ids']) : '';
            $catsCsv    = isset($_GET['cats']) ? sanitize_text_field((string) $_GET['cats']) : '';
            $tagsCsv    = isset($_GET['tags']) ? sanitize_text_field((string) $_GET['tags']) : '';

            $args = [
                'postIds'    => array_filter(array_map('absint', array_map('trim', explode(',', $postIdsCsv)))),
                'categories' => array_filter(array_map('absint', array_map('trim', explode(',', $catsCsv)))),
                'tags'       => array_filter(array_map('absint', array_map('trim', explode(',', $tagsCsv)))),
            ];

            $this->set($args);
            $this->stream($filename);
        }
    }

    /**
     * Prepare and build the ICS from query arguments.
     *
     * @param array{postIds?:int[],categories?:int[],tags?:int[]} $args
     */
    private function set(array $args): void
    {
        $postIds    = isset($args['postIds']) ? (array) $args['postIds'] : [];
        $categories = isset($args['categories']) ? (array) $args['categories'] : [];
        $tags       = isset($args['tags']) ? (array) $args['tags'] : [];

        // Build WP_Query args
        $q = [
            'post_type'              => CalendarEvent::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if (!empty($postIds)) {
            $q['post__in'] = array_values(array_unique(array_map('absint', $postIds)));
        }

        // tax_query with 'term_id' field
        $taxQuery = [];
        if (!empty($categories)) {
            $taxQuery[] = [
                'taxonomy' => CalendarEvent::TAX_CATEGORY,
                'field'    => 'term_id',
                'terms'    => array_map('absint', $categories),
            ];
        }
        if (!empty($tags)) {
            $taxQuery[] = [
                'taxonomy' => CalendarEvent::TAX_TAG,
                'field'    => 'term_id',
                'terms'    => array_map('absint', $tags),
            ];
        }
        if (!empty($taxQuery)) {
            $q['tax_query'] = array_merge(['relation' => 'AND'], $taxQuery);
        }

        // If no filters at all were provided, we avoid dumping entire DB by mistake.
        if (empty($postIds) && empty($taxQuery)) {
            $this->vcalendar = ''; // nothing to build
            return;
        }

        $posts = get_posts($q);
        if (empty($posts)) {
            $this->vcalendar = '';
            return;
        }

        $data = $this->getEvents($posts);
        if (!empty($data)) {
            $this->build($data);
        }
    }

    /**
     * Extract event data from posts in a shape the ICS builder expects.
     * Native all-day events use site-local calendar dates and an exclusive end.
     * Timed events and imported events start from their stored UTC instants.
     *
     * @param \WP_Post[] $posts
     * @return array<int,array<string,string>>
     */
    private function getEvents(array $posts): array
    {
        $data = [];
        $feedTimezones = [];

        foreach ($posts as $post) {
            // Load all meta at once (array of arrays)
            $meta = get_post_meta($post->ID, '', true);

            // Stored Unix timestamps represent absolute instants.
            $startTs = isset($meta['start'][0]) ? (int) $meta['start'][0] : 0;
            $endTs   = isset($meta['end'][0])   ? (int) $meta['end'][0]   : 0;

            $native = empty(Utils::getMeta($meta, 'ics_feed_id'));
            $allDay = Utils::getMeta($meta, 'all-day') === 'on';
            if ($native && $allDay) {
                $local = new \DateTimeImmutable('now', wp_timezone());
                $dates = [
                    'dtstart;value=date' => $startTs > 0 ? $local->setTimestamp($startTs)->format('Ymd') : '',
                    'dtend;value=date'   => $endTs > 0 ? $local->setTimestamp($endTs)->modify('tomorrow')->format('Ymd') : '',
                ];
            } else {
                $dates = [
                    'dtstart' => $startTs > 0 ? Utils::timestampToIcsUtc($startTs) : '',
                    'dtend'   => $endTs > 0 ? Utils::timestampToIcsUtc($endTs) : '',
                ];
            }

            $args = [
                'summary'     => Utils::prepareSummaryFromPostTitle($post->post_title),
                'uid'         => isset($meta['event-uid'][0]) ? (string) $meta['event-uid'][0] : '',
                'description' => isset($meta['description'][0]) ? Utils::prepareDescriptionFromPostContent($meta['description'][0]) : '',
                ...$dates,
                'location'    => isset($meta['location'][0]) ? (string) $meta['location'][0] : '',
            ];

            // Decide RRULE source: internal builder or imported ICS meta
            if ($native) {
                $this->buildRrule($meta, $args, $allDay);
            } else {
                $this->buildRruleFromIcsMeta($meta, $args);
            }

            if (!empty($args['rrule']) && !$allDay) {
                $args['timezone'] = $this->getRecurrenceTimezone($meta, $feedTimezones)->getName();
            }

            $data[(int) $post->ID] = $args;
        }

        return $data;
    }

    /**
     * Resolve a series timezone without changing stored events or import behavior.
     *
     * Imported feed items retain DTSTART's original TZID/UTC designation. Index
     * them once per feed. Older imports without those items use their recorded
     * display timezone, matching the existing calendar's local recurrence times.
     *
     * @param array $meta Raw event metadata.
     * @param array $feedTimezones Per-export cache of feed ID and UID to timezone.
     * @return \DateTimeZone The source timezone, or WordPress timezone for native events.
     */
    private function getRecurrenceTimezone(array $meta, array &$feedTimezones): \DateTimeZone
    {
        $feedId = (int) Utils::getMeta($meta, 'ics_feed_id');
        if (!$feedId) {
            return wp_timezone();
        }

        $icsMeta = Utils::getMeta($meta, 'ics_event_meta');
        $fallback = !empty($icsMeta['timezone']) ? $icsMeta['timezone'] : wp_timezone()->getName();
        $parser = new ICal(false, ['defaultTimeZone' => $fallback]);
        if (!isset($feedTimezones[$feedId])) {
            $feedTimezones[$feedId] = [];
            $items = get_post_meta($feedId, CalendarFeed::FEED_EVENTS_ITEMS, true);
            foreach (is_array($items) ? $items : [] as $item) {
                if (!is_object($item) || empty($item->uid) || empty($item->dtstart)
                    || !empty($item->recurrence_id) || isset($feedTimezones[$feedId][$item->uid])) {
                    continue;
                }
                $zone = str_ends_with($item->dtstart, 'Z')
                    ? 'UTC'
                    : ($item->dtstart_array[0]['TZID'] ?? $fallback);
                $feedTimezones[$feedId][$item->uid] = $parser->timeZoneStringToDateTimeZone($zone);
            }
        }

        $uid = (string) Utils::getMeta($meta, 'event-uid');
        return $feedTimezones[$feedId][$uid] ?? $parser->timeZoneStringToDateTimeZone($fallback);
    }

    /**
     * Build RRULE/EXDATE/RDATE from native event meta.
     *
     * @param array $meta Raw meta array from get_post_meta($id, '', true)
     * @param array $args Reference to ICS event args
     * @param bool $allDay Whether the recurrence uses DATE values.
     */
    private function buildRrule(array $meta, array &$args, bool $allDay): void
    {
        // RRULE
        $rruleArgsJson = Utils::getMeta($meta, 'event-rrule-args');
        $rrule = '';
        if (!empty($rruleArgsJson)) {
            $decoded = json_decode((string) $rruleArgsJson, true);
            if (is_array($decoded) && !empty($decoded)) {
                $decoded = array_change_key_case($decoded, CASE_UPPER);
                $startTs = (int) Utils::getMeta($meta, 'start');
                if ($startTs > 0) {
                    $decoded['DTSTART'] = (new \DateTime('@' . $startTs))->setTimezone(wp_timezone());
                }
                $rr = new RRule($decoded);
                // Convert to RFC string and strip leading "RRULE:" if present
                $rfc = $rr->rfcString(!$allDay);
                $pos = strpos($rfc, 'RRULE:');
                $rrule = ($pos !== false) ? substr($rfc, $pos + strlen('RRULE:')) : $rfc;
                if ($allDay) {
                    // rfcString(false) writes UNTIL in DTSTART's local timezone.
                    $rrule = preg_replace('/\b(UNTIL=\d{8})T\d{6}\b/', '$1', $rrule);
                }
            }
        }
        if (!empty($rrule)) {
            $args['rrule'] = $rrule;
        }

        foreach (['exceptions' => 'exdate', 'additions' => 'rdate'] as $key => $property) {
            $values = $this->getNativeRecurrenceDates($meta, $key, $allDay);
            if ($values) {
                $args[$allDay ? $property . ';value=date' : $property] = implode(',', $values);
            }
        }
    }

    /**
     * Format native exceptions and additions using the event's date type.
     *
     * The editor stores one date per line; accept older comma-separated values
     * and explicit date-times as well. UTC conversion uses each occurrence's
     * date, so an exclusion in winter still matches a summer-starting series.
     *
     * @param array $meta Raw event metadata.
     * @param string $key The exceptions or additions meta key.
     * @param bool $allDay Whether to omit the time from each local date.
     * @return string[] iCalendar dates or UTC date-times.
     */
    private function getNativeRecurrenceDates(array $meta, string $key, bool $allDay): array
    {
        $timezone = wp_timezone();
        $start = (new \DateTimeImmutable('@' . (int) Utils::getMeta($meta, 'start')))->setTimezone($timezone);
        $values = [];
        foreach (preg_split('/[\r\n,]+/', (string) Utils::getMeta($meta, $key)) as $value) {
            $value = trim($value);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $value .= ' ' . $start->format('H:i:s');
            }
            $format = strlen($value) === 16 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
            $date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
                continue;
            }
            $values[] = $allDay ? $date->format('Ymd') : $date->setTimezone(new \DateTimeZone('UTC'))->format(ICS::DT_FORMAT);
        }
        return array_values(array_unique($values));
    }

    /**
     * Build the recurrence rule and dates from an imported event's metadata.
     *
     * @param array $meta Raw event metadata.
     * @param array $args Reference to ICS event arguments.
     * @return void
     */
    private function buildRruleFromIcsMeta(array $meta, array &$args): void
    {
        $icsMeta = Utils::getMeta($meta, 'ics_event_meta');
        if (!is_array($icsMeta)) {
            return;
        }
        if (!empty($icsMeta['rrule']) && is_string($icsMeta['rrule'])) {
            $args['rrule'] = $icsMeta['rrule'];
        }

        $fallback = $icsMeta['timezone'] ?? wp_timezone()->getName();
        $parser = new ICal(false, ['defaultTimeZone' => $fallback]);
        foreach (['exdate', 'rdate'] as $property) {
            $timezone = $parser->timeZoneStringToDateTimeZone($fallback);
            $values = [];
            foreach (($icsMeta[$property . '_array'] ?? []) as $part) {
                if (is_array($part) && (!$part || !array_is_list($part))) {
                    $timezone = $parser->timeZoneStringToDateTimeZone($part['TZID'] ?? $fallback);
                    continue;
                }
                foreach ((array) $part as $key => $dates) {
                    if (!is_int($key)) {
                        continue;
                    }
                    foreach (explode(',', (string) $dates) as $date) {
                        // Keep UTC designators and support repeated property lines.
                        if (!preg_match('/^\d{8}(?:T\d{6}Z?)?$/', $date)) {
                            continue;
                        }
                        $utc = str_ends_with($date, 'Z');
                        $format = strlen($date) === 8 ? '!Ymd' : ($utc ? '!Ymd\THis\Z' : '!Ymd\THis');
                        $datetime = \DateTimeImmutable::createFromFormat($format, $date, $utc ? new \DateTimeZone('UTC') : $timezone);
                        $errors = \DateTimeImmutable::getLastErrors();
                        if (!$datetime || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
                            continue;
                        }
                        $values[] = $datetime->setTimezone(new \DateTimeZone('UTC'))->format(ICS::DT_FORMAT);
                    }
                }
            }
            if ($values) {
                $args[$property] = implode(',', array_unique($values));
            }
        }
    }

    /**
     * Build ICS payload.
     *
     * @param array<int,array<string,string>> $data
     */
    private function build(array $data): void
    {
        $ics = new ICS($data);
        $this->vcalendar = (string) $ics->build();
    }

    /**
     * Stream ICS to client with safe headers and UTF-8 filename support.
     */
    private function stream(string $filename): void
    {
        $charset = get_option('blog_charset') ?: 'UTF-8';
        $safe = sanitize_title($filename) ?: 'calendar';
        $file = $safe . '.ics';

        // Headers
        header('Content-Type: text/calendar; charset=' . $charset, true);
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Content-Transfer-Encoding: binary', true);
        header(sprintf(
            "Content-Disposition: attachment; filename=\"%s\"; filename*=UTF-8''%s",
            $file,
            rawurlencode($file)
        ), true);

        echo $this->vcalendar;
        exit;
    }

    /**
     * Public helper to compose an ICS export link for buttons/shortcodes.
     *
     * @param array{ids?:int[],cats?:int[],tags?:int[]} $args
     * @return string Absolute URL
     */
    public static function makeIcsLink(array $args): string
    {
        // Filename based on host + path
        $currentUrl = home_url($_SERVER['REQUEST_URI'] ?? '/');
        $urlHost    = (string) parse_url($currentUrl, PHP_URL_HOST);
        $urlPath    = (string) parse_url($currentUrl, PHP_URL_PATH);
        $filename   = sprintf(
            '%1$s%2$s',
            sanitize_title($urlHost),
            $urlPath ? '-' . sanitize_title($urlPath) : ''
        );

        $qArgs = [
            'ical-plugin' => sanitize_title(plugin()->getSlug()),
            'action'      => 'export',
            'filename'    => $filename,
        ];

        // Append optional filters as CSV
        foreach (['ids', 'cats', 'tags'] as $key) {
            if (!empty($args[$key]) && is_array($args[$key])) {
                $qArgs[$key] = implode(',', array_map('absint', $args[$key]));
            }
        }

        // Force absolute site URL root for consistency
        return add_query_arg($qArgs, site_url('/'));
    }
}
