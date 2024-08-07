<?php
if (empty($attributes['class'])) $attributes['class'] = "container";
$attributes = implode(" ", array_map(fn ($k) => "$k=\"$attributes[$k]\"", array_keys($attributes)));
?>

<div <?= $attributes ?>><?= renderComponents($components) ?></div>