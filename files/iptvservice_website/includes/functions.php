<?php
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return false;
    $files = array_diff(scandir($dirPath), ['.', '..']);
    foreach ($files as $file) {
        $p = $dirPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($p)) { deleteDir($p); } else { @unlink($p); }
    }
    return @rmdir($dirPath);
}
function boolish($v, $default=false) {
    if (is_bool($v)) return $v;
    $b = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $b === null ? $default : $b;
}
