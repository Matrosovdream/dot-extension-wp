<?php
if ( ! defined('ABSPATH') ) { exit; }

final class DotFrmSetImageProcessingSingleEvent
{
    /**
     * WP-Cron hook name
     */
    private const EVENT_HOOK = 'dot_frm_set_image_processing_single';


    /**
     * Bootstrap
     */
    public static function init(): void
    {
        add_action(self::EVENT_HOOK, [self::class, 'handle'], 10, 1);
    }

    /**
     * Public method: schedule single enhance event
     */
    public static function schedule(int $entry_id, bool $isProcessing, int $delay_seconds = 90): bool
    {
        $entry_id = (int) $entry_id;
        if ($entry_id <= 0) {
            return false;
        }

        // Prevent duplicate scheduling
        if (wp_next_scheduled(self::EVENT_HOOK, [$entry_id, $isProcessing])) {
            return true;
        }

        return (bool) wp_schedule_single_event(
            time() + max(0, $delay_seconds),
            self::EVENT_HOOK,
            [$entry_id, $isProcessing]
        );
    }

    /**
     * Cron worker
     */
    public static function handle(int $entry_id, bool $isProcessing): void
    {
        $entry_id = (int) $entry_id;
        if ($entry_id <= 0) {
            return;
        }

        $photoHelper = new DotFrmPhotoEntryHelper();

        // Set processing status to true
        $photoHelper->setEntryImageProcessing($entry_id, $isProcessing);

    }

    /**
     * Optional: unschedule existing event
     */
    public static function unschedule(int $entry_id): void
    {
        $timestamp = wp_next_scheduled(self::EVENT_HOOK, [$entry_id]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::EVENT_HOOK, [$entry_id]);
        }
    }
}

DotFrmSetImageProcessingSingleEvent::init();