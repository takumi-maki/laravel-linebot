<?php

namespace App\Http\Controllers;

use App\Services\Gurunavi;

use App\Services\RestaurantBubbleBuilder;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;

use LINE\LINEBot\Event\MessageEvent\TextMessage;

use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;

class LineBotController extends Controller
{
    public function index()
    {
        return view('linebot.index');
    }
    
    public function restaurants(Request $request)
    {
        Log::debug($request->header());
        Log::debug($request->input());

        $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
        $lineBot = new LINEBot($httpClient,['channelSecret' => env('LINE_CHANNEL_SECRET')]);

        // ログ出力時にも登場した$request->header()は、引数を渡すと、それをキーとする値を返す
        $signature = $request->header('x-line-signature');
        // validateSignatureメソッドは、メッセージボディと署名を引数として受け取り、署名の検証を行う
        if(!$lineBot->validateSignature($request->getContent(), $signature)) {
            // false だったら　リクエストが不正であるとする
            abort(400, 'Invalid signature');
        }
        // メッセージの種類に応じたクラスのインスタンスを返します。例）テキストメッセージであればLINE\LINEBot\Event\MessageEvent\TextMessageクラス
         $events = $lineBot->parseEventRequest($request->getContent(), $signature);

         Log::debug($events);

         foreach($events as $event){
             if(!($event instanceof TextMessage)){
                 Log::debug('Non text message has come');
                 continue;
             }
             $gurunavi = new Gurunavi();
            //  検索結果を$gurunaviResponseに代入
             $gurunaviResponse = $gurunavi->searchRestaurants($event->getText());

            //  検索結果がエラーであった場合
             if(array_key_exists('error', $gurunaviResponse)){
                 $replyText = $gurunaviResponse['error'][0]['message'];
                 $replyToken = $event->getReplyToken();
                 $lineBot->replyText($replyToken,$replyText);
                 continue;
             }
            $bubbles = [];
            foreach($gurunaviResponse['rest'] as $restaurant) {
                $bubble = RestaurantBubbleBuilder::builder();
                $bubble->setContents($restaurant);
                $bubbles[] = $bubble;
            } 
            $carousel = CarouselContainerBuilder::builder();
            $carousel->setContents($bubbles);

            $flex = FlexMessageBuilder::builder();
            $flex->setAltText('飲食店検索結果');
            $flex->setContents($carousel);

            $lineBot->replyMessage($event->getReplyToken(), $flex);

            
         }
    }
}