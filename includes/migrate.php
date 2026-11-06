<?php
/**
 * Automatic database updates. When settings.db_version is older than DB_VERSION, every
 * sql/update-x.y.z.sql newer than it is applied once, statement by statement. Statements that
 * were already applied ("table exists", "duplicate column / key") are skipped, so running
 * twice is harmless. New installs get the full sql/schema.sql and start at DB_VERSION.
 */

function db_migrate()
{
    $current = (string) setting('db_version', '1.0.0');
    if (version_compare($current, DB_VERSION, '>=')) {
        return;
    }
    $files = glob(APP_ROOT . '/sql/update-*.sql') ?: [];
    $todo = [];
    foreach ($files as $f) {
        if (preg_match('/update-(\d+\.\d+\.\d+)\.sql$/', $f, $m) && version_compare($m[1], $current, '>') && version_compare($m[1], DB_VERSION, '<=')) {
            $todo[$m[1]] = $f;
        }
    }
    uksort($todo, 'version_compare');
    $skip = [1050, 1060, 1061, 1091]; // table exists, duplicate column, duplicate key, can't drop missing
    foreach ($todo as $ver => $file) {
        $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
            if (trim($stmt) === '') {
                continue;
            }
            try {
                db()->exec($stmt);
            } catch (PDOException $ex) {
                $code = isset($ex->errorInfo[1]) ? (int) $ex->errorInfo[1] : 0;
                if (!in_array($code, $skip, true)) {
                    error_log('kmproperties migration ' . $ver . ' failed: ' . $ex->getMessage());
                    return; // try again on the next request; nothing is marked as done
                }
            }
        }
        save_setting('db_version', $ver);
    }
    save_setting('db_version', DB_VERSION);
}
