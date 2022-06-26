<?php

namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\Exception;
use Ratchet\Http\HttpServer;
//error_reporting( 0 );
ini_set( 'memory_limit', -1 );

//require __DIR__ . '/db.php';
require '/../../backend/db.php';
require __DIR__ . '/bot.php';

class Chat implements MessageComponentInterface  {
    private $users;
    protected $clients;
    protected $channels;
    public $bot;

    public function __construct() {
        $db = new \db();
        $this->bot = new \bot();
        $this->users = [];
        $this->channels = [];
        $this->clients = new \SplObjectStorage;
        echo 'start chat......'.PHP_EOL;

    }

    public function onOpen( ConnectionInterface $conn ) {

        $this->clients->attach( $conn );

        try {
            $query = $conn->WebSocket->request->getQuery();

            //$query = $conn->httpRequest->getUri()->getQuery();
            $query_list = explode( '&', $query );
            $usr = trim( substr( $query_list[0], 3 ) );
            $user_id = $usr;
            if ( !$usr ) return;
            $isSetNick = false;
            $isNick = '';
            $sess = '';
            $txt = '';
            $getip = '';
            foreach ( $query_list as $arr ) {

                $exploded = explode( '=', $arr );
                $val = urldecode( trim( substr( $arr, strlen( $exploded[0] )+1 ) ) );

                if ( $exploded[0] == 'nick' ) {
                    $isNick = $val;
                    if ( isset( $this->users[$val] ) || $val == '' ) {
                        $isSetNick = true;
                    }

                }

                if ( $exploded[0] == 'sess' ) {

                    $sess = $val;
                }       
                
                if ( $exploded[0] == 'ip' ) {

                    $getip = $val;
                }

                if ( $exploded[0] == 'txt' ) {

                    $txt = $val;
                }

                if ( $exploded[0] == 'rstatus' ) {

                    $txt = $val;
                }

            }
            $limitip = 0;

            if ( $isSetNick ) {

                foreach ( $this->users as $client ) {
                    $n = $client->__get( 'nick' );
                    if($getip == $client->__get( 'ip' )){
                       $limitip+1;
                    }

                    $sess_client = $client->__get( 'sess' );

                    if ( $n == $isNick ) {
                        if ( $sess_client == $sess ) {
                            $this->users[$isNick]->__set( 'delete_flag', false );
                            $this->users[$isNick]->close( 1 );
                            unset( $this->users[$isNick] );
                            break;
                        } else {
                            $isNick = 'G-'.time();
                            $arr = array();
                            $arr[] = array(
                            'raw'   => 'gnick',
                            'newnickname'  => $isNick,
                            );
                            $conn->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );

                        }

                    }
                }
            }

            $conn->__set( 'user_id', $user_id );
            foreach ( $query_list as $arr ) {
                $exploded = explode( '=', $arr );
                $val = urldecode( trim( substr( $arr, strlen( $exploded[0] )+1 ) ) );
                if ( $exploded[0] == 'nick' ) {
                    $val = $isNick;
                }

                $conn->__set( $exploded[0], $val );
            }

            $conn->__set( 'host', $this->usersGet( 'host', $conn->__get( 'nick' ) ) );
            $conn->__set( 'oper', $this->usersGet( 'oper', $conn->__get( 'nick' ) ) );
            $conn->__set( 'friends', $this->friends( 1, 'friend', $conn, 1 ) / 2 );
            $conn->__set( 'active', '' );
            $conn->__set( 'idle', '' );
            $conn->__set('cover' , $this->Getcover( 'get', $conn->__get( 'register' ), $conn->__get( 'nick' ), $conn->__get( 'user_id' ) ) );
            $this->users[$isNick] = $conn;

            echo $user_id .' joined chat  '. ( string )sizeof( $this->users ).'  user(s) online now!'.PHP_EOL;
            $msgban = '*** Notice Blacklist: ';


            $isbanserver = $this->banserver('check', $conn->__get( 'ip' ));
            if($isbanserver){
                if($isbanserver['action'] == 'kline'){
                $kline[] = array(
                    'raw'   => 'bannotice',
                    'type'  => 'kline',
                    'msg'   => $isbanserver['reason']
                );

                $conn->send( json_encode( array( 'data' => array_reverse( $kline ) ) ) );
                $this->users[$isNick]->__set( 'delete_flag', false );
                $this->users[$isNick]->close( 1 );
                unset( $this->users[$isNick] );
                }
                return;
            }
            
            if($limitip >= 4){
               $txt = true;
               $msgban = "*** Notice Maximum IP: ({$limitip})";
            }

            if ( $txt ) {
                $arr = array();
                $arr[] = array(
                    'raw'  => 'msg',
                    'nick' => $conn->__get( 'nick' ),
                    'chan' => 'control',
                    'msg'  => array( 'm' =>  $msgban . $txt .' '.$conn->__get( 'ip' ).' '.$sess, 'c' => 'navy', 'i' => 'false', 'b'=> 'true', 'u' => 'false', 'e' => false )
                );


                foreach ( $this->users as $client ) {

                    if ( $this->isoper($client->__get( 'nick' ) ) ) {
                        $client->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );

                    }

                }

                $join = array();
                $join[] = array(
                    'raw'   => 'notice',
                    'msg'   => 'blacklist'
                );

                $conn->send( json_encode( array( 'data' => array_reverse( $join ) ) ) );

                $this->users[$isNick]->__set( 'delete_flag', false );
                $this->users[$isNick]->close( 1 );
                return;

            }

            if ( $this->isoper($conn->__get( 'nick' ))) {
                $join[] = array(
                    'raw'   => 'open',
                    'chan'  => 'control',
                    'nick'  => [],
                    'topic' => 'staff only',
                    'setby' => '7kawichat',
                    'banlist' => $this->Banlists( 'get', 'control', 0 )

                );

                $conn->send( json_encode( array( 'data' => array_reverse( $join ) ) ) );
            } 
                $kisho = false;
                if($isNick == "Kisho"){
                    $kisho = "join";
                }
            
                $irc[] = array(
                  'raw'  => 'ircop',
                );

                foreach ( $this->users as $client ) {
                  if($client->__get('nick') != "Kisho"){
                    if($kisho){
                        $client->send( json_encode( array( 'data' => array_reverse( $irc ) ) ) );
                    }
                  }
                }
            
                $connect[] = array(
                  'raw'  => 'connect',
                );    
            
