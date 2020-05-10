<?php
use App\Http\Controllers\BotManController;

$botman = resolve('botman');

$botman->hears('Hi', function ($bot) {
    $bot->reply('Hello!');
});
$botman->hears('shap', BotManController::class.'@startConversation');

$botman->hears('menu', BotManController::class.'@menuConversation');