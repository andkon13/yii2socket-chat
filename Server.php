<?php
/**
 * Created by PhpStorm.
 * User: andkon
 * Date: 19.07.16
 * Time: 12:42
 */

namespace andkon\yii2SocketChat;

use React\Socket\Connection;

/**
 * Class Server
 *
 * @package andkon\yii2SocketChat
 */
class Server
{
    /**
     *
     */
    const EVENT_ON_CONNECT = 'SocketChatOnConnect';
    /**
     *
     */
    const EVENT_ON_MESSAGE = 'SocketChatOnMessage';
    /**
     *
     */
    const EVENT_ON_CLOSED = 'SocketChatOnClosed';
    /**
     *
     */
    const EVENT_ON_EOF = 'SocketChatOnEof';
    /**
     *
     */
    const EVENT_ON_ERROR = 'SocketChatOnError';
    /**
     * @var array
     */
    static public $roomByShop = [];
    /** @var Connection[] */
    static protected $connectsByRoom = [];
    /** @var Connection[] */
    static protected $connectsByUser = [];
    /** @var Server */
    protected static $instance;
    /**
     * @var int
     */
    public $port = 1337;
    public $listen_host = '0.0.0.0';
    /**
     * @var \React\EventLoop\ExtEventLoop|\React\EventLoop\LibEventLoop|\React\EventLoop\LibEvLoop|\React\EventLoop\StreamSelectLoop
     */
    protected $loop;

    /**
     * Server constructor.
     */
    public function __construct()
    {
        $this->loop = \React\EventLoop\Factory::create();
        $socket     = new \React\Socket\Server($this->loop);
        $socket->on('connection', function (Connection $conn) {
            usleep(500);
            $info = $this->handshake($conn);
            echo print_r($info, true);
            $room = $this->registryConnect($conn, $info);
            \Yii::$app->trigger(self::EVENT_ON_CONNECT, (new Event(['connect' => $conn, 'context' => $room])));
            $conn->on('data', function (string $data) use ($conn) {
                $data = $this->decode($data);
                $data = json_decode($data['payload'], true);
                echo print_r($data, true);
                \Yii::$app->trigger(self::EVENT_ON_MESSAGE, (new Event(['message' => $data, 'connect' => $conn])));
            });
            $conn->on('close', function (Connection $conn) {
                \Yii::$app->trigger(self::EVENT_ON_CLOSED, (new Event(['connect' => $conn])));
            });
            $conn->on('end', function (Connection $conn) {
                \Yii::$app->trigger(self::EVENT_ON_EOF, (new Event(['connect' => $conn])));
            });
            $conn->on('error', function (Connection $conn) {
                \Yii::$app->trigger(self::EVENT_ON_ERROR, (new Event(['connect' => $conn])));
            });
        });

        $socket->listen($this->port, $this->listen_host);
        self::$instance = $this;
    }

    /**
     * @param Connection $connect
     *
     * @return array|bool
     */
    public function handshake(Connection $connect)
    {
        $info           = array();
        $stream         = $connect->getBuffer()->stream;
        $line           = fgets($stream);
        $header         = explode(' ', $line);
        $info['method'] = $header[0];
        $info['room']   = $header[1] ?? null;
        $info['room']   = ($info['room']) ? substr($info['room'], 1) : null;
        $info['uri']    = $connect->getRemoteAddress();

        //считываем заголовки из соединения
        while ($line = rtrim(fgets($stream))) {
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $info[$matches[1]] = $matches[2];
            } else {
                break;
            }
        }
        $address      = explode(':', stream_socket_get_name($stream, true)); //получаем адрес клиента
        $info['ip']   = $address[0];
        $info['port'] = $address[1];

        if (empty($info['Sec-WebSocket-Key'])) {
            return false;
        }

