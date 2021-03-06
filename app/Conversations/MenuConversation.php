<?php

namespace App\Conversations;

use Illuminate\Foundation\Inspiring;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\BotMan;

use Illuminate\Support\Facades\Storage;

class MenuConversation extends Conversation
{
    /**
     * First question
     */
    function __construct(BotMan $bot)
    {
        // Access user
        $this->user = $bot->getUser();
        $this->driver = $this->getDriverById($this->user->getId());

        // error_log(var_export($this->user, 1));
        // error_log(json_encode($bot->getMessage()->getPayload()));
        error_log("Driver : " . $this->driver);
    }

    private function getDriverById($id)
    {
        $length = strlen($id);
        $driver = null;
        switch ($length) {
            case 9:
                $driver = "telegram";
                break;
            case 13:
                $driver = "web";
                break;
            default:
                # code...
                break;
        }
        return $driver;
    }

    public function askMenu($is_back = false)
    {
        $menu = "";
        if (!$is_back) {
            $menu .= "Hallo {$this->user->getFirstName()}, ";
        }
        $menu .= "Ada yang bisa saya bantu?";
        $question = Question::create($menu)
            ->fallback('Menu tidak tersedia.')
            ->callbackId('ask_menu')
            ->addButtons([
                Button::create('📂 Detail Saya')->value('my_detail'),
                Button::create('📷 Baca QR Code')->value('read_qr'),
                Button::create('😆 Tell a joke')->value('joke'),
                Button::create('😇 Give me a fancy quote')->value('quote'),
            ]);

        return $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                switch ($answer->getValue()) {
                    case "my_detail":
                        $this->say("Id : {$this->user->getId()}");
                        $this->say("Username : {$this->user->getUsername()}");
                        $this->say("Nama : {$this->user->getFirstName()} {$this->user->getLastName()} ");
                        $this->askBackToMenu();
                        break;
                    case "read_qr":
                        $this->askQr();
                        break;
                    case "joke":
                        $joke = json_decode(file_get_contents('http://api.icndb.com/jokes/random'));
                        $this->say($joke->value->joke);
                        $this->askBackToMenu();
                        break;
                    case "quote":
                        $this->say(Inspiring::quote());
                        $this->askBackToMenu();
                        break;
                }
            }
        });
    }

    public function askQr()
    {
        $this->askForImages('Silahkan upload gambar QR code.', function ($images) {

            // error_log(var_export($images[0], 1));

            $url = $images[0]->getUrl();
            // error_log($url);
            $this->say("Ok, Sedang membaca QR...");
            if (in_array($this->driver, ["telegram", "web"])) {
                $this->qrScan($url);
            } else {
                $this->qrScanThirdParty($url);
            }
        });
    }

    private function qrScan($url)
    {
        //Get File
        $name = $this->user->getId().'.jpg';
        
        $content = file_get_contents($url);
        $path = "public/temp/qrcode";

        $img = \Image::make($content);
        $width = $img->width();
        $height = $img->height();
        error_log("size : {$width}x{$height}");
        $need_resize = false;

        if($width > $height && $width > 720){
            $w = 720;
            $h = null;
            $need_resize = true;
            error_log('need_resize : ');
        }

        if($height > $width && $height > 720){
            $h = 720;
            $w = null;
            $need_resize = true;
            error_log('need_resize : ');
        }


        if($need_resize == true){
            $img->resize($w, $h, function ($constraint) {
                $constraint->aspectRatio();
            });
            $width = $img->width();
            $height = $img->height();
            error_log("RESIZE : {$width}x{$height}");
        }
        $img->encode('jpg', 50);

        try {
            error_log('START UPLOAD : ');
            $dropbox =  Storage::disk('dropbox')->getDriver()->getAdapter()->getClient();
            Storage::disk('dropbox')->put("{$path}/{$name}", (string) $img);
            $dropbox->createSharedLinkWithSettings("{$path}/{$name}");

            $link = $dropbox->listSharedLinks("{$path}/{$name}");
            error_log(json_encode($link));

            $raw = explode("?", $link[0]['url']);
            error_log(json_encode($raw));
            
            $url = $raw[0]. '?raw=1';
            error_log($url);

            $this->qrScanThirdParty($url);
        } catch (\Exception $e) {
            error_log("Error : ". $e->getMessage());
            $this->say("Baca QR gagal.");
            $this->askBackToMenu();
        }

        try {
            Storage::disk('dropbox')->delete("{$path}/{$name}");
        } catch (\Exception $e) {
            error_log("Error : ". $e->getMessage());
        }
    }

    private function qrScanThirdParty($url)
    {
        //make sure there are no credentials on URL send to Third Party
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.qrserver.com/v1/read-qr-code/?fileurl=" . urlencode($url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                // Set Here Your Requesred Headers
                'Content-Type: application/json',
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            $this->say("Baca QR gagal_");
            $this->askBackToMenu();
        } else {
            $scan = json_decode($response, true);;
            error_log(var_export($scan, 1));

            $scan_result = $scan[0]["symbol"][0]["data"];
            error_log(var_export($scan_result, 1));
            if (!is_null($scan_result)) {
                $this->say("QR data :");
                $this->say("{$scan_result}");
                $this->askBackToMenu();
            } else {
                $this->say("Baca QR gagal.");
                $this->askBackToMenu();
                // $this->askMenu();
            }
        }

        // return ['status' => 'finished'];
    }

    public function askBackToMenu()
    {
        $question = Question::create("Proses selesai, Selanjutnya?")
            ->fallback('Tidak tersedia.')
            ->callbackId('ask_back_to_menu')
            ->addButtons([
                Button::create('Kembali ke-Menu')->value('go_to_menu'),
                Button::create('Selesai/Stop')->value('stop'),
            ]);

        return $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                switch ($answer->getValue()) {
                    case "go_to_menu":
                        $this->askMenu(true);
                        break;
                }
            }
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
