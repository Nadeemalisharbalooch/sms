<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassSubject;

echo 'Before: ' . ClassSubject::where('class_id', 1)->count() . PHP_EOL;

$controller = new App\Http\Controllers\Institute\ClassSubjectController();
$method = new ReflectionMethod($controller, 'syncForTarget');
$method->setAccessible(true);
$method->invoke($controller, 1, null, []);

echo 'After: ' . ClassSubject::where('class_id', 1)->count() . PHP_EOL;