<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';

class TvheadendEPG extends IPSModuleStrict
{
    use TvheadendChild;

    //Idents der Variablen pro beobachtetem Kanal: Präfix + Kanal-UUID ohne Sonderzeichen.
    private const PREFIX_NOW = 'Now_';
    private const PREFIX_NOW_END = 'NowEnd_';
    private const PREFIX_NEXT = 'Next_';
    private const PREFIX_NEXT_START = 'NextStart_';

    //Mindestabstand zwischen zwei Abfragen, die durch EPG-Meldungen ausgelöst werden (Sekunden).
    private const MIN_NOTIFY_INTERVAL = 20;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('Channels', '[]');
        //Kanalnamen zu den UUIDs, damit die Variablen auch ohne Verbindung benannt werden.
        $this->RegisterAttributeString('ChannelNames', '{}');

        $this->RegisterVariableString('Overview', $this->Translate('On Air Overview'), $this->HtmlPresentation(), 1);

        //Springt zum Ende der laufenden Sendungen, damit "Jetzt" und "Als Nächstes" pünktlich wechseln.
        $this->RegisterTimer('Refresh', 0, 'TVHE_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        if ($this->HasActiveParent()) {
            $this->StoreChannelNames();
        }
        $this->MaintainChannelVariables();
        if ($this->HasActiveParent()) {
            $this->Update();
        }
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $options = [];
        foreach ($this->GetChannelList() as $uuid => $name) {
            $options[] = ['caption' => $name, 'value' => $uuid];
        }
        if (count($options) === 0) {
            $options[] = ['caption' => $this->Translate('No channels available (gateway not connected?)'), 'value' => ''];
        }
        foreach ($form['elements'] as &$element) {
            if (($element['name'] ?? '') === 'Channels') {
                $element['columns'][0]['edit']['options'] = $options;
            }
        }
        return json_encode($form);
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        switch ($data['Action'] ?? '') {
            case TVH_ACTION_SYNC:
                if (($data['Online'] ?? false) === true) {
                    $this->StoreChannelNames();
                    $this->Update();
                }
                break;
            case TVH_ACTION_NOTIFY:
                if (($data['Class'] ?? '') === 'epg') {
                    $last = (int) $this->GetBuffer('LastNotifyUpdate');
                    if (time() - $last >= self::MIN_NOTIFY_INTERVAL) {
                        $this->SetBuffer('LastNotifyUpdate', (string) time());
                        $this->Update();
                    }
                }
                break;
        }
        return '';
    }

    //Liest "Jetzt" und "Als Nächstes" der beobachteten Kanäle neu.
    public function Update(): void
    {
        $names = $this->GetChannelNames();
        $rows = [];
        $earliestEnd = 0;

        foreach ($this->GetWatchedChannels() as $uuid) {
            $key = $this->VariableKey($uuid);
            $now = $this->FindNow($uuid);
            $next = null;
            if ($now !== null && isset($now['nextEventId'])) {
                $next = $this->LoadEvent((int) $now['nextEventId']);
            }

            $nowEnd = (int) ($now['stop'] ?? 0);
            $this->SetValue(self::PREFIX_NOW . $key, (string) ($now['title'] ?? '-'));
            $this->SetValue(self::PREFIX_NOW_END . $key, $nowEnd);
            $this->SetValue(self::PREFIX_NEXT . $key, (string) ($next['title'] ?? '-'));
            $this->SetValue(self::PREFIX_NEXT_START . $key, (int) ($next['start'] ?? 0));

            if ($nowEnd > time() && ($earliestEnd === 0 || $nowEnd < $earliestEnd)) {
                $earliestEnd = $nowEnd;
            }
            $rows[] = [
                $names[$uuid] ?? $uuid,
                ($now['title'] ?? '-') . ($nowEnd > 0 ? ' (' . $this->Translate('until') . ' ' . date('H:i', $nowEnd) . ')' : ''),
                ($next['title'] ?? '-') . (isset($next['start']) ? ' (' . date('H:i', (int) $next['start']) . ')' : '')
            ];
        }
        $this->SetValue('Overview', $this->BuildTable(['Channel', 'On Air', 'Next'], $rows));

        //Zum Ende der nächsten Sendung neu laden, spätestens nach zehn Minuten.
        $interval = $earliestEnd > 0 ? min(600, max(5, $earliestEnd - time() + 2)) : 600;
        $this->SetTimerInterval('Refresh', $interval * 1000);
    }

    //Sucht Sendungen. Die Parameter von epg/events/grid (zum Beispiel channel, limit, durationMin) kommen als
    //JSON, die Antwort ist die Liste der Sendungen als JSON.
    public function Search(string $Title, string $ParamsJSON = '{}'): string
    {
        $params = json_decode($ParamsJSON, true);
        $params = is_array($params) ? $params : [];
        $params['title'] = $Title;
        if (isset($params['channel'])) {
            $params['channel'] = $this->ResolveChannel((string) $params['channel']);
        }
        $params += ['limit' => 50];
        $result = $this->ApiRequest('api/epg/events/grid', $params);
        return json_encode($result['code'] === 200 ? ($result['data']['entries'] ?? []) : []);
    }

    //Die laufende Sendung eines Kanals (Name oder UUID) als JSON, leer ({}), wenn nichts gefunden wurde.
    public function GetNow(string $Channel): string
    {
        $uuid = $this->ResolveChannel($Channel);
        return json_encode($uuid === '' ? new stdClass() : ($this->FindNow($uuid) ?? new stdClass()));
    }

    //Stream-Adresse eines Kanals (Name oder UUID). Der Server verlangt eine Anmeldung, die der Player selbst
    //mitbringen muss: die Adresse enthält keine Zugangsdaten.
    public function GetStreamUrl(string $Channel): string
    {
        $uuid = $this->ResolveChannel($Channel);
        $gatewayID = (int) (IPS_GetInstance($this->InstanceID)['ConnectionID'] ?? 0);
        if ($uuid === '' || $gatewayID === 0) {
            return '';
        }
        $scheme = IPS_GetProperty($gatewayID, 'UseSSL') ? 'https' : 'http';
        return $scheme . '://' . IPS_GetProperty($gatewayID, 'Host') . ':' . IPS_GetProperty($gatewayID, 'Port') . '/stream/channel/' . $uuid;
    }

    //Legt eine Aufnahme für eine Sendung an (eventId aus Search).
    public function RecordEvent(int $EventId, string $ConfigUuid = ''): bool
    {
        $params = ['event_id' => $EventId];
        if ($ConfigUuid !== '') {
            $params['config_uuid'] = $ConfigUuid;
        }
        return $this->ApiRequest('api/dvr/entry/create_by_event', $params, 'POST')['code'] === 200;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
        }
    }

    private function FindNow(string $Uuid): ?array
    {
        $result = $this->ApiRequest('api/epg/events/grid', ['mode' => 'now', 'channel' => $Uuid, 'limit' => 1]);
        if ($result['code'] !== 200 || !is_array($result['data'])) {
            return null;
        }
        return $result['data']['entries'][0] ?? null;
    }

    private function LoadEvent(int $EventId): ?array
    {
        $result = $this->ApiRequest('api/epg/events/load', ['eventId' => $EventId]);
        if ($result['code'] !== 200 || !is_array($result['data'])) {
            return null;
        }
        return $result['data']['entries'][0] ?? null;
    }

    //UUIDs der beobachteten Kanäle aus der Konfiguration.
    private function GetWatchedChannels(): array
    {
        $uuids = [];
        foreach ((array) json_decode($this->ReadPropertyString('Channels'), true) as $row) {
            $uuid = (string) ($row['Channel'] ?? '');
            if ($uuid !== '' && !in_array($uuid, $uuids, true)) {
                $uuids[] = $uuid;
            }
        }
        return $uuids;
    }

    private function VariableKey(string $Uuid): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $Uuid);
    }

    private function MaintainChannelVariables(): void
    {
        $names = $this->GetChannelNames();
        $wanted = [];
        $position = 10;
        foreach ($this->GetWatchedChannels() as $uuid) {
            $key = $this->VariableKey($uuid);
            $name = $names[$uuid] ?? $uuid;
            $definitions = [
                [self::PREFIX_NOW, 'On Air', VARIABLETYPE_STRING, $this->ValuePresentation()],
                [self::PREFIX_NOW_END, 'On Air until', VARIABLETYPE_INTEGER, $this->DateTimePresentation()],
                [self::PREFIX_NEXT, 'Next', VARIABLETYPE_STRING, $this->ValuePresentation()],
                [self::PREFIX_NEXT_START, 'Next from', VARIABLETYPE_INTEGER, $this->DateTimePresentation()]
            ];
            foreach ($definitions as [$prefix, $caption, $type, $presentation]) {
                $this->MaintainVariable($prefix . $key, $name . ' - ' . $this->Translate($caption), $type, $presentation, $position++, true);
                $wanted[] = $prefix . $key;
            }
        }

        //Variablen von Kanälen, die aus der Liste entfernt wurden.
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $ident = IPS_GetObject($childID)['ObjectIdent'];
            foreach ([self::PREFIX_NOW, self::PREFIX_NOW_END, self::PREFIX_NEXT, self::PREFIX_NEXT_START] as $prefix) {
                if (strpos($ident, $prefix) === 0 && !in_array($ident, $wanted, true)) {
                    $this->UnregisterVariable($ident);
                }
            }
        }
    }

    //Kanäle als UUID => Name vom Server.
    private function GetChannelList(): array
    {
        $result = $this->ApiRequest('api/channel/list');
        $list = [];
        if ($result['code'] === 200 && is_array($result['data'])) {
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                $uuid = (string) ($entry['key'] ?? $entry['uuid'] ?? '');
                if ($uuid !== '') {
                    $list[$uuid] = (string) ($entry['val'] ?? $entry['name'] ?? $uuid);
                }
            }
        }
        return $list;
    }

    private function StoreChannelNames(): void
    {
        $list = $this->GetChannelList();
        if (count($list) > 0) {
            $this->WriteAttributeString('ChannelNames', json_encode($list));
        }
    }

    private function GetChannelNames(): array
    {
        return (array) json_decode($this->ReadAttributeString('ChannelNames'), true);
    }

    //Name oder UUID eines Kanals zur UUID. Leer, wenn der Kanal nicht bekannt ist.
    private function ResolveChannel(string $Channel): string
    {
        if (preg_match('/^[0-9a-f]{32}$/i', $Channel)) {
            return $Channel;
        }
        $names = $this->GetChannelNames();
        if (count($names) === 0) {
            $names = $this->GetChannelList();
        }
        foreach ($names as $uuid => $name) {
            if (strcasecmp($name, $Channel) === 0) {
                return (string) $uuid;
            }
        }
        return '';
    }
}
