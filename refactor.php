<?php
$dir = new RecursiveDirectoryIterator('app/Http/Controllers');
$ite = new RecursiveIteratorIterator($dir);
foreach ($ite as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;
        
        $content = preg_replace('/->where\(\'restaurant_id\',[^)]+\)/', '', $content);
        $content = preg_replace('/::where\(\'restaurant_id\',[^)]+\)->/', '::', $content);
        
        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}
echo "Done.\n";
