<?php
declare(strict_types=1);

/**
 * Minimaler MQTT 3.1.1 Client
 * - TCP Socket
 * - QoS 0/1
 * - Clean Session
 * - KeepAlive mit PINGREQ/PINGRESP
 *
 * Erwartet eine globale Funktion logln($lvl, $msg) im aufrufenden Script.
 */

class SonosMqttClient
{
    private string $host;
    private int $port;
    private string $clientId;
    private ?string $username;
    private ?string $password;
    private $socket = null;
    private int $keepAlive = 60;
    private int $lastActivity = 0;
    private bool $connected = false;
    private int $packetId = 1;
    private int $reconnectDelay = 1; // Sekunden (wird bis max 10 gesteigert)

    public function __construct(string $host, int $port, string $clientId, ?string $username = null, ?string $password = null)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->clientId = $clientId;
        $this->username = $username ?: null;
        $this->password = $password ?: null;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    private function openSocket(): bool
    {
        $errNo = 0;
        $errStr = '';
        $sock = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errNo,
            $errStr,
            4,
            STREAM_CLIENT_CONNECT
        );
        if (!$sock) {
            logln('warn', "MQTT: Could not connect to {$this->host}:{$this->port} - $errStr ($errNo)");
            $this->socket = null;
            $this->connected = false;
            return false;
        }
        stream_set_timeout($sock, 4);
        stream_set_blocking($sock, true);
        $this->socket = $sock;
        logln('dbg', "MQTT: TCP connection established to {$this->host}:{$this->port}");
        return true;
    }

    private function encodeRemainingLength(int $len): string
    {
        $encoded = '';
        do {
            $digit = $len % 128;
            $len   = intdiv($len, 128);
            if ($len > 0) {
                $digit |= 0x80;
            }
            $encoded .= chr($digit);
        } while ($len > 0);
        return $encoded;
    }

    private function nextPacketId(): int
    {
        $this->packetId++;
        if ($this->packetId > 0xFFFF) {
            $this->packetId = 1;
        }
        return $this->packetId;
    }

    private function sendPacket(string $packet): bool
    {
        if (!$this->socket) {
            return false;
        }
        $written = @fwrite($this->socket, $packet);
        if ($written === false || $written < strlen($packet)) {
            logln('warn', "MQTT: Failed to write packet (" . strlen($packet) . " bytes, wrote=$written)");
            $this->disconnect();
            return false;
        }
        $this->lastActivity = time();
        return true;
    }

    private function readBytes(int $len, int $timeoutSec = 4): ?string
    {
        if (!$this->socket) {
            return null;
        }
        $data = '';
        $start = time();
        while (strlen($data) < $len) {
            $chunk = @fread($this->socket, $len - strlen($data));
            if ($chunk === false || $chunk === '') {
                if ((time() - $start) >= $timeoutSec) {
                    logln('dbg', "MQTT: readBytes timeout ($len bytes)");
                    return null;
                }
                usleep(50000);
                continue;
            }
            $data .= $chunk;
        }
        $this->lastActivity = time();
        return $data;
    }

    private function readRemainingLength(): ?int
    {
        if (!$this->socket) {
            return null;
        }
        $multiplier = 1;
        $value = 0;
        do {
            $digit = @fread($this->socket, 1);
            if ($digit === false || $digit === '') {
                return null;
            }
            $digitVal = ord($digit);
            $value += ($digitVal & 0x7F) * $multiplier;
            $multiplier *= 128;
        } while (($digitVal & 0x80) !== 0);
        return $value;
    }

    private function connectInternal(): bool
    {
        if (!$this->openSocket()) {
            return false;
        }

        // MQTT CONNECT packet (v3.1.1)
        $protocolName   = "MQTT";
        $protocolLevel  = 4; // 3.1.1
        $connectFlags   = 0;
        $keepAlive      = $this->keepAlive;

        if ($this->username !== null) {
            $connectFlags |= 0x80; // User Name Flag
        }
        if ($this->password !== null) {
            $connectFlags |= 0x40; // Password Flag
        }
        $connectFlags |= 0x02; // Clean Session

        $payload  = "";
        // Client ID
        $payload .= pack('n', strlen($this->clientId)) . $this->clientId;
        // Username
        if ($this->username !== null) {
            $payload .= pack('n', strlen($this->username)) . $this->username;
        }
        // Password
        if ($this->password !== null) {
            $payload .= pack('n', strlen($this->password)) . $this->password;
        }

        $variableHeader  = pack('n', strlen($protocolName)) . $protocolName;
        $variableHeader .= chr($protocolLevel);
               $variableHeader .= chr($connectFlags);
        $variableHeader .= pack('n', $keepAlive);

        $remainingLength = strlen($variableHeader) + strlen($payload);
        $fixedHeader = chr(0x10) . $this->encodeRemainingLength($remainingLength);

        $packet = $fixedHeader . $variableHeader . $payload;

        logln('dbg', "MQTT: Sending CONNECT (clientId={$this->clientId}, user=" . ($this->username ?? 'none') . ")");
        if (!$this->sendPacket($packet)) {
            logln('warn', 'MQTT: Failed to send CONNECT packet');
            $this->disconnect();
            return false;
        }

        // Read fixed header (2 bytes minimum)
        $header = $this->readBytes(1);
        if ($header === null) {
            logln('warn', 'MQTT: No CONNACK header received');
            $this->disconnect();
            return false;
        }
        $packetType = ord($header[0]) >> 4;
        if ($packetType !== 2) {
            logln('warn', 'MQTT: Invalid CONNACK packet type: ' . $packetType);
            $this->disconnect();
            return false;
        }
        $remainingLength = $this->readRemainingLength();
        if ($remainingLength === null) {
            logln('warn', 'MQTT: Failed to read CONNACK remaining length');
            $this->disconnect();
            return false;
        }
        $payloadData = $this->readBytes($remainingLength);
        if ($payloadData === null || strlen($payloadData) < 2) {
            logln('warn', 'MQTT: Invalid CONNACK payload');
            $this->disconnect();
            return false;
        }
        $ackFlags   = ord($payloadData[0]); // aktuell uninteressant
        $returnCode = ord($payloadData[1]);

        logln('dbg', "MQTT: CONNACK flags=$ackFlags returnCode=$returnCode");

        if ($returnCode !== 0) {
            logln('warn', "MQTT: CONNACK returned error code $returnCode");
            $this->disconnect();
            return false;
        }

        $this->connected    = true;
        $this->lastActivity = time();
        $this->reconnectDelay = 1;
        logln('ok', 'MQTT: Connected (SonosMqttClient)');
        return true;
    }

    public function connect(): bool
    {
        if ($this->connected && $this->socket) {
            return true;
        }
        logln('info', 'MQTT: Connecting …');
        return $this->connectInternal();
    }

    public function publish(string $topic, string $payload, bool $retain=false, int $qos=0): bool
    {
        if (!$this->connected || !$this->socket) {
            if (!$this->connect()) {
                logln('warn', "MQTT: publish($topic) aborted – not connected");
                return false;
            }
        }

        $qos = ($qos === 1) ? 1 : 0; // wir unterstützen 0 oder 1

        // Fixed header
        $header = 0x30; // PUBLISH, QoS 0, DUP 0, RETAIN 0
        if ($qos === 1) {
            $header |= 0x02; // QoS 1
        }
        if ($retain) {
            $header |= 0x01; // RETAIN flag
        }

        $topicLen   = strlen($topic);
        $varHeader  = pack('n', $topicLen) . $topic;
        $packetId   = null;

        if ($qos === 1) {
            $packetId = $this->nextPacketId();
            $varHeader .= pack('n', $packetId);
        }

        $payloadBin = $payload;
        $remainingLength = strlen($varHeader) + strlen($payloadBin);
        $fixedHeader = chr($header) . $this->encodeRemainingLength($remainingLength);
        $packet = $fixedHeader . $varHeader . $payloadBin;

        logln('dbg', "MQTT: PUBLISH topic=$topic qos=$qos retain=" . ($retain ? '1' : '0') . " len=" . strlen($payloadBin));

        if (!$this->sendPacket($packet)) {
            logln('warn', "MQTT: Failed to send PUBLISH to $topic");
            $this->disconnect();
            return false;
        }

        // QoS 0 → kein ACK notwendig
        if ($qos === 0) {
            return true;
        }

        // QoS 1 → PUBACK abwarten
        $header = $this->readBytes(1, 4);
        if ($header === null) {
            logln('warn', "MQTT: No PUBACK header for topic $topic");
            $this->disconnect();
            return false;
        }
        $packetType = ord($header[0]) >> 4;
        if ($packetType !== 4) {
            logln('warn', "MQTT: Invalid packet type while waiting PUBACK: " . $packetType);
            $this->disconnect();
            return false;
        }
        $remainingLength = $this->readRemainingLength();
        if ($remainingLength !== 2) {
            logln('warn', "MQTT: PUBACK invalid remaining length: $remainingLength");
            $this->disconnect();
            return false;
        }
        $ackPayload = $this->readBytes(2);
        if ($ackPayload === null || strlen($ackPayload) < 2) {
            logln('warn', "MQTT: PUBACK payload read failed");
            $this->disconnect();
            return false;
        }
        $ackPid = unpack('n', $ackPayload)[1];
        if ($ackPid !== $packetId) {
            logln('warn', "MQTT: PUBACK packetId mismatch (sent=$packetId, got=$ackPid)");
            $this->disconnect();
            return false;
        }

        logln('dbg', "MQTT: PUBACK for topic=$topic pid=$packetId");
        return true;
    }

    public function loop(): void
    {
        // KeepAlive Ping
        if ($this->connected && $this->socket) {
            if ((time() - $this->lastActivity) > ($this->keepAlive - 5)) {
                // PINGREQ
                $fixedHeader = chr(0xC0) . chr(0x00); // PINGREQ mit Remaining Length 0
                logln('dbg', 'MQTT: Sending PINGREQ');
                if (!$this->sendPacket($fixedHeader)) {
                    logln('warn', 'MQTT: Failed to send PINGREQ, disconnecting');
                    $this->disconnect();
                    return;
                }
                // PINGRESP erwarten
                $header = $this->readBytes(1, 4);
                if ($header === null) {
                    logln('warn', 'MQTT: No PINGRESP received, disconnecting');
                    $this->disconnect();
                    return;
                }
                $packetType = ord($header[0]) >> 4;
                if ($packetType !== 13) { // PINGRESP
                    logln('warn', "MQTT: Unexpected packet type instead of PINGRESP: $packetType");
                    $this->disconnect();
                    return;
                }
                $len = $this->readRemainingLength();
                if ($len !== 0) {
                    // sollte 0 sein – notfalls ignorieren
                    if ($len !== null && $len > 0) {
                        $this->readBytes($len);
                    }
                }
                logln('dbg', 'MQTT: PINGRESP received');
            }
        } elseif (!$this->connected) {
            // Reconnect Backoff
            static $lastAttempt = 0;
            if ((time() - $lastAttempt) >= $this->reconnectDelay) {
                $lastAttempt = time();
                logln('info', "MQTT: Trying reconnect (delay={$this->reconnectDelay}s) …");
                if ($this->connectInternal()) {
                    logln('ok', 'MQTT: Reconnected');
                } else {
                    $this->reconnectDelay = min(10, $this->reconnectDelay * 2);
                    logln('warn', "MQTT: Reconnect failed, next try in {$this->reconnectDelay}s");
                }
            }
        }
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
        }
        $this->socket    = null;
        $this->connected = false;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
