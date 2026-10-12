<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendApi.php';

//Verbindet Tvheadend mit Symcon: HTTP-Abfragen der JSON-API und Live-Meldungen über den WebSocket des Servers
//(/comet/ws). Den WebSocket spricht das Gateway selbst über einen Client Socket (TCP). Der WS-Client von Symcon ist
//hier ungeeignet: Er sendet etwa jede Minute einen Ping, und der Tvheadend antwortet darauf nicht und sendet danach
//nichts mehr.
class TvheadendGateway extends IPSModuleStrict
{
    //Der Server sendet mindestens alle 15 Sekunden (diskspaceUpdate). Kommt länger nichts an, steht die Verbindung still.
    private const SILENCE_LIMIT = 60;
    //Der Handshake muss in dieser Zeit (Sekunden) abgeschlossen sein.
    private const HANDSHAKE_LIMIT = 20;

    private const STATE_CLOSED = 'closed';
    private const STATE_HANDSHAKE = 'handshake';
    private const STATE_OPEN = 'open';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyInteger('Port', 9981);
        $this->RegisterPropertyBoolean('UseSSL', false);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('SyncInterval', 300);

        //Merkt sich Version, Fähigkeiten und Rechte des Servers aus der letzten Abfrage.
        $this->RegisterAttributeString('ServerInfo', '{}');

