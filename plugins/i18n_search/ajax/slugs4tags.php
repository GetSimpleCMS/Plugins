<?php
$tags = preg_split('/\s+/',trim(strtolower($_GET["tags"])));
if (!$tags) die;
header('Content-Type: application/json');
$datadir = substr(dirname(__FILE__), 0, strrpos(dirname(__FILE__), DIRECTORY_SEPARATOR.'plugins')) . '/data/';
$tagfile = $datadir . 'other/i18n_tag_index.txt';
$slugs = null;
if (file_exists($tagfile)) {
  $f = fopen($tagfile, "r");
  while (($line = fgets($f)) !== false) {
    $items = preg_split('/\s+/',trim($line));
    $tag = array_shift($items);
    if (in_array($tag,$tags)) {
      if ($slugs == null) {
        $slugs = $items;
      } else {
        $slugs = array_values(array_intersect($slugs, $items));
      }
    }
  }
  fclose($f);
}
if ($slugs == null) $slugs = array();
echo json_encode($slugs);