        //отправляем заголовок согласно протоколу вебсокета
        $SecWebSocketAccept = base64_encode(pack('H*', sha1($info['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade            = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept:$SecWebSocketAccept\r\n\r\n";
        $connect->write($upgrade);

        return $info;
    }

    /**
     * @param Connection $conn
     * @param            $info
     *
     * @return bool|null|Room|static
     */
    protected function registryConnect($conn, $info)
    {
        if (!$info) {
            $info['room'] = self::getConnectRoom($conn);
        }
        $room = Room::findById($info['room']);
        if (!$room) {
            return false;
        }

        if (!$room->isShop) {
            $userId = ($room->user_id) ? $room->user_id : $room->id;
            if (isset(self::$connectsByUser[$userId])) {
                self::$connectsByUser[$userId]->close();
            }
            self::$connectsByUser[$userId] = $conn;
            if (!array_key_exists($room->id, self::$connectsByRoom)) {
                self::$connectsByRoom[$room->id] = ['user' => null, 'seller' => null];
            }

            self::$connectsByRoom[$room->id]['user']   = &self::$connectsByUser[$userId];
            self::$connectsByRoom[$room->id]['seller'] = &self::$connectsByUser[$room->seller_id] ?? null;
            $room->clientConnectId                     = $userId;
            $room->save();
            if (!array_key_exists($room->shop_id, self::$roomByShop)) {
                self::$roomByShop[$room->shop_id] = [$room->id];
            } elseif (!in_array($room->id, self::$roomByShop)) {
                self::$roomByShop[$room->shop_id][] = $room->id;
            }
        } else {
            if (isset(self::$connectsByUser[$room->seller_id])) {
                self::$connectsByUser[$room->seller_id]->close();
            }
            self::$connectsByUser[$room->seller_id] = $conn;
            foreach ($room->shop_id as $shop_id) {
                if (array_key_exists($shop_id, self::$roomByShop)) {
                    foreach (self::$roomByShop[$shop_id] as $item) {
                        self::$connectsByRoom[$item]['seller'] = &self::$connectsByUser[$room->seller_id];
                    }
                }
            }
        }

        return $room;
    }

    /**
     * @param Connection $conn
     *
     * @return string
     */
    public static function getConnectRoom(Connection $conn)
    {
        $stream = $conn->getBuffer()->stream;
        $line   = fgets($stream);
        $header = explode(' ', $line);
        $id     = $header[1] ?? null;
        $id     = ($id) ? substr($id, 1) : null;

        return $id;
    }

    /**
     * @param $data
     *
     * @return array|bool
     */
    protected function decode($data)
    {
        $unmaskedPayload = '';
        $decodedData     = array();

        // estimate frame type:
        $firstByteBinary  = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode           = bindec(substr($firstByteBinary, 4, 4));
        $isMasked         = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength    = ord($data[1]) & 127;

        // unmasked frame is received:
        if (!$isMasked) {
            return array('type' => '', 'payload' => '', 'error' => 'protocol error (1002)');
        }

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;

            case 2:
                $decodedData['type'] = 'binary';
                break;

            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;

            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;

            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;

            default:
                return array('type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)');
        }

        if ($payloadLength === 126) {
            $mask          = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength    = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            $mask          = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp           = '';
            for ($i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        } else {
            $mask          = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength    = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if (strlen($data) < $dataLength) {
            return false;
        }

        if ($isMasked) {
            for ($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if (isset($data[$i])) {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset          = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }

    /**
     * @param $connect
     * @param $message
     *
     * @return bool|mixed
     */
    public static function send($connect, $message)
    {
        if (!$connect instanceof Connection) {
            $connect = self::getInstance()->getConnectById($connect);
        }

        if ($connect) {
            return self::getInstance()->write($message, $connect);
        }

        return false;
    }

    /**
     * @return Server
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string     $message
     * @param Connection $conn
     *
     * @return mixed
     */
    public static function write($message, $conn)
    {
        if ($conn->isWritable()) {
            $conn->write(
                self::getInstance()->encode($message)
            );

            return true;
        }

        return false;
    }

    /**
     * @param        $payload
     * @param string $type
     * @param bool   $masked
     *
     * @return string
     */
    protected function encode($payload, $type = 'text', $masked = false)
    {
        $frameHead     = array();
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1]     = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0
            if ($frameHead[2] > 127) {
                return array('type' => '', 'payload' => '', 'error' => 'frame too large (1004)');
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1]     = ($masked === true) ? 254 : 126;
            $frameHead[2]     = bindec($payloadLengthBin[0]);
            $frameHead[3]     = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if ($masked === true) {
            // generate a random mask:
            $mask = array();
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    /**
     * @param $userId
     *
     * @return Connection|null
     */
    public static function getConnectByUser($userId)
    {
        return self::$connectsByUser[$userId] ?? null;
    }

    /**
     *
     */
    public function start()
    {
        $this->loop->run();
    }

    public static function clearConnects()
    {
        self::$connectsByUser = array_filter(self::$connectsByUser, function (Connection $conn) {
            return $conn->isWritable();
        });
    }
}
