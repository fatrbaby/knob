<?php

use Knob\Knob;

const OPT = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

require __DIR__ . '/vendor/autoload.php';

Knob::using(new PDO('mysql:host=localhost;dbname=ctyun', 'root', '12345678'));

$value = Knob::table('biz_order')
    ->where('status', 'finished')
    ->get();

echo 'count: ', $value->count(), PHP_EOL;

echo $value->toJson(OPT);

