<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$token = App\Models\User::first()->createToken("dev-token")->accessToken;
echo "TOKEN:" . $token . "\n";
foreach (App\Models\Compte::all() as $c) {
    echo "COMPTE:\t{$c->id}\tuser_id={$c->user_id}\tsolde={$c->solde}\n";
}
foreach (App\Models\QrCode::all() as $q) {
    echo "QR:\t{$q->code}\n";
}
