<?php
function repo_clone($repo_url, $clone_dir, $branch) {
    exec("git clone " . escapeshellarg($repo_url) . " " . escapeshellarg($clone_dir), $o, $rc);
    if ($rc !== 0) die("Error cloning repository.");
    if ($branch) {
        chdir($clone_dir);
        exec("git checkout " . escapeshellarg($branch), $o, $rc);
        if ($rc !== 0) die("Error checking out branch.");
    }
}