        $this->RegisterTimer('SyncTimer', 0, 'TVHG_Sync($_IPS[\'TARGET\']);');
        $this->RegisterTimer('Watchdog', 0, 'TVHG_CheckConnection($_IPS[\'TARGET\']);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        //Die Verbindung zum Client Socket richtet die Verwaltungskonsole über GetCompatibleParents() ein.
        $parentID = $this->GetParentID();
        if ($parentID > 0) {
            $this->RegisterMessage($parentID, IM_CHANGESTATUS);
        }

        if ($this->ReadPropertyString('Host') === '') {
            $this->SetTimerInterval('SyncTimer', 0);
            $this->SetTimerInterval('Watchdog', 0);
            $this->SetStatus(TVH_STATUS_HOST_MISSING);
            return;
        }

        $this->SetTimerInterval('SyncTimer', max(30, $this->ReadPropertyInteger('SyncInterval')) * 1000);
        $this->SetTimerInterval('Watchdog', 15000);
        $this->UpdateParentConfiguration($parentID);

        //Nach dem Laden des Moduls ist der Zustand der Verbindung unbekannt: sauber neu aufbauen.
        if ($parentID > 0 && IPS_GetInstance($parentID)['InstanceStatus'] === IS_ACTIVE && $this->GetState() !== self::STATE_OPEN) {
            $this->ReconnectParent();
        }
        $this->Sync();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELMESSAGE && $Data[0] === KR_READY) {
            $this->ApplyChanges();
            return;
        }
        if ($Message === IM_CHANGESTATUS && $SenderID === $this->GetParentID()) {
            $this->SendDebug('Socket status', (string) $Data[0], 0);
            if ($Data[0] === IS_ACTIVE) {
                $this->StartHandshake();
            } else {
                $this->SetState(self::STATE_CLOSED);
            }
        }
    }

    public function GetCompatibleParents(): string
    {
        return json_encode([
            'type'      => 'connect',
            'moduleIDs' => [TVH_SOCKET_MODULE_ID]
        ]);
    }

    public function GetConfigurationForParent(): string
    {
        $host = $this->ReadPropertyString('Host');
        return json_encode([
            'Open'       => $host !== '',
            'Host'       => $host,
            'Port'       => $this->ReadPropertyInteger('Port'),
            'UseSSL'     => $this->ReadPropertyBoolean('UseSSL'),
            'VerifyPeer' => false,
            'VerifyHost' => false
        ]);
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $info = json_decode($this->ReadAttributeString('ServerInfo'), true) ?: [];
        $text = $this->Translate('Not connected');
        if (isset($info['version'])) {
            $text = $this->Translate('Server') . ': ' . ($info['name'] ?? 'Tvheadend') . ' ' . $info['version']
                . ' | ' . $this->Translate('Administrator') . ': ' . $this->Translate(($info['admin'] ?? false) ? 'Yes' : 'No');
        }
        foreach ($form['actions'] as &$element) {
            if (($element['name'] ?? '') === 'ServerInfo') {
                $element['caption'] = $text;
            }
        }
        return json_encode($form);
    }

    //Fragt Version, Rechte und Fähigkeiten ab und stößt bei den Kindern einen Vollabgleich an.
    public function Sync(): void
    {
        if ($this->ReadPropertyString('Host') === '') {
            return;
        }

        $serverinfo = $this->ApiRequest('api/serverinfo');
        if ($serverinfo['code'] === 401 || $serverinfo['code'] === 403) {
            $this->SetStatus(TVH_STATUS_AUTH_FAILED);
            return;
        }
        if ($serverinfo['code'] !== 200 || !is_array($serverinfo['data'])) {
            $this->SetStatus(TVH_STATUS_UNREACHABLE);
            $this->SendDebug(__FUNCTION__, 'serverinfo: ' . $serverinfo['error'], 0);
            $this->SendToChildren(['Action' => TVH_ACTION_SYNC, 'Online' => false]);
            return;
        }

        $info = [
            'version'    => (string) ($serverinfo['data']['sw_version'] ?? ''),
            'name'       => (string) ($serverinfo['data']['name'] ?? 'Tvheadend'),
            'apiVersion' => (int) ($serverinfo['data']['api_version'] ?? 0),
            'admin'      => false
        ];
        //Die Rechteprüfung hängt von der Version ab, deshalb zuerst die Version speichern.
        $this->WriteAttributeString('ServerInfo', json_encode($info));
        $info['admin'] = $this->DetectAdmin();
        $capabilities = $this->ApiRequest('api/config/capabilities');
        if ($capabilities['code'] === 200 && is_array($capabilities['data'])) {
            $info['capabilities'] = $capabilities['data'];
        }
        $this->WriteAttributeString('ServerInfo', json_encode($info));
        $this->SendDebug(__FUNCTION__, json_encode($info), 0);

        $this->SetStatus(IS_ACTIVE);
        $this->SendToChildren(['Action' => TVH_ACTION_SYNC, 'Online' => true]);
    }

    //Prüft die WebSocket-Verbindung und baut sie neu auf, wenn der Handshake hängt oder nichts mehr ankommt.
    public function CheckConnection(): void
    {
        $parentID = $this->GetParentID();
        if ($parentID <= 0 || $this->ReadPropertyString('Host') === '' || IPS_GetInstance($parentID)['InstanceStatus'] !== IS_ACTIVE) {
            return;
        }
        $age = time() - (int) $this->GetBuffer('StateSince');
        switch ($this->GetState()) {
            case self::STATE_OPEN:
                $silence = time() - (int) $this->GetBuffer('LastReceive');
                if ($silence >= self::SILENCE_LIMIT) {
                    $this->SendDebug(__FUNCTION__, 'No WebSocket data for ' . $silence . ' seconds, reconnecting', 0);
                    $this->ReconnectParent();
                }
                break;
            case self::STATE_HANDSHAKE:
                if ($age >= self::HANDSHAKE_LIMIT) {
                    $this->SendDebug(__FUNCTION__, 'Handshake did not finish, reconnecting', 0);
                    $this->ReconnectParent();
                }
                break;
            default:
                //Der Socket ist offen, aber der WebSocket nicht aufgebaut.
                if ($age >= self::HANDSHAKE_LIMIT) {
                    $this->ReconnectParent();
                }
        }
    }

    //Ruft eine Funktion der Tvheadend-API auf und gibt die Antwort als JSON zurück (code, data, error).
    public function Request(string $Path, string $ParamsJSON = '{}', string $Method = 'GET'): string
    {
        $params = json_decode($ParamsJSON, true);
        return json_encode($this->ApiRequest($Path, is_array($params) ? $params : [], strtoupper($Method)));
    }

    //Wird von den Kindern aufgerufen (SendDataToParent).
    public function ForwardData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        switch ($data['Action'] ?? '') {
            case TVH_ACTION_REQUEST:
                $result = $this->ApiRequest((string) $data['Path'], (array) ($data['Params'] ?? []), (string) ($data['Method'] ?? 'GET'));
                $this->SendDebug('Request', ($data['Method'] ?? 'GET') . ' ' . $data['Path'] . ' => ' . $result['code'], 0);
                return json_encode($result);
            case TVH_ACTION_INFO:
                return $this->ReadAttributeString('ServerInfo');
            case TVH_ACTION_RAW:
                $raw = $this->Api()->requestRaw((string) $data['Path']);
                $raw['body'] = base64_encode($raw['body']);
                return json_encode($raw);
        }
        return json_encode(['code' => 0, 'data' => null, 'error' => 'Unknown action']);
    }

    //Empfängt die Bytes des Client Sockets: erst die Antwort auf den Handshake, danach WebSocket-Frames.
    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if (!is_array($data) || !isset($data['Buffer'])) {
            return '';
        }
        $this->SetBuffer('LastReceive', (string) time());
        $rx = $this->HexToBytes($this->GetBuffer('Rx')) . $this->BufferToBytes((string) $data['Buffer']);

        if ($this->GetState() === self::STATE_HANDSHAKE) {
            $end = strpos($rx, "\r\n\r\n");
            if ($end === false) {
                $this->SetBuffer('Rx', bin2hex($rx));
                return '';
            }
            $header = substr($rx, 0, $end);
            $rx = substr($rx, $end + 4);
            if (preg_match('#^HTTP/1\.[01] 101#', $header) !== 1) {
                $this->SendDebug('Handshake failed', strtok($header, "\r\n"), 0);
                $this->SetBuffer('Rx', '');
                $this->SetState(self::STATE_CLOSED);
                $this->SetStatus(strpos($header, ' 401 ') !== false ? TVH_STATUS_AUTH_FAILED : TVH_STATUS_UNREACHABLE);
                return '';
            }
            $this->SendDebug('Handshake', 'WebSocket open', 0);
            $this->SetState(self::STATE_OPEN);
            //Die Kinder holen nach dem Verbindungsaufbau alles neu, damit nichts aus der Zwischenzeit fehlt.
            $this->RegisterOnceTimer('SyncAfterConnect', 'TVHG_Sync($_IPS[\'TARGET\']);');
        }

        if ($this->GetState() === self::STATE_OPEN) {
            $this->ParseFrames($rx);
        }
        $this->SetBuffer('Rx', bin2hex($rx));
        return '';
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Sync':
                $this->Sync();
                break;
        }
    }

    private function ApiRequest(string $Path, array $Params = [], string $Method = 'GET'): array
    {
        return $this->Api()->request($Path, $Params, $Method);
    }

    private function Api(): TvheadendApi
    {
        return new TvheadendApi(
            $this->ReadPropertyString('Host'),
            $this->ReadPropertyInteger('Port'),
            $this->ReadPropertyBoolean('UseSSL'),
            $this->ReadPropertyString('Username'),
            $this->ReadPropertyString('Password')
        );
    }

    private function SendToChildren(array $Payload): void
    {
        $Payload['DataID'] = TVH_DATA_ID;
        $this->SendDataToChildren(json_encode($Payload));
    }

    //Sendet die Anfrage zum Wechsel auf WebSocket. Der Server schickt danach sofort accessUpdate und alle 15 Sekunden
    //diskspaceUpdate. Er verlangt das Unterprotokoll tvheadend-comet.
    private function StartHandshake(): void
    {
        $host = $this->ReadPropertyString('Host');
        if ($host === '') {
            return;
        }
        $auth = base64_encode($this->ReadPropertyString('Username') . ':' . $this->ReadPropertyString('Password'));
        $request = "GET /comet/ws HTTP/1.1\r\n"
            . 'Host: ' . $host . ':' . $this->ReadPropertyInteger('Port') . "\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . 'Sec-WebSocket-Key: ' . base64_encode(random_bytes(16)) . "\r\n"
            . "Sec-WebSocket-Version: 13\r\n"
            . "Sec-WebSocket-Protocol: tvheadend-comet\r\n"
            . 'Authorization: Basic ' . $auth . "\r\n\r\n";
        $this->SetBuffer('Rx', '');
        $this->SetBuffer('Message', '');
        $this->SetBuffer('LastReceive', (string) time());
        $this->SetState(self::STATE_HANDSHAKE);
        $this->SendBytes($request);
    }

    //Zerlegt die empfangenen Bytes in WebSocket-Frames (RFC 6455). $Rx behält, was noch unvollständig ist.
    private function ParseFrames(string &$Rx): void
    {
        while (strlen($Rx) >= 2) {
            $byte0 = ord($Rx[0]);
            $byte1 = ord($Rx[1]);
            $fin = ($byte0 & 0x80) !== 0;
            $opcode = $byte0 & 0x0F;
            $masked = ($byte1 & 0x80) !== 0;
            $length = $byte1 & 0x7F;
            $offset = 2;
            if ($length === 126) {
                if (strlen($Rx) < 4) {
                    return;
                }
                $length = (int) unpack('n', substr($Rx, 2, 2))[1];
                $offset = 4;
            } elseif ($length === 127) {
                if (strlen($Rx) < 10) {
                    return;
                }
                $length = (int) unpack('J', substr($Rx, 2, 8))[1];
                $offset = 10;
            }
            $maskKey = '';
            if ($masked) {
                $maskKey = substr($Rx, $offset, 4);
                $offset += 4;
            }
            if (strlen($Rx) < $offset + $length) {
                return;
            }
            $payload = substr($Rx, $offset, $length);
            $Rx = substr($Rx, $offset + $length);
            if ($masked) {
                for ($i = 0; $i < $length; $i++) {
                    $payload[$i] = $payload[$i] ^ $maskKey[$i % 4];
                }
            }

            switch ($opcode) {
                case 0x0: //Fortsetzung
                case 0x1: //Text
                case 0x2: //Binär
                    $message = $this->GetBuffer('Message') . $payload;
                    if ($fin) {
                        $this->SetBuffer('Message', '');
                        $this->HandleMessage($message);
                    } else {
                        $this->SetBuffer('Message', $message);
                    }
                    break;
                case 0x8: //Close
                    $this->SendDebug('WebSocket', 'Server closed the connection', 0);
                    $this->SetState(self::STATE_CLOSED);
                    return;
                case 0x9: //Ping: mit Pong antworten (Frames vom Client müssen maskiert sein)
                    $this->SendBytes($this->BuildFrame(0xA, $payload));
                    break;
            }
        }
    }

    //Eine vollständige Nachricht: Tvheadend sendet JSON mit einem Feld "messages". Jede Meldung geht an die Kinder.
    private function HandleMessage(string $Text): void
    {
        $this->SendDebug('WebSocket', $Text, 0);
        $json = json_decode($Text, true);
        if (!is_array($json) || !isset($json['messages']) || !is_array($json['messages'])) {
            return;
        }
        foreach ($json['messages'] as $message) {
            if (!is_array($message) || !isset($message['notificationClass'])) {
                continue;
            }
            $this->SendToChildren([
                'Action'  => TVH_ACTION_NOTIFY,
                'Class'   => (string) $message['notificationClass'],
                'Message' => $message
            ]);
        }
    }

    //Ein maskierter Frame vom Client (für Pong).
    private function BuildFrame(int $Opcode, string $Payload): string
    {
        $length = strlen($Payload);
        $frame = chr(0x80 | $Opcode);
        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } else {
            $frame .= chr(0x80 | 126) . pack('n', $length);
        }
        $mask = random_bytes(4);
        for ($i = 0; $i < $length; $i++) {
            $Payload[$i] = $Payload[$i] ^ $mask[$i % 4];
        }
        return $frame . $mask . $Payload;
    }

    private function SendBytes(string $Bytes): void
    {
        $this->SendDataToParent(json_encode(['DataID' => TVH_WSC_SEND_ID, 'Buffer' => bin2hex($Bytes)]));
    }

    //Schließt den Socket und öffnet ihn neu. Die Zustandsmeldungen des Sockets lösen danach den Handshake aus.
    private function ReconnectParent(): void
    {
        $parentID = $this->GetParentID();
        if ($parentID <= 0) {
            return;
        }
        $this->SetState(self::STATE_CLOSED);
        IPS_SetProperty($parentID, 'Open', false);
        IPS_ApplyChanges($parentID);
        IPS_Sleep(1000);
        IPS_SetProperty($parentID, 'Open', true);
        IPS_ApplyChanges($parentID);
    }

    private function GetState(): string
    {
        $state = $this->GetBuffer('WsState');
        return $state === '' ? self::STATE_CLOSED : $state;
    }

    private function SetState(string $State): void
    {
        $this->SetBuffer('WsState', $State);
        $this->SetBuffer('StateSince', (string) time());
        if ($State === self::STATE_CLOSED) {
            $this->SetBuffer('Rx', '');
            $this->SetBuffer('Message', '');
        }
    }

    //Ab Tvheadend 4.3-2740 sagt access/whoami, ob der Benutzer Administrator ist. Bei älteren Servern
    //reicht ein Zugriff auf eine Funktion, die nur Administratoren sehen.
    private function DetectAdmin(): bool
    {
        if ($this->ServerBuild() >= 2740) {
            $whoami = $this->ApiRequest('api/access/whoami');
            if ($whoami['code'] === 200 && is_array($whoami['data']) && isset($whoami['data']['admin'])) {
                return (bool) $whoami['data']['admin'];
            }
        }
        return $this->ApiRequest('api/status/connections')['code'] === 200;
    }

    //Build-Nummer aus der Version ("4.3-2685~gfb53138eb" ergibt 2685), 0 wenn sie nicht erkennbar ist.
    //Ältere Server kennen manche Funktionen nicht und würden den Aufruf mit 404 im eigenen Log vermerken.
    private function ServerBuild(): int
    {
        $info = json_decode($this->ReadAttributeString('ServerInfo'), true) ?: [];
        return preg_match('/^\d+\.\d+-(\d+)/', (string) ($info['version'] ?? ''), $match) ? (int) $match[1] : 0;
    }

    //Hält Host, Port und SSL des Client Sockets passend zur Gateway-Konfiguration. Die Verwaltungskonsole setzt sie
    //nur beim Verbinden, spätere Änderungen von Host oder Port übernimmt das Gateway selbst.
    private function UpdateParentConfiguration(int $ParentID): void
    {
        if ($ParentID <= 0 || IPS_GetInstance($ParentID)['ModuleInfo']['ModuleID'] !== TVH_SOCKET_MODULE_ID) {
            return;
        }
        $wanted = json_decode($this->GetConfigurationForParent(), true);
        $current = json_decode(IPS_GetConfiguration($ParentID), true);
        $changed = false;
        foreach ($wanted as $name => $value) {
            if (($current[$name] ?? null) !== $value) {
                IPS_SetProperty($ParentID, $name, $value);
                $changed = true;
            }
        }
        if ($changed) {
            $this->SendDebug(__FUNCTION__, 'Client socket configuration updated', 0);
            IPS_ApplyChanges($ParentID);
        }
    }

    private function GetParentID(): int
    {
        return (int) (IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0);
    }

    //IPSModuleStrict liefert die Nutzdaten des Sockets HEX-kodiert.
    private function BufferToBytes(string $Buffer): string
    {
        if ($Buffer !== '' && strlen($Buffer) % 2 === 0 && ctype_xdigit($Buffer)) {
            $decoded = hex2bin($Buffer);
            return $decoded === false ? $Buffer : $decoded;
        }
        return $Buffer;
    }

    private function HexToBytes(string $Hex): string
    {
        $bytes = $Hex === '' ? '' : hex2bin($Hex);
        return $bytes === false ? '' : $bytes;
    }
}
