<?php
if (!defined('ABSPATH')) {
    exit;
}

class ACE_Planner {

    const OPTION_ITEMS = 'ace_social_planner_items';

    public static function get_items() {
        $items = get_option(self::OPTION_ITEMS, []);

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_map([__CLASS__, 'sanitize_item'], $items));
    }

    public static function save_item($item) {
        $items = self::get_items();
        $item = self::sanitize_item($item);

        if (empty($item['id'])) {
            $item['id'] = wp_generate_uuid4();
            $items[] = $item;
        } else {
            $updated = false;

            foreach ($items as $index => $existing_item) {
                if ((string) $existing_item['id'] === (string) $item['id']) {
                    $items[$index] = $item;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $items[] = $item;
            }
        }

        update_option(self::OPTION_ITEMS, array_values($items), false);

        return self::get_items();
    }

    public static function delete_item($id) {
        $items = array_values(array_filter(self::get_items(), static function ($item) use ($id) {
            return (string) $item['id'] !== (string) $id;
        }));

        update_option(self::OPTION_ITEMS, $items, false);

        return self::get_items();
    }

    public static function sanitize_item($item) {
        $item = is_array($item) ? $item : [];

        return [
            'id' => isset($item['id']) ? sanitize_text_field((string) $item['id']) : '',
            'title' => sanitize_text_field((string) ($item['title'] ?? 'Untitled social post')),
            'platform' => sanitize_text_field((string) ($item['platform'] ?? 'X')),
            'status' => sanitize_key((string) ($item['status'] ?? 'drafted')),
            'start' => self::sanitize_date((string) ($item['start'] ?? '')),
            'end' => self::sanitize_date((string) ($item['end'] ?? '')),
            'allDay' => !empty($item['allDay']),
            'notes' => sanitize_textarea_field((string) ($item['notes'] ?? '')),
        ];
    }

    private static function sanitize_date($date) {
        $date = trim($date);

        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return '';
        }

        if (strlen($date) === 10) {
            return gmdate('Y-m-d', $timestamp);
        }

        return gmdate('Y-m-d\TH:i:s', $timestamp);
    }
}
