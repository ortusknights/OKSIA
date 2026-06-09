<?php
namespace OKSIA\Core;

class Deactivator {

    public static function deactivate() {
        self::clear_temp_files();
        flush_rewrite_rules();
    }

    private static function clear_temp_files() {
        $temp_dir = OKSIA_UPLOAD_DIR;
        if (defined('OKSIA_UPLOAD_DIR') && is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.htaccess' && basename($file) !== 'index.php') {
                    @unlink($file);
                }
            }
        }
    }
}
