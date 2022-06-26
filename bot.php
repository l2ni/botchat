<?php

class bot {

    public $name;
    public $active;

    public function __construct() {
        $this->name = 'Power';
        $this->active = array();
    }

    public function Repeats( $from, $room, $msg ) {

        if ( $from->__get( 'text'.$room ) == $msg ) {
            if ( $from->__get( 'ban'.$room ) == 1 ) {
                $from->__set( 'text'.$room, '' );
                $from->__set( 'ban'.$room, '' );
                return true;

            } else {
                $from->__set( 'ban'.$room, 1 );

            }
        } else {
            $from->__set( 'ban'.$room, 0 );

        }
        $from->__set( 'text'.$room, $msg );
        return false;

    }

    public function flood( $from, $room ) {
        $flood = ( int ) $from->__get( 'flood'.$room );
        if ( $flood == 0 ) {
            $from->__set( 'flood_time'.$room, time() );
        }

        if ( $flood == 5 ) {
            $gt = $from->__get( 'flood_time'.$room );
            $from->__set( 'flood_time'.$room, time() );
            $from->__set( 'flood'.$room, 0 );
            echo time() - $gt.PHP_EOL;
            if ( time() - $gt <= 10 ) return true;

        } else {
            $from->__set( 'flood'.$room, ( int )$flood+1 );
        }

        return false;

    }

    public function say( $from, $room, $msg, $users ) {

        if ( $this->Repeats( $from, $room, $msg ) ) {
            return array( 'ban' => 'yes', 'min' => 1, 'type' => 'Repeats' );
        }
        if ( $this->flood( $from, $room ) ) {
            return array( 'ban' => 'yes', 'min' => 5, 'type' => 'flood' );
        }

        if ( $this->filtter_phone( $msg ) ) {
            return array( 'ban' => 'yes', 'min' => 15, 'type' => 'phone' );
        }

        if ( $this->filtter_mail( $msg ) ) {
            return array( 'ban' => 'yes', 'min' => 15, 'type' => 'mail' );
        }

        if ( $this->badword( $msg ) ) {
            $this->kick( $from, $room, 'لقد قمت بقول لفظ خارج - ممنوع من الدخول لمدة ساعه', $users );
            return array( 'ban' => 'yes', 'min' => 60, 'type' => 'kick' );
        }

        $v = ( INT )$from->__get( 'voice'.$room );

        if ( $v == 50 ) {
            $from->__set( 'devoice'.$room, time() );
            $this->addmsg( $from, 'voice', $room, $users );
            $from->__set( 'voice'.$room, $v+1 );

            $h = $from->__get( $room.'_hammer' );
            if ( $h == '0' ) {
                $from->__set( $room.'_hammer', 'v' );
                $this->setmode( $room, $users, $this->name, $from->__get( 'nick' ), 'v' );
            }

        } else {
            if ( $v < 50 ) {
                $from->__set( 'voice'.$room, $v+1 );

            } else {
                $from->__set( 'devoice'.$room, time() );
            }
        }
        return array( 'ban' => 'no' );

    }

