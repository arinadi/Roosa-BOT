<?php

namespace App\Conversations;

use Illuminate\Foundation\Inspiring;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\BotMan;

class MenuConversation extends Conversation
{
    /**
     * First question
     */
    function __construct(BotMan $bot)
    {
        // Access user
        $this->user = $bot->getUser();
        error_log(var_export($this->user, 1));
    }
    public function askMenu()
    {
        $question = Question::create("Hallo {$this->user->getFirstName()}, Ada yang bisa saya bantu?")
            ->fallback('Menu tidak tersedia.')
            ->callbackId('ask_menu')
            ->addButtons([
                Button::create('Detail Saya')->value('my_detail'),
                Button::create('Baca QR Code')->value('read_qr'),
                Button::create('Tell a joke')->value('joke'),
                Button::create('Give me a fancy quote')->value('quote'),
            ]);

        return $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                switch ($answer->getValue()){
                    case "my_detail":
                        $this->say("Id : {$this->user->getId()}");
                        $this->say("Username : {$this->user->getUsername()}");
                        $this->say("Nama : {$this->user->getFirstName()} {$this->user->getLastName()} ");
                    break;
                    case "read_qr":
                        $this->askQr();
                    break;
                    case "joke":
                        $joke = json_decode(file_get_contents('http://api.icndb.com/jokes/random'));
                        $this->say($joke->value->joke);
                    break;
                    case "quote":
                        $this->say(Inspiring::quote());
                    break;
                    }
            }
        });
    }

    public function askQr()
{
    $this->askForImages('Silahkan upload gambar QR code.', function ($images) {
        
        error_log(var_export($images[0], 1));
        
        $url = $images[0]->getUrl();
        error_log($url);
        
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', "http://api.qrserver.com/v1/read-qr-code/", ['query' => [
            'fileurl' => urlencode($url),
        ]]);

        $scan = json_decode($response->getBody(), true);;

        $scan_result = $scan[0]["symbol"][0]["data"];
        error_log(var_export($scan_result, 1));
        if(!is_null($scan_result)){
            $this->say("QR data :");
            $this->say($scan_result);
        } else {
            $this->say("Baca QR gagal.");
            // $this->askMenu();
        }
        $this->say($scan->value->joke);
    });
}

    /**
     * Start the conversation
     */
    public function run()
    {
        $this->askMenu();
    }
}
