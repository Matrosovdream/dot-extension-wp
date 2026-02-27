<?php
if ( ! defined('ABSPATH') ) { exit; }

final class DotFrmImageEnhancerSingleEvent
{
    /**
     * WP-Cron hook name
     */
    private const EVENT_HOOK = 'dot_frm_enhance_entry_image_single';

    /**
     * Simple lock prefix (to prevent double run)
     */
    private const LOCK_PREFIX = 'dot_frm_enhance_lock_';

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
    public static function schedule(int $entry_id, int $delay_seconds = 30): bool
    {
        $entry_id = (int) $entry_id;
        if ($entry_id <= 0) {
            return false;
        }

        // Prevent duplicate scheduling
        if (wp_next_scheduled(self::EVENT_HOOK, [$entry_id])) {
            return true;
        }

        return (bool) wp_schedule_single_event(
            time() + max(0, $delay_seconds),
            self::EVENT_HOOK,
            [$entry_id]
        );
    }

    /**
     * Cron worker
     */
    public static function handle(int $entry_id): void
    {
        $entry_id = (int) $entry_id;
        if ($entry_id <= 0) {
            return;
        }

        // Prevent parallel execution
        $lock_key = self::LOCK_PREFIX . $entry_id;

        if (get_transient($lock_key)) {
            return; // already running
        }

        set_transient($lock_key, 1, 5 * MINUTE_IN_SECONDS);

        try {
            $photoHelper = new DotFrmPhotoEntryHelper();

            // Set processing status to true
            $photoHelper->setEntryImageProcessing($entry_id, true);

            // Set single event for setting off this field value, in case of infinite loop
            $photoHelper->scheduleEntryImageProcessSet($entry_id, false);

            // Run image enhancement and update entry
            $photoHelper->updateEnhanceEntryImage($entry_id);

            // Set processing status to false, finished
            $photoHelper->setEntryImageProcessing($entry_id, false);


        } catch (\Throwable $e) {
            error_log('[DotFrmImageEnhancerSingleEvent] Error: ' . $e->getMessage());
        }

        delete_transient($lock_key);
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

DotFrmImageEnhancerSingleEvent::init();