    public function kick( $from, $room, $msg, $users ) {

        $arr2[] = array(
            'raw'    => 'kick' ,
            'kicked' => $from->__get( 'nick' ),
            'nick'   => $this->name,
            'chan'   => $room,
            'msg'    => $msg,
            'ip'     => $from->__get( 'ip' )

        );

        $arr[] = array(
            'raw'    => 'nav' ,
            'mode'   => 'kick',
            'nick'   => $this->name,
            'chan'   => $room,
            'msg'    => $msg,
        );

        $from->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );
        $this->users( $users, $room, $arr2 );
        $channel_from = $from->__get( 'active' );
        $from->__set( 'active', str_replace( $room, '', $channel_from ) );

    }

    public function unmode( $users ) {

        foreach ( $users as $client ) {
            $x  = $client->__get( 'active' );
            foreach ( explode( ' ', $x ) as $chan ) {
                if ( $chan ) {
                    if (in_array( $chan, $this->active ) ) {
                        $v = $client->__get( 'devoice'.$chan );
                        $o = $client->__get( 'voice'.$chan );
                        $time = time() - $v;
                        $min = 60 * 5;

                        if ( $time > $min && $o == 51 ) {

                            $client->__set( 'devoice'.$chan, 0 );
                            $client->__set( 'voice'.$chan, 0 );
                            $this->setmode( $chan, $users, $this->name, $client->__get( 'nick' ), '0' );
                            $this->addmsg( $client, 'devoice', $chan, $users );
                        }
                    }
                }
            }
        }
    }

    public function type( $type, $nick ) {
        $msg = '';
        switch( $type ) {

            case 'Repeats':
            $msg = "4 {$nick->__get('nick')} , 1 لقد قمت بتكرار نفس الجملة ثلالث مرات وهذه يعتبر فلود ممنوع عن الكتابه لمدة دققتين";
            break;

            case 'flood':
            $msg = "4 {$nick->__get('nick')} , 12برجاء عدم الكتابة السريعة - ممنوع عن الكتابة لمدة 5 دقائق";
            break;

            case 'voice':
            $msg = "4 {$nick->__get('nick')} ,12 لنشاطك بالغرفة - (Vip) - تم منحك عضوية مميزة";
            break;

            case 'devoice':
            $msg = "4 {$nick->__get('nick')} ,12 تم تنزيلك من العضوية - لعدم نشاطك بالغرفة";
            break;

            case 'phone':
            $msg = "12 {$nick->__get('nick')} ,1 ممنوع تداول ارقام الهواتف على العام - ممنوع لمدة 15 دقيقة";
            break;

            case 'mail':
            $msg = "12 {$nick->__get('nick')} ,1 ممنوع تداول الايميل على العام - ممنوع لمدة 15 دقيقة";
            break;

            case 'unban':
            $msg = "2 Unbanned.. 4 {$nick} يمكنك التحدث الان";
            break;

        }
        return $msg;

    }

    public function filtter_mail( $msg ) {
        $msg = explode( ' ', $msg );
        foreach ( $msg as $word ) {
            $pattern = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';

            $matches = preg_match( $pattern, $word );
            if ( $matches > 0 ) {
                return 1;
            }
        }
        return 0;
    }

    public function badnick( $nick ) {
        if ( $this->openFile( $nick, '/badnicks.txt' ) ) {
            return true;
        }
        return false;
    }

    public function openFile( $msg, $f ) {
        $fh = fopen( __DIR__.$f, 'r' );
        while ( $line = fgets( $fh ) ) {
            if ( preg_replace( '/\s+/', '', $line ) == $msg ) {
                return true;
            }
        }
        fclose( $fh );
        return false;
    }

    public function badword( $msg ) {
        $msg = explode( ' ', $msg );
        foreach ( $msg as $word ) {
            if ( $this->openFile( $word, '/badwords.txt' ) ) {
                return true;
            }
        }

        return false;

    }

    public function filtter_phone( $msg ) {
        $msg = explode( ' ', $msg );
        foreach ( $msg as $word ) {
            $pattern = '/^\d+$/';

            $matches = preg_match( $pattern, $word );
            if ( $matches > 0 && strlen( $word ) == 11 ) {
                $expr = '/(010|011|012|016|017|019|014|015)(\d+)/';
                if ( preg_match( $expr, $word ) == 1 ) {
                    return 1;
                }
            }
        }
        return 0;
    }

    public function addmsg( $nick, $type, $chan, $users ) {
        if ( $type == 'kick' ) return;
        $ob = array(
            'm' => $this->type( $type, $nick ),
            'status' => true,
            'id' => microtime( true )*1000,
            'reply'=> null,
            'e' => false
        );

        $arr[] = array(
            'raw'  => 'msg',
            'nick' => $this->name,
            'chan' => $chan,
            'msg'  => $ob,
        );

        $this->users( $users, $chan, $arr );
    }

    public function setban( $nick, $type, $chan, $users ) {
        $arr[] = array(
            'raw'    => 'setban' ,
            'chan'   => $chan,
            'ip'     => $nick->__get( 'ip' ),
            'nick'   => $nick->__get( 'nick' ),
            'by'     => $this->name,
            'date' => microtime( true )*1000

        );
        $this->users( $users, $chan, $arr );
        $this->addmsg( $nick, $type, $chan, $users );
    }

    public function setmode( $chan, $users, $nick, $controled, $c ) {
        $arr[] = array(
            'raw'    => 'control' ,
            'controled' => $controled,
            'nick'   => $nick,
            'chan'   => $chan,
            'control' => $c,

        );

        $this->users( $users, $chan, $arr );
    }

    public function join( $chan, $users ) {
        array_push( $this->active, $chan );
        $arr[] = array(
            'raw' => 'join' ,
            'nick' => $this->_get(),
            'chan' => $chan
        );

        $this->users( $users, $chan, $arr );
        $this->setmode( $chan, $users, '7kawichat', $this->name, 'b' );
    }

    public function users( $users, $chan, $arr ) {

        foreach ( $users as $client ) {
            $x  = $client->__get( 'active' );
            $xx = explode( ' ', $x );
            $channel_client = ( array_search( $chan, $xx ) ) ? true : false;

            if ( $channel_client ) {
                $client->send( json_encode( array( 'data' => array_reverse( $arr ) ) ) );
            }
        }
    }

    public function part( $chan, $users ) {
        foreach ( $this->active as $key => $value ) {
            if ( $value == $chan ) {
                unset( $this->active[$key] );
                break;
            }
        }
        $arr[] = array(
            'raw' => 'part' ,
            'nick' => $this->name,
            'chan' => $chan
        );

        $this->users( $users, $chan, $arr );

    }

    public function msg( $chan, $msg, $users ) {
        $msg = explode( ' ', $msg );
        if ( array_search( '!join', $msg ) ) {
            if ( !in_array( $chan, $this->active ) ) {
                $this->join( $chan, $users );
            }
        } else if ( array_search( '!part', $msg ) ) {
            $this->part( $chan, $users );

        }
    }

    public function _get() {

        return array(
            'raw'     => 'whois',
            'ip'      =>  '7kawichat.com',
            'name'    =>  $this->name,
            'icon'    =>  'bot/bot.png',
            'topic'   =>  'Strong guard',
            'status'  =>  'act',
            'statusJoin' => '',
            'friends' =>  0,
            'host'     => 'bot.7kawichat.com',
            'control' =>  'b',
            'sex'     =>  'male',
            'register' => 1,
            'cover' =>    'bot/cover.png',
            'active' =>   'Arab',
            'idle' =>     microtime( true )*1000
        );

    }
}