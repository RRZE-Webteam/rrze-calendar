<?php

namespace RRZE\Calendar\ICS;

defined('ABSPATH') || exit;

use RRZE\Calendar\Utils;
use RRZE\Calendar\CPT\CalendarEvent;
use RRZE\Calendar\Vendor\Dependencies\ICal\ICal;
use RRZE\Calendar\Vendor\Dependencies\RRule\RRule;
use function RRZE\Calendar\plugin;

/**
 * Export ICS feeds for calendar events.
 *
 * Responsibilities:
 * - Listen to GET requests (?ical-plugin={slug}&action=export&ids=...&cats=...&tags=...)
 * - Query events by IDs and/or taxonomy filters
 * - Build RFC5545-compliant ICS content (UTC for dtstart/dtend, EXDATE/RDATE)
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
        $filename = isset($_GET['filename']) ? sanitize_file_name((string) $_GET['filename']) : '';

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
     * dtstart/dtend MUST be in UTC (Y-m-d H:i:s), EXDATE/RDATE as UTC 'Ymd\THis\Z'.
     *
     * @param \WP_Post[] $posts
     * @return array<int,array<string,string>>
     */
    private function getEvents(array $posts): array
    {
        $data = [];

        foreach ($posts as $post) {
            // Load all meta at once (array of arrays)
            $meta = get_post_meta($post->ID, '', true);

            // Timestamps (assumed local epoch seconds); normalize to int
            $startTs = isset($meta['start'][0]) ? (int) $meta['start'][0] : 0;
            $endTs   = isset($meta['end'][0])   ? (int) $meta['end'][0]   : 0;

            // Convert to UTC ISO-like strings the ICS builder consumes
            $dtstart = $startTs > 0 ? get_gmt_from_date(date('Y-m-d H:i:s', $startTs), 'Y-m-d H:i:s') : '';
            $dtend   = $endTs > 0   ? get_gmt_from_date(date('Y-m-d H:i:s', $endTs),   'Y-m-d H:i:s') : '';

            $args = [
                'summary'     => (string) $post->post_title,
                'uid'         => isset($meta['event-uid'][0]) ? (string) $meta['event-uid'][0] : '',
                'description' => isset($meta['description'][0]) ? (string) $meta['description'][0] : '',
                'dtstart'     => $dtstart,
                'dtend'       => $dtend,
                'location'    => isset($meta['location'][0]) ? (string) $meta['location'][0] : '',
            ];

            // Decide RRULE source: internal builder or imported ICS meta
            if (empty(Utils::getMeta($meta, 'ics_feed_id'))) {
                $this->buildRrule($meta, $args);
            } else {
                $this->buildRruleFromIcsMeta($meta, $args);
            }

            $data[(int) $post->ID] = $args;
        }

        return $data;
    }

    /**
     * Build RRULE/EXDATE/RDATE from native event meta.
     *
     * @param array $meta Raw meta array from get_post_meta($id, '', true)
     * @param array $args Reference to ICS event args
     */
    private function buildRrule(array $meta, array &$args): void
    {
        // RRULE
        $rruleArgsJson = Utils::getMeta($meta, 'event-rrule-args');
        $rrule = '';
        if (!empty($rruleArgsJson)) {
            $decoded = json_decode((string) $rruleArgsJson, true);
            if (is_array($decoded)) {
                $rr = new RRule($decoded);
                // Convert to RFC string and strip leading "RRULE:" if present
                $rfc = $rr->rfcString();
                $pos = strpos($rfc, 'RRULE:');
                $rrule = ($pos !== false) ? substr($rfc, $pos + strlen('RRULE:')) : $rfc;
            }
        }
        if (!empty($rrule)) {
            $args['rrule'] = $rrule;
        }

        // EXDATE
        $exDateUtc = [];
        $exceptionsCsv = Utils::getMeta($meta, 'exceptions');
        if (!empty($exceptionsCsv)) {
            foreach (explode(',', (string) $exceptionsCsv) as $datetimeStr) {
                $datetimeStr = trim($datetimeStr);
                if (Utils::validateDate($datetimeStr)) {
                    $dt = new \DateTime($datetimeStr);
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $exDateUtc[] = $dt->format('Ymd\THis\Z');
                }
            }
        }
        if (!empty($exDateUtc)) {
            $args['exdate'] = implode(',', $exDateUtc);
        }

        // RDATE
        $rDateUtc = [];
        $additionsCsv = Utils::getMeta($meta, 'additions');
        if (!empty($additionsCsv)) {
            foreach (explode(',', (string) $additionsCsv) as $datetimeStr) {
                $datetimeStr = trim($datetimeStr);
                if (Utils::validateDate($datetimeStr)) {
                    $dt = new \DateTime($datetimeStr);
                    $dt->setTimezone(new \DateTimeZone('UTC'));
                    $rDateUtc[] = $dt->format('Ymd\THis\Z');
                }
            }
        }
        if (!empty($rDateUtc)) {
            $args['rdate'] = implode(',', $rDateUtc);
        }
    }

    /**
     * Build RRULE/EXDATE/RDATE from stored ICS (imported) meta block.
     * Expects 'ics_event_meta' array with 'rrule', 'exdate_array', 'rdate_array'.
     *
     * @param array $meta Raw meta array from get_post_meta($id, '', true)
     * @param array $args Reference to ICS event args
     */
    private function buildRruleFromIcsMeta(array $meta, array &$args): void
    {
        $icsMeta = Utils::getMeta($meta, 'ics_event_meta');

        // RRULE
        if (!empty($icsMeta['rrule']) && is_string($icsMeta['rrule'])) {
            $args['rrule'] = (string) $icsMeta['rrule'];
        }

        // EXDATE
        $exdates = [];
        $exdateArray = isset($icsMeta['exdate_array']) ? $icsMeta['exdate_array'] : [];
        if (!empty($exdateArray) && is_array($exdateArray)) {
            $tzid = isset($exdateArray[0]['TZID']) ? (string) $exdateArray[0]['TZID'] : '';
            $tzObj = (new ICal())->timeZoneStringToDateTimeZone($tzid);

            if (!empty($exdateArray[1]) && is_array($exdateArray[1])) {
                foreach ($exdateArray[1] as $dateStr) {
                    $dt = \DateTime::createFromFormat('Ymd\THis', (string) $dateStr, $tzObj);
                    if ($dt instanceof \DateTime) {
                        $dt->setTimezone(new \DateTimeZone('UTC'));
                        $exdates[] = $dt->format('Ymd\THis\Z');
                    }
                }
            }
            if (!empty($exdates)) {
                $args['exdate'] = implode(',', $exdates);
            }
        }

        // RDATE
        $rdates = [];
        $rdateArray = isset($icsMeta['rdate_array']) ? $icsMeta['rdate_array'] : [];
        if (!empty($rdateArray) && is_array($rdateArray)) {
            $tzid = isset($rdateArray[0]['TZID']) ? (string) $rdateArray[0]['TZID'] : '';
            $tzObj = (new ICal())->timeZoneStringToDateTimeZone($tzid);

            if (!empty($rdateArray[1]) && is_array($rdateArray[1])) {
                foreach ($rdateArray[1] as $dateStr) {
                    $dt = \DateTime::createFromFormat('Ymd\THis', (string) $dateStr, $tzObj);
                    if ($dt instanceof \DateTime) {
                        $dt->setTimezone(new \DateTimeZone('UTC'));
                        $rdates[] = $dt->format('Ymd\THis\Z');
                    }
                }
            }
            if (!empty($rdates)) {
                $args['rdate'] = implode(',', $rdates);
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
        $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename) ?: 'calendar';
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
            'filename'    => sanitize_file_name($filename),
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