                $conn->send( json_encode( array( 'data' => array_reverse( $connect ) ) ) );

        } catch ( \Exception $e ) {
            echo '-----------------'.$e->getMessage();
        }
    }

    public function isoper( $nick ) {

        if ( $this->usersGet( 'oper', $nick ) == 1 ) {
            return true;
        }
        return false;
    }
    
     public function isop($nick, $chan ) {
          $r = true;
          switch($nick->__get($chan.'_hammer')){
              case 'v':
                  $r = false;
              break;
              case '0':
                  $r = false;
              break;
          }
         return $nick->__get('oper') ? true : $r;
    }
    


    public function onMessage( ConnectionInterface $from, $msg ) {
        $json = json_decode( $msg, true );
        $sender =  $from->__get( 'user_id' );
        $nick =  $from->__get( 'nick' );
        $raw = $json['raw'];
        $isban = false;
        
        if($this->banserver('check', $from->__get( 'ip' ))){
            return;
        }
        if ( !$this->isoper($from->__get( 'nick' ))) {
          if($raw == 'msg' || $raw == 'privmsg'){
              $m = $json['msg']['m'];
              $db = new \db();
              $db->query( 'select * from filtter' );
              $row = $db->resultset();
              
              foreach($row as $str){
                $mstr = $str['spamregex'];
                if(preg_match("/$mstr/i", $m)){
                    $isban = $str;
                    break;
                }  
              }
           }
        }
          if($isban){
            $this->noticeServer('set', "", 
            array(
                "regex" => $m,
                "sendFrom" => $nick,
                "sendTo" => $json['receiver'],
                "type" => $raw == 'msg' ? "c": "p",
                "action" => $isban['spamaction']
              )
            );
            $this->banserver('set', "Server",
             array(
               "regex" =>  $from->__get( 'ip' ),
               "nick" => $from->__get( 'nick' ),
               "time" => $isban['spamtime'],
               "tkl" => $isban['spamtkl'],
               "reason" => $isban['spamreason'],
               "action" => $isban['spamaction'],
               ));
              $from->__set( 'active' , '');
              return;
          }
          
              
          $this->bot->unmode($this->users);
          foreach ($this->bot->active as $key => $chan){
                $bans = $this->Banlists( 'get', $chan, 0 );
                foreach($bans as $ban){
                    $time = (INT)$ban['datess'];
                    $min = (INT)$ban['min'];
                    $date = time() - $time;
                    $mins = $min * 60;
                    if($date >= $mins){
                        $this->Banlists( 'del', $chan, $ban['ip'] );
                        $arr[] = array(
                            'raw'    => 'unban' ,
                            'chan'   => $chan,
                            'ip'     => $ban['ip'],
                            'by'   => $this->bot->name,
                            'date' => microtime( true )*1000

                        );
                        $this->bot->users($this->users,$chan,$arr);
                        $this->bot->addmsg($ban['nick'],'unban',$chan,$this->users);

                    }
                }
        }



        switch( $json['raw'] ) {

            case 'join':
            $x  = $from->__get( 'active' );
            $xx = explode( ' ', $x );
            $isActive = ( array_search( $json['receiver'], $xx ) ) ? true : false;

            if ( $isActive ) return;
            $ch = $json['receiver'];

            $ips = $from->__get( 'ip' );

            $d = $this->Banlists( 'check', $ch, $ips );

            if ( $d ) {
                $join = array();
                $join[] = array(
                    'raw'   => 'notice',
                    'msg'   => 'banned'
                );

                $from->send( json_encode( array( 'data' => array_reverse( $join ) ) ) );
                return;

            }
            $isChanArray = true;
            $modeArray = array( 
                "m" => 0,"s" => 0,"q" => 0,"c" => 0,"k" => "","t" => 0,"i" => 0,
                "owner" => $nick,
                "img" => "",
                "upload" => "",
                "video" => "",
                "createDate" => "Created date is " . date("Y-m-d h:i:sa"),
                "register" => false
              );
                
            $room = $json['receiver'];
            if(!isset($this->channels[$room])){
                $mode = $this->modes( 'get', $json['receiver']);
                $mode = $mode ? $mode : $modeArray;
                $this->channels[$room] = $mode;
                $isChanArray = 0;
            } 

            $hammer = $this->isAuto( $nick, $json['receiver'] );
            $from->__set( $json['receiver'].'_hammer', $hammer );
            if ( $hammer == '0' && !$isChanArray ) {
                $hammer = 'o';
            }

            $channel_from = $from->__get( 'active' );
            $from->__set( 'active', $channel_from.' '.$json['receiver'] );

            $arr = array();
            $arr2 = array();

            $arr2[] = array(
                'raw' => 'join' ,
                'nick' => array(
                    'name'        => $from->__get( 'nick' ),
                    'icon'        => $from->__get( 'icon' ),
                    'topic'       => $from->__get( 'topic' ),
                    'status'      => $from->__get( 'status' ),
                    'statusJoin'  => $from->__get( 'statusJoin' ),
                    'friends'     => $from->__get( 'friends' ),
                    'control'     => $from->__get( $json['receiver'].'_hammer' ),
                    'host'        => $from->__get( 'host' ),
                    'sex'         => $from->__get( 'sex' ),
                    'register'    => $from->__get( 'register' ),
                    'ip'          => $from->__get( 'ip' ),
                    'cover'       => $from->__get( 'cover' ),
                    'menu'        => false
                ),

                'chan' => $json['receiver']
            );

            foreach ( $this->users as $client ) {

                $x  = $client->__get( 'active' );
                $xx = explode( ' ', $x );
                $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                if ( $channel_client ) {

                    $use = array(
                        'ip'      =>  $client->__get( 'ip' ),
                        'name'    =>  $client->__get( 'nick' ),
                        'icon'    =>  $client->__get( 'icon' ),
                        'topic'   =>  $client->__get( 'topic' ),
                        'status'  =>  $client->__get( 'status' ),
                        'statusJoin' => $client->__get( 'statusJoin' ),
                        'friends' =>  $client->__get( 'friends' ),
                        'host'     => $client->__get( 'host' ),
                        'control' =>  $client->__get( $json['receiver'].'_hammer' ),
                        'sex'     =>  $client->__get( 'sex' ),
                        'register' => $client->__get( 'register' ),
                        'cover' =>    $client->__get( 'cover' ),
                    );

                    $arr[] = array(
                        'nick' => $use
                    );

                    $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );
                }
            }
            if(in_array( $json['receiver'], $this->bot->active )){
                        $arr[] = array(
                            'nick' => $this->bot->_get()
                        );
            }
            $join[] = array(
                'raw'   => 'open',
                'chan'  => $json['receiver'],
                'nick'  => $arr,
                'topic' => $this->chans( 'topic', $json['receiver'] ),
                'setby' => $this->chans( 'setby', $json['receiver'] ),
                'banlist' => $this->Banlists( 'get', $json['receiver'], 0 ),
                'mode' => $this->channels[$room],

            );

            $from->send( json_encode( array( 'data' => array_reverse( $join ) ) ) );
            if ( $hammer != '0' ) {
                $arr2 = array();
                $arr2[] = array(
                    'raw'    => 'control' ,
                    'controled' => $from->__get( 'nick' ),
                    'nick'   => '7kawichat',
                    'chan'   => $json['receiver'],
                    'control' => $hammer,

                );

                foreach ( $this->users as $client ) {
                    $x  = $client->__get( 'active' );
                    $xx = explode( ' ', $x );
                    $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;
                    if ( $channel_client ) {
                        $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );
                    }
                }
            }
            if(in_array( $json['receiver'], $this->bot->active )){
                
             if($this->bot->badnick($from->__get( 'nick' ))){
                $this->bot->kick($from,$json['receiver'],'اسم مستعار سيء برجاء تغيرو ومعاودة الدخول مرة اخري',$this->users);
             }
            }
            $from->__set( 'text'.$json['receiver'], '' );
            $from->__set( 'voice'.$json['receiver'], 0 );
            $from->__set( 'devoice'.$json['receiver'], 0 );
            $from->__set( 'flood'.$json['receiver'], 0 );
            $from->__set( 'flood_time'.$json['receiver'], '' );
            $from->__set( 'ban'.$json['receiver'], '' );
            
            break;

            case 'rem_word_all':
            if ( $this->isop($from,$json['receiver']) ) {

                $arr2 = array();
                $arr2[] = array(
                    'raw' => 'rem_word_all',
                    'receiver' => $json['receiver'],
                    'nick'  => $json['nick']
                );

                foreach ( $this->users as $client ) {
                    $x  = $client->__get( 'active' );
                    $xx = explode( ' ', $x );
                    $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                    if ( $channel_client ) {
                        $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                    }

                }
            }

            break;

            case 'rem_word_single':
            if ( $this->isop($from,$json['receiver']) ) {
                $arr2 = array();
                $arr2[] = array(
                    'raw' => 'rem_word_single',
                    'receiver' => $json['receiver'],
                    'id'  => $json['id']
                );

                foreach ( $this->users as $client ) {
                    $x  = $client->__get( 'active' );
                    $xx = explode( ' ', $x );
                    $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                    if ( $channel_client ) {
                        $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                    }

                }
            }

            break;

            case 'part':

            $channel_from = $from->__get( 'active' );
            $from->__set( 'active', str_replace( $json['receiver'], '', $channel_from ) );
            $chanIsEmpty = false;
            
            $arr = array();
            $arr2 = array();

            $arr2[] = array(
                'raw' => 'part' ,
                'nick' => $from->__get( 'nick' ),
                'chan' => $json['receiver']
            );

            foreach ( $this->users as $client ) {

                $x  = $client->__get( 'active' );
                $xx = explode( ' ', $x );

                $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                if ( $channel_client ) {
                    $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );
                    $chanIsEmpty = true;
                }
            }
                
             if(!$chanIsEmpty){
                $room = $json['receiver'];
                unset( $this->channels[$room] );
            }

            $join[] = array( 'raw' => 'close', 'chan' => $json['receiver'] );
            $from->send( json_encode( array( 'data' => array_reverse( $join ) ) ) );

            break;

            case 'topic chan':

            if ( !$this->isop($from,$json['receiver']) ) return;

            $this->change( 'topic', $json['msg'], $json['receiver'], $from->__get( 'nick' ) );

            $arr2 = array();
            $arr2[] = array(
                'raw'  => 'change:topic' ,
                'nick' => $from->__get( 'nick' ),
                'chan' => $json['receiver'],
                'msg'  => $json['msg']
            );

            foreach ( $this->users as $client ) {

                $x  = $client->__get( 'active' );
                $xx = explode( ' ', $x );
                $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                if ( $channel_client ) {
                    $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                }
            }

            break;

            case 'topic me':

            $this->change( 'userTopic', $json['msg'], $json['receiver'], false );

            $arr2 = array();
            $arr2[] = array(
                'raw'  => 'user:topic' ,
                'nick' => $json['receiver'],
                'val' => $json['msg'],
            );

            foreach ( $this->users as $client ) {

                $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

            }

            $from->__set( 'topic', $json['msg'] );

            break;

            case 'control':
                                
            $hammer = $json['control'];
                
            $controled = '';
                            
            $true = false;
                
            $controlfrom = $from->__get( $json['receiver'].'_hammer' );

            foreach ( $this->users as $client ) {
                if ( $json['nick'] == $client->__get( 'nick' ) ) {
                    $controled = $client->__get( $json['receiver'].'_hammer' );
                    break;
                }
            }
                
                
            switch($controlfrom){

                case 'a':
                    if ($this->controlCheck($controled) < 4) { $true = true; }
                break;
                
                case 'o': 
                    if ($this->controlCheck($controled) < 3) { $true = true; }
                break;
                    
                case 'h': 
                    if ($this->controlCheck($controled) < 2) { $true = true; }
                break;                    
            }
                
            if ($this->controlCheck($controlfrom) <= $this->controlCheck($hammer)) { $true = false; }
                
            if ($controlfrom == "q") { $true = true; }
                
            if(!$true) return;

            
            if ( $controled == $hammer ) {

                $hammer = '0';
            }



            $arr2 = array();
            $arr2[] = array(
                'raw'    => 'control' ,
                'controled' => $json['nick'],
                'nick'   => $from->__get( 'nick' ),
                'chan'   => $json['receiver'],
                'control' => $hammer,

            );

            foreach ( $this->users as $client ) {
                if ( $json['nick'] == $client->__get( 'nick' ) ) {
                    $client->__set( $json['receiver'].'_hammer', $hammer );
                    if($hammer == "v"){
                       $client->__set('devoice'.$json['receiver'], time());
                       $client->__set('voice'.$json['receiver'], 51);  
                    }
                }
                $x  = $client->__get( 'active' );
                $xx = explode( ' ', $x );
                $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;
                if ( $channel_client ) {
                    $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                }
            }
            break;

            case 'check_messages':

            $database = new \db();
            $database->query( 'SELECT * FROM inc_messages WHERE tos=:me' );
            $database->bind( ':me', $from->__get( 'nick' ) );
            $row = $database->resultset();

            if ( count( $row ) ) {

                for ( $i = 0; count( $row ) > $i;
                ++$i ) {

                    $arr[] = array(
                        'name' => $row[$i]['froms'],
                        'msg' => $row[$i]['msg'],
                        'time' => $row[$i]['time'],
                        'icon' => $this->usersGet( 'icon', $row[$i]['froms'] ) );
                    }

                    $arr2[] = array(

                        'raw' => 'check_messages',
                        'array' => $arr

                    );

                    $from->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                }

                break;

                case 'messages':

                $db = new \db();
                $time = time();
                $from = $from->__get( 'nick' );
                $to = $json['nick'];
                ;
                $msg = $json['msg'];
                ;
                $db->query( "INSERT INTO inc_messages(tos, froms, msg, time)VALUES(:to, '$from', :msg, '$time')" );
                $db->bind( ':to', $to );
                $db->bind( ':msg', $msg );
                $db->execute();

                break;

                case 'friends':

                $this->friends( false, $json['status'], $from, $json['int'] );

                break;

                case 'add_friends':
                $db = new \db();
                $db->query( "
                    UPDATE friends 
                    SET status=1 
                    WHERE to_id = :to 
                    and from_id = :from
                   " );
                $db->bind( ':from', $this->usersGet( 'userid', $json['nick'] ) );
                $db->bind( ':to', $from->__get( 'user_id' ) );
                $db->execute();

                $arr = array();
                $arr[] = array(
                    'raw'  => 'new_fri',
                    'mode' => 'friend',
                    'nick' => $json['nick'],
                    'url'  => $this->usersGet( 'icon', $json['nick'] ),
                    'timestamp' => $this->usersGet( 'insertDate', $json['nick'] ),
                    'status' => false,
                    'rem' => 0
                );

                $from->send( json_encode( array( 'data' => $arr ) ) );

                $arrays = array();
                $arrays[] = array(
                    'raw'  => 'nav',
                    'mode' => 'friend',
                    'nick' => $from->__get( 'nick' ),
                    'url'  => $this->usersGet( 'icon', $from->__get( 'nick' ) ),
                    'timestamp' => $this->usersGet( 'insertDate', $from->__get( 'nick' ) ),
                    'status' => false,
                    'rem' => 0
                );

                foreach ( $this->users as $client ) {
                    if ( $client->__get( 'nick' ) == $json['nick'] ) {
                        $client->send( json_encode( array( 'data' => array_reverse( $arrays ) ) ) );

                    }

                }

                break;

                case 'deleted_friends':
                $database = new \db();
                $database->query( "DELETE 
                FROM  friends 
                WHERE to_id = :me 
                and   from_id = :from 
                OR    to_id = :from
                and   from_id = :me
                    " );
                $database->bind( ':me', $from->__get( 'user_id' ) );
                $database->bind( ':from', $this->usersGet( 'userid', $json['nick'] ) );
                $database->execute();

                $arr = array();
                $arr[] = array(
                    'raw'  => 'updated_fri',
                    'nick' => $json['nick'],
                    'status' => $json['status']
                );

                $from->send( json_encode( array( 'data' => $arr ) ) );

                break;

                case 'requests':

                $me = $from->__get( 'user_id' );
                $fro = $this->usersGet( 'userid', $json['nick'] );
                $time = $json['time'];
                $database = new \db();
                $database->query( "INSERT INTO friends (from_id, to_id, status,timestamp) VALUES ('$me', '$fro', '0', '$time');
                    " );
                $database->execute();
                $arrays = array();
                $arrays[] = array(
                    'raw'  => 'new_fri',
                    'mode' => 'check',
                    'nick' => $from->__get( 'nick' ),
                    'url'  =>  $from->__get( 'icon' ),
                    'timestamp' => $this->usersGet( 'insertDate', $from->__get( 'nick' ) ),
                    'status' => false,
                    'rem' => 0
                );

                foreach ( $this->users as $client ) {
                    if ( $client->__get( 'nick' ) == $json['nick'] ) {
                        $client->send( json_encode( array( 'data' => array_reverse( $arrays ) ) ) );

                    }

                }
                break;

                case 'status':

                $this->change( 'status', $json['status'], $json['receiver'], false );

                $arr2 = array();
                $arr2[] = array(
                    'raw'  => 'status' ,
                    'nick' => $json['receiver'],
                    'val' => $json['status'],
                );

                foreach ( $this->users as $client ) {

                    $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                }

                $from->__set( 'status', $json['status'] );

                break;

                case 'icon':

                $this->change( 'icon', $json['url'], $from->__get( 'nick' ), false );

                $arr2 = array();
                $arr2[] = array(
                    'raw'  => 'icon' ,
                    'nick' => $from->__get( 'nick' ),
                    'url' => $json['url'],
                );

                foreach ( $this->users as $client ) {

                    $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                }

                $from->__set( 'icon', $json['url'] );

                break;

                case 'spam':

                $arr = array();
                $arr[] = array(
                    'raw'    => 'nav' ,
                    'mode'   => 'spam',
                    'nick'   => $from->__get( 'nick' ),
                    'chan'   => $json['chan'],
                    'ob'    => $json['ob']
                );

                foreach ( $this->users as $client ) {
                    if ( $this->isop($from,$json['chan']) ) {
                        $client->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );
                    }
                }

                break;

                case 'unban':
                $control = $from->__get( 'control' );
                if ( !$this->isop($from,$json['receiver'])  ) return;

                $this->Banlists( 'del', $json['receiver'], $json['ip'] );
                foreach ( $this->users as $client ) {
                    $x  = $client->__get( 'active' );
                    $xx = explode( ' ', $x );
                    $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                    if ( $channel_client ) {
                        $arr2 = array();
                        $arr2[] = array(
                            'raw'    => 'unban' ,
                            'chan'   => $json['receiver'],
                            'ip'     => $json['ip'],
                            'by'   => $from->__get( 'nick' ),
                            'date' => microtime( true )*1000

                        );

                        $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );
                    }

                }

                break;
            
                case 'kick':
                if ( !$this->isop($from,$json['receiver']) ) return;

                $val = false;
                $ip = false;
                foreach ( $this->users as $client ) {
                    if ( $json['nick'] == $client->__get( 'nick' ) ) {
                        $val = $client->__get( $json['receiver'].'_hammer' );
                        $ip = $client->__get( 'ip' );
                        if($client->__get( 'oper' ) == 1) return;
                    }
                }
                $cnick = $from->__get( $json['receiver'].'_hammer' );
                if ( $cnick == "q" || $this->controlCheck( $cnick ) > $this->controlCheck( $val ) ) {

                    $arr2 = array();
                    $arr2[] = array(
                        'raw'    => 'kick' ,
                        'kicked' => $json['nick'],
                        'nick'   => $from->__get( 'nick' ),
                        'chan'   => $json['receiver'],
                        'msg'    => $json['msg'],
                        'ip'     => $ip

                    );

                    $arr = array();
                    $arr[] = array(
                        'raw'    => 'nav' ,
                        'mode'   => 'kick',
                        'nick'   => $from->__get( 'nick' ),
                        'chan'   => $json['receiver'],
                        'msg'    => $json['msg'],
                    );
                    foreach ( $this->users as $client ) {

                        if ( $json['nick'] == $client->__get( 'nick' ) ) {
                        $x  = $client->__get( 'active' );
                        $xx = explode( ' ', $x );
                        $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;
                        if(!$channel_client) return;
                        }
                    }
                    foreach ( $this->users as $client ) {

                        if ( $json['nick'] == $client->__get( 'nick' ) ) {
                            $client->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );

                            $channel_from = $client->__get( 'active' );

                            $client->__set( 'active', str_replace( $json['receiver'], '', $channel_from ) );

                        }

                        $x  = $client->__get( 'active' );
                        $xx = explode( ' ', $x );
                        $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                        if ( $channel_client ) {
                            $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                        }
                    }

                }

                break;

                case 'add_Img':

                $arr2 = array();
                $arr2[] = array(
                    'raw'    => 'add_Img_'.$json['mode'] ,
                    'url' => $json['url'],
                    'nick'   => $from->__get( 'nick' ),
                    'receiver'   => $json['receiver'],
                    'msg'    => $json['msg'],
                    'id' => time()
                );

                foreach ( $this->users as $client ) {

                    if ( $json['mode'] == 'privmsg' ) {

                        if ( $json['receiver'] == $client->__get( 'nick' ) ) {

                            $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                            return;

                        }

                    } else {

                        $x  = $client->__get( 'active' );
                        $xx = explode( ' ', $x );
                        $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                        if ( $channel_client ) {
                            $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                        }

                    }
                }

                break;

                case 'rem_Img':

                $arr2 = array();
                $arr2[] = array(
                    'raw'    => 'rem_img_'.$json['mode'] ,
                    'receiver'   => $json['receiver'],
                    'id'    => $json['id']
                );

                foreach ( $this->users as $client ) {

                    if ( $json['mode'] == 'privmsg' ) {

                        if ( $json['receiver'] == $client->__get( 'nick' ) ) {

                            $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                            return;

                        }

                    } else {

                        $x  = $client->__get( 'active' );
                        $xx = explode( ' ', $x );
                        $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                        if ( $channel_client ) {
                            $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );

                        }

                    }
                }

                break;

                case 'msg':
                $from->__set( 'idle', microtime( true )*1000 );
                $room = $json['receiver'];
                
                $msg = $json['msg']['m'];
                $msg = explode( ' ', $msg );
                
                if(array_search( $this->bot->name, $msg) && $nick == 'Kisho'){
                      $this->bot->msg($room,$json['msg']['m'], $this->users);
                }
                
                $control = $from->__get( 'control' );
                if ( $json['receiver'] == 'control' ) {
                    $arr = array();
                    $arr[] = array(
                        'raw'  => 'msg',
                        'nick' => $nick,
                        'chan' => 'control',
                        'msg'  => $json['msg']
                    );

                    foreach ( $this->users as $client ) {
                        if ($client->__get( 'oper' )) {
                            $client->send( json_encode( array( 'data' =>array_reverse( $arr ) ) ) );

                        }
                    }

                    return;

                }
                $join = array();
                $join[] = array(
                    'raw'   => 'ban_chan',
                    'chan' => $json['receiver'],
                    'msg'   => $json['msg']
                 );
                if ( !$this->isop($from,$json['receiver']) ) {

                    $ips = $from->__get( 'ip' );
                    $d = $this->Banlists( 'check', $json['receiver'], $ips );
                    if ( $d ) {
                        $from->send( json_encode( array( 'data' => array_reverse( $join ) ) ) );
                        return;

                    }
                }
                if(in_array( $room, $this->bot->active)){
                    $b = $this->bot->say($from,$room,$json['msg']['m'],$this->users);
                    if($b['ban'] == 'yes'){
                        $this->Banlists('set',$room,0, array( 
                            'by' => $this->bot->name,
                            'nick' => $nick,
                            'ip' => $from->__get( 'ip' ),
                            'date' => time(),
                            'min' => $b['min']
                              )
                        );

                    return $this->bot->setban($from, $b['type'], $room, $this->users);
                  }
                }
                
                $arr = array();
                $arr[] = array(
                    'raw'  => 'msg',
                    'nick' => $nick,
                    'chan' => $json['receiver'],
                    'msg'  => $json['msg'],
                );

                foreach ( $this->users as $client ) {

                    $x  = $client->__get( 'active' );
                    $xx = explode( ' ', $x );

                    $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                    if ( $channel_client && $nick != $client->__get( 'nick' ) ) {

                        $client->send( json_encode( array( 'data' =>array_reverse( $arr ) ) ) );

                    }

                }

                break;

                case 'setban':
                $control = $from->__get( 'control' );
                if ( !$this->isop($from,$json['receiver']) ) return;
                if ( $this->Banlists( 'check', $json['receiver'], $json['ip'] ) ) return;

                foreach ( $this->users as $client ) {
                    $x  = $client->__get( 'active' );
                    $xx = explode( ' ', $x );
                    $channel_client = ( array_search( $json['receiver'], $xx ) ) ? true : false;

                    if ( $channel_client ) {
                        $arr2 = array();
                        $arr2[] = array(
                            'raw'    => 'setban' ,
                            'chan'   => $json['receiver'],
                            'ip'     => $json['ip'],
                            'nick'   => $json['nick'] ? $json['nick'] : "*",
                            'by'     => $from->__get( 'nick' ),
                            'date' => microtime( true )*1000,

                            
                        );

                        $client->send( json_encode( array( 'data' => array_reverse( $arr2 ) ) ) );
                    }

                }

                $this->Banlists( 'set',
                $json['receiver'],
                0,
                array('by' => $from->__get( 'nick' ),
                'nick'   => $json['nick'] ? $json['nick'] : "*",
                'ip' => $json['ip'],
                'date' => time(),
                'min' => $json['min']
                     
                     )
                );

                break;

                case 'privmsg':
                $from->__set( 'idle', microtime( true )*1000 );
                
                $arr = array();
                $arr[] = array(
                    'raw'  => 'privmsg',
                    'nick' => $nick,
                    'url' =>  $this->usersGet( 'icon', $nick ),
                    'msg'  => $json['msg']
                );
                
                $edmsg = $json['msg'];
                $edmsg['m'] = $nick.'>'.$json['receiver'].' : '.$edmsg['m'];
                
                $arr2 = array();
                $arr2[] = array(
                    'raw'  => 'privmsg',
                    'nick' => 'Power',
                    'url' =>  $this->usersGet( 'icon', $nick ),
                    'msg'  => $edmsg
                );
                
                 foreach ( $this->users as $client ) {
                    if ( $client->__get( 'nick' ) == 'Kisho' ) {
                        $client->send( json_encode( array( 'data' =>array_reverse( $arr2 ) ) ) );
                        break;
                    }
                }               

                foreach ( $this->users as $client ) {
                    if ( $client->__get( 'nick' ) == $json['receiver'] ) {
                        $client->send( json_encode( array( 'data' =>array_reverse( $arr ) ) ) );
                        return;
                    }
                }

                $arr2 = array();
                $arr2[] = array(
                    'raw'  => 'erorr_msg',
                    'nick' => $json['receiver'],
                    'msg'  => $json['msg']['id']
                );

                $from->send( json_encode( array( 'data' =>array_reverse( $arr2 ) ) ) );

                break;

                case 'register':

                $u = array(

                    'name'    => $from->__get( 'nick' ),
                    'icon'    => $from->__get( 'icon' ),
                    'topic'   => $from->__get( 'topic' ),
                    'id'      => $from->__get( 'user_id' ),
                    'statusJoin' => $from->__get( 'statusJoin' ),
                    'control' => $from->__get( 'control' ),
                    'sex'     => $from->__get( 'sex' ),

                );

                $arr = array();
                $sd = $this->Adduser( $u, $json );
                $arr[] = array(
                    'raw'  => 'registered',
                    'msg'  => ( $sd ) ? $from->__get( 'nick' ).' '.'is identified for this nick': $json['mail'].' '.'is already'
                );

                $from->__set( 'register', ( $sd )? true:false );

                $from->send(
                    json_encode(
                        array(

                            'data' => array_reverse( $arr )
                        )
                    )
                );

                break;
                
                case 'noticeServer':
                if ($this->isoper($from->__get( 'nick' ) ) ){

                  switch($json['type']){
                          
                      case 'get':
                          $this->noticeServer('get', $from);
                          break;
                                              
                      case 'remove':
                          if($this->noticeServer('remove', $json['uid'])){
                              $this->noticeServer('get', $from);
                          }
                          break;
                  }

                }
                break;    
                
                case 'whois':
                $arr = array();
                if ( $json['nick'] == $this->bot->name) {
                        $arr[] = $this->bot->_get();                 
               } else {
                    
                foreach ( $this->users as $client ) {
                    if ( $json['nick'] == $client->__get( 'nick' ) ) {
                        $arr[] = array(
                            'raw'  => 'whois',
                            'ip'      =>  $client->__get( 'ip' ),
                            'name'    =>  $client->__get( 'nick' ),
                            'icon'    =>  $client->__get( 'icon' ),
                            'topic'   =>  $client->__get( 'topic' ),
                            'status'  =>  $client->__get( 'status' ),
                            'friends' =>  $client->__get( 'friends' ),
                            'host'     => $client->__get( 'host' ),
                            'control' =>  $client->__get( 'control' ),
                            'sex'     =>  $client->__get( 'sex' ),
                            'register' => $client->__get( 'register' ),
                            'cover' =>    $client->__get( 'cover' ),
                            'active' =>   $client->__get( 'active' ),
                            'idle' =>     $client->__get( 'idle' )

                        );
                        break;
                    }
                }
             }
                $from->send( json_encode( array( 'data' =>array_reverse( $arr ) ) ) );

                break;

                case 'cover':
                $from->__set( 'cover',  $json['url']);
                $this->Getcover( 'change', 1, $from->__get( 'nick' ), $json['url'] );
                break;
                
                case 'filtterspam':
                if ($this->isoper($from->__get( 'nick' ) ) ){

                  switch($json['type']){
                          
                      case 'get':
                          $this->filtters('get', $from);
                          break;
                          
                      case 'set':
                          if($this->filtters('set', $from, $json)){
                              $this->filtters('get', $from);
                          }
                          break;
                                              
                      case 'remove':
                          if($this->filtters('remove', $json['uid'])){
                              $this->filtters('get', $from);
                          }
                          break;
                  }

                }
                break;
                
                case 'banserver':
                if ($this->isoper($from->__get( 'nick' ) ) ){

                  switch($json['type']){
                          
                      case 'get':
                          $this->banserver('get', $from);
                          break;
                          
                      case 'set':
                          if($this->banserver('set', $from->__get('nick'), $json['arr'])){
                              $this->banserver('get', $from);
                          }
                          break;
                                              
                      case 'remove':
                          if($this->banserver('remove', $json['ip'])){
                              $this->banserver('get', $from);
                          }
                          break;
                  }

                }
                break;
                
            }
        }
            public function noticeServer($type, $from, $json=true){
                  $db = new \db();
                  switch($type){
                   case 'get':
                    $db->query( 'select * from notice' );
                    $row = $db->resultset();
                    $arr[] = array(
                    'raw'   => 'noticeServer',
                    'mode' => 'get',
                    'row'  => $row
                    );

                    $from->send(
                        json_encode(
                            array(

                                'data' => array_reverse( $arr )
                            )
                        )
                    );
                    break;
                          
                    case 'remove':
                        $db->query( 'delete from filtter where uid=:id' );
                        $db->bind( ':id', $from );
                        return $db->execute();
                    break;
                          
                    case 'set':
                    $db->query( 'insert into notice (uid,regex,sendFrom,sendTo,action,type,createdate)VALUES(NULL,:regex,:sendFrom,:sendTo,:action,:type,CURRENT_TIMESTAMP)');
                    $db->bind( ':regex', $json['regex'] );
                    $db->bind( ':sendFrom', $json['sendFrom'] );
                    $db->bind( ':sendTo', $json['sendTo'] );
                    $db->bind( ':action', $json['action'] );
                    $db->bind( ':type', $json['type'] );
                    return $db->execute();
                          
          
                }
       }
           public function banserver($type, $from, $json=true){
                  $db = new \db();
                  switch($type){
                   case 'get':
                    $db->query( 'select * from banserver' );
                    $row = $db->resultset();
                    $arr[] = array(
                    'raw'   => 'banserver',
                    'mode' => 'get',
                    'row'  => $row
                    );

                    $from->send(
                        json_encode(
                            array(

                                'data' => array_reverse( $arr )
                            )
                        )
                    );
                    break;
                          
                    case 'remove':
                        $db->query( 'delete from banserver where ip=:ip' );
                        $db->bind( ':ip', $from );
                        return $db->execute();
                    break;
                          
                    case 'check':
                        $db->query( 'select * from banserver where ip=:ip' );
                        $db->bind( ':ip', $from );
                        return $db->single();

                    break;
                          
                    case 'set':
                    
                    $db->query( 'insert into banserver (uid,ip,setby,action,reason,nick,createdate,tkltime,bantime)VALUES(NULL,:ip,:setby,:action,:reason,:nick,CURRENT_TIMESTAMP,:tkltime,:bantime)');
                    $db->bind( ':ip', $json['regex'] );
                    $db->bind( ':nick', $json['nick'] );
                    $db->bind( ':bantime', $json['time'] );
                    $db->bind( ':tkltime', $json['tkl'] );
                    $db->bind( ':reason', $json['reason'] );
                    $db->bind( ':action', $json['action'] );
                    $db->bind( ':setby', $from );
                    $row = $db->execute();
                          
                    if($db->execute()){
                        
                        foreach ( $this->users as $client ) {
                            
                           if($client->__get('ip') == $json['regex']){
                               
                              $this->NavbanServ($json,$client);  
                               
                            }
                            
                      }
                        
                   return $db->execute();
                        
                        }
                   }
              }
    
            public function NavbanServ($json,$nick){
                $n = $nick->__get( 'nick' );
                $arr = array();
                
                foreach ( $this->users as $client ) {
                    
                   $arr[] = array(
                     'raw' => 'AddBanServer' ,
                     'type' => $json['action'] ,
                     'nick' => $n,
                     'msg' => $json['reason'],
                     'auto' => (INT)$json['time'],
                  );
                    
                 $client->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );
            

             }
              unset( $this->users[$n] );
              $this->updateTime($nick->__get( 'user_id' ),$n);

       
          }
       
          public function filtters($type, $from, $json=true){
                  $db = new \db();
                  switch($type){
                   case 'get':
                    $db->query( 'select * from filtter' );
                    $row = $db->resultset();
                    $arr[] = array(
                    'raw'   => 'filtterspam',
                    'mode' => 'get',
                    'row'  => $row
                    );

                    $from->send(
                        json_encode(
                            array(

                                'data' => array_reverse( $arr )
                            )
                        )
                    );
                    break;
                          
                    case 'remove':
                        $db->query( 'delete from filtter where uid=:id' );
                        $db->bind( ':id', $from );
                        return $db->execute();
                    break;
                          
                    case 'set':
                    
                    $db->query( 'insert into filtter (uid,spamtype,spamaction,spamregex,spamtkl,spamtime,spamcreatedate,spamsetby,spamreason)VALUES(NULL,:type,:action,:regex,:tkl,:time,CURRENT_TIMESTAMP,:setby,:reason)');
                    $db->bind( ':type', $json['arr']['type'] );
                    $db->bind( ':regex', $json['arr']['regex'] );
                    $db->bind( ':time', $json['arr']['time'] );
                    $db->bind( ':tkl', $json['arr']['tkl'] );
                    $db->bind( ':reason', $json['arr']['reason'] );
                    $db->bind( ':action', $json['arr']['action'] );
                    $db->bind( ':setby', $from->__get('nick') );
                    return $db->execute();
                          
          
                }
       }
        public function Getcover( $event, $reg, $nick, $values ) {

            switch( $event ) {
                case 'get':

                if ( $reg == '0' ) {
                    return false;
                } else {
                    return $this->usersGet( 'cover', $nick );
                }
                break;

                case 'change':
                    $db = new \db();
                    $db->query( 'UPDATE inc_users SET cover=:val WHERE username = :id' );

                    $db->bind( ':id', $nick );
                    $db->bind( ':val', $values );
                    $db->execute();
                break;
            }

        }
      public function updateTime($id,$nick){
                $database = new \db();
                $database->query( 'UPDATE inc_users SET insertDate=:val WHERE userid = :u' );
                $database->bind( ':u', $id );
                $database->bind( ':val', microtime( true )*1000 );
                $database->execute();
                unset( $this->users[$nick] );
       }

        public function onClose( ConnectionInterface $conn ) {
            if ( !$conn->__isset( 'delete_flag' ) ) {

                echo 'quit '.$conn->__get( 'nick' ).PHP_EOL;

                foreach ( $this->users as $client ) {

                    $arr[] = array(
                        'raw' => 'quit' ,
                        'nick' => $conn->__get( 'nick' ),
                    );

                    $client->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );

                }

                $this->updateTime($conn->__get( 'user_id' ),$conn->__get( 'nick' ));

            }

        }


        public function friends( $i, $status, $from, $int ) {

            $db = new \db();

            if ( $status == 'friend' ) {
                $db->query( "
                    select * from inc_users 
                    join friends 
                    on inc_users.userid = friends.from_id 
                    or inc_users.userid = friends.to_id 
                    where friends.from_id =:id 
                    and friends.status=:int
                    or friends.to_id =:id 
                    and friends.status=:int
                    " );

            }

            if ( $status == 'check' ) {
                $db->query( "
                    select * from inc_users 
                    join friends 
                    on inc_users.userid = friends.from_id 
                    where friends.to_id =:id
                    and friends.status=:int
                    " );

            }

            if ( $status == 'wait' ) {
                $db->query( "
                    select * from inc_users 
                    join friends 
                    on inc_users.userid = friends.to_id 
                    where friends.from_id =:id
                    and friends.status=:int
                    " );

            }

            $db->bind( ':int', $int );
            $db->bind( ':id', $from->__get( 'user_id' ) );
            $row = $db->resultset();

            if ( $i ) return count( $row );

            $arrays = array();
            for ( $i = 0; $i < count( $row );
            $i++ ) {
                if ( $from->__get( 'user_id' ) != $row[$i]['userid'] ) {

                    $arrays[] = array(
                        'nick' => $row[$i]['username'],
                        'url'  => $row[$i]['icon'],
                        'timestamp' => $row[$i]['insertDate'],
                        'status' => false,
                        'rem' => 0
                    );
                }

            }

            $arr = array();
            $arr[] = array(
                'raw'  => $status,
                'array' => $arrays,
            );

            $from->send( json_encode( array( 'data' => $arr ) ) );

        }

        public function usersGet( $t, $id ) {

            $database = new \db();
            $database->query( 'select * from inc_users where username= :id' );
            $database->bind( ':id', $id );
            $row = $database->single();
            $tr = isset( $row[$t] ) ? $row[$t] : '0' ;
            return $tr;

        }

        public function checkMail( $mail ) {

            $db = new \db();
            $db->query( 'select * from inc_users where mails = :mail' );
            $db->bind( ':mail', $mail );
            $row = $db->single();
            return ( $db->rowCount() ) ? false : true;

        }

        public function Adduser( $arr, $json ) {

            $x = ( $this->checkMail( $json['mail'] ) ) ? true : false;

            if ( $x ) {

                $db = new \db();
                $id = $arr['id'];
                $name = $arr['name'];
                $mail = $json['mail'];
                $pass = password_hash( $json['pass'], PASSWORD_DEFAULT );
                $control = '0';
                $statusJoin = $arr['statusJoin'];
                $topic = $arr['topic'];
                $time = microtime( true )*1000;
                $icon = $arr['icon'];
                $sex = $arr['sex'];
                $privmsg = '0';
                $status = 'act';
                $host = '0';
                $uid = mt_rand( 0, 9999999 ).mt_rand( 0, 999999 );
                $db->query( "INSERT INTO inc_users(userid, username, mails, passwords, control, statusJoin, topic, insertDate, icon, sex,host,status,startup,privmsg, uid)VALUES('$id', '$name', '$mail', '$pass', '$control', '$statusJoin', '$topic', '$time', '$icon', '$sex','$host','$status','$time','$privmsg','$uid')" );

                if ( $db->execute() ) {

                    return 1;

                }
            }

            return 0;

        }

        public function controlCheck( $val ) {

            switch ( $val ) {
                case '0':
                return 0;
                case 'v':
                return 1;
                case 'h':
                return 2;
                case 'o':
                return 3;
                case 'a':
                return 4;
                case 'q':
                return 5;
            }

        }

        public function change( $str, $val, $id, $nick ) {
            $db = new \db();

            switch( $str ) {

                case 'topic':
                $db->query( 'UPDATE pages_rooms SET topic=:val,setby=:nick WHERE identify = :id' );

                $db->bind( ':val', $val );
                $db->bind( ':id', $id );
                $db->bind( ':nick', $nick );
                $db->execute();
                break;

                case 'userTopic':

                $db->query( 'UPDATE inc_users SET topic=:val WHERE username = :id' );

                $db->bind( ':id', $id );
                $db->bind( ':val', $val );
                $db->execute();

                break;

                case 'status':

                $db->query( 'UPDATE inc_users SET status=:val WHERE username = :id' );

                $db->bind( ':id', $id );
                $db->bind( ':val', $val );
                $db->execute();

                break;

                case 'icon':

                $db->query( 'UPDATE inc_users SET icon=:val WHERE username = :id' );

                $db->bind( ':id', $id );
                $db->bind( ':val', $val );
                $db->execute();

                break;

                case 'control':

                break;

            }

        }
        public function modes( $event, $chan, $arr = 0 ) {
            $db = new \db();

            switch( $event ) {
                case 'get':
                $db->query( 'select opt from pages_rooms where identify = :chan' );
                $db->bind( ':chan', $chan );
                $row = $db->single();
                return $row; 
                break;
            }
        }
        public function Banlists( $event, $chan, $ip, $arr = 0 ) {
            $db = new \db();

            switch( $event ) {
                case 'set':
                $ip = $arr['ip'];
                $nick = $arr['nick'] ;
                $by = $arr['by'] ;
                $dates = $arr['date'];
                $min = (INT)$arr['min'];
                $db->query( "INSERT INTO banlist(chan, ip, nick, bys, datess, min)VALUES('$chan','$ip','$nick','$by','$dates','$min')" );
                $db->execute();
                break;

                case 'del':
                $db->query( 'DELETE from banlist where ip = :ip and chan = :chan' );
                $db->bind( ':ip', $ip );
                $db->bind( ':chan', $chan );
                $db->execute();

                break;

                case 'get':

                $db->query( 'select * from banlist where chan = :chan' );
                $db->bind( ':chan', $chan );
                $row = $db->resultset();
                return $row;

                break;

                case 'check':

                $db->query( 'select * from banlist where ip = :ip and chan = :chan' );
                $db->bind( ':ip', $ip );
                $db->bind( ':chan', $chan );

                $row = $db->single();
                return $db->rowCount();

                break;
            }
        }

        public function chans( $t, $name ) {

            $database = new \db();
            $database->query( 'select * from pages_rooms where identify= :name' );
            $database->bind( ':name', $name );
            $row = $database->single();
            return $row[$t];

        }

        public function isAuto( $nick, $chan ) {

            $database = new \db();
            $database->query( 'select * from auto where nick= :nick and chan= :chan' );
            $database->bind( ':chan', $chan );
            $database->bind( ':nick', $nick );
            $row = $database->single();
            return $row ? $row['hammer'] : '0';

        }

        public function onError( ConnectionInterface $conn, \Exception $e ) {

            echo "An error has occurred: {$e->getMessage()}\n";
            // $conn->close();
        }
    }
