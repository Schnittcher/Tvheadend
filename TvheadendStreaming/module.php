<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';
require_once __DIR__ . '/../libs/TvheadendTile.php';

//Wer schaut gerade was: laufende Streams (Subscriptions) und angemeldete Clients (Verbindungen) des Servers.
class TvheadendStreaming extends IPSModuleStrict
{
    use TvheadendChild;
    use TvheadendTile;

    //Mindestabstand zwischen zwei Abfragen, die durch Meldungen vom Server ausgelöst werden (Sekunden). Der Server
    //meldet laufende Streams im Sekundentakt.
    private const MIN_NOTIFY_INTERVAL = 5;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterVariableInteger('ActiveStreams', $this->Translate('Active Streams'), $this->ValuePresentation(), 1);
        $this->RegisterVariableInteger('Clients', $this->Translate('Connected Clients'), $this->ValuePresentation(), 2);
        $this->RegisterVariableString('Watching', $this->Translate('Watching'), $this->ValuePresentation(), 3);
        $this->RegisterVariableString('StreamList', $this->Translate('Streams'), $this->HtmlPresentation(), 4);
        $this->RegisterVariableString('ClientList', $this->Translate('Clients'), $this->HtmlPresentation(), 5);

        //Holt nach einer Flut von Meldungen einmal alles neu (die letzte Meldung wird sonst von der Drosselung verschluckt).
        $this->RegisterTimer('Refresh', 0, 'TVHM_RefreshLater($_IPS[\'TARGET\']);');

        $this->EnableTile();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent()) {
            $this->Update();
        }
    }

    public function GetVisualizationTile(): string
    {
        $data = $this->HasActiveParent() ? $this->LoadSessions() : ['streams' => [], 'clients' => []];
        return $this->RenderTile(__DIR__, $this->BuildTileData($data));
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        switch ($data['Action'] ?? '') {
            case TVH_ACTION_SYNC:
                if (($data['Online'] ?? false) === true) {
                    $this->Update();
                }
                break;
            case TVH_ACTION_NOTIFY:
                if (in_array($data['Class'] ?? '', ['subscriptions', 'connections'], true)) {
                    $last = (int) $this->GetBuffer('LastNotifyUpdate');
                    if (time() - $last >= self::MIN_NOTIFY_INTERVAL) {
                        $this->SetBuffer('LastNotifyUpdate', (string) time());
                        $this->Update();
                    } else {
                        //Endet die Meldungsflut (der Stream hört auf), läuft der Timer ab und holt den Endstand.
                        $this->SetTimerInterval('Refresh', (self::MIN_NOTIFY_INTERVAL + 1) * 1000);
                    }
                }
                break;
        }
        return '';
    }

    //Wird vom Timer nach einer Meldungsflut aufgerufen.
    public function RefreshLater(): void
    {
        $this->SetTimerInterval('Refresh', 0);
        $this->SetBuffer('LastNotifyUpdate', (string) time());
        $this->Update();
    }

    //Liest Streams und Clients vom Server.
    public function Update(): void
    {
        if (!($this->GetServerInfo()['admin'] ?? false)) {
            //status/subscriptions und status/connections brauchen Administratorrechte.
            $this->SendDebug(__FUNCTION__, 'User is not an administrator', 0);
            return;
        }
        $sessions = $this->LoadSessions();
        $streams = $sessions['streams'];
        $clients = $sessions['clients'];

        $this->SetValue('ActiveStreams', count($streams));
        $this->SetValue('Clients', count($clients));
        $this->SetValue('Watching', implode(', ', array_map(fn (array $s): string => $s['title'] . ($s['who'] !== '' ? ' (' . $s['who'] . ')' : ''), $streams)));

        $rows = [];
        foreach ($streams as $stream) {
            $rows[] = [$stream['title'], $stream['who'], $stream['profile'], $stream['state'], $stream['start'] > 0 ? date('d.m.Y H:i', $stream['start']) : '', $stream['errors']];
        }
        $this->SetValue('StreamList', $this->BuildTable(['Channel', 'User', 'Profile', 'State', 'Start', 'Errors'], $rows));

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [$client['type'], $client['user'], $client['peer'], $client['started'] > 0 ? date('d.m.Y H:i', $client['started']) : ''];
        }
        $this->SetValue('ClientList', $this->BuildTable(['Type', 'User', 'Address', 'Connected since'], $rows));

        $this->PushTile($this->BuildTileData($sessions));
    }

    //Die Streams und Clients als JSON: {streams: [...], clients: [...]}.
    public function GetSessions(): string
    {
        return json_encode($this->LoadSessions());
    }

    //Trennt einen Client (ID aus GetSessions) oder mit "all" alle Clients.
    public function CancelConnection(string $ConnectionId): bool
    {
        $result = $this->ApiRequest('api/connections/cancel', ['id' => $ConnectionId], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
                //Aktion der Kachel: Wert ist ein JSON-Text mit der ID der Verbindung.
            case 'Cancel':
                $args = json_decode((string) $Value, true);
                $id = is_array($args) ? (string) ($args['id'] ?? '') : '';
                if ($id !== '') {
                    $ok = $this->CancelConnection($id);
                    $this->PushTile(['type' => 'result', 'ok' => $ok, 'text' => $this->Translate($ok ? 'Done' : 'Failed')]);
                }
                break;
        }
    }

    private function BuildTileData(array $Sessions): array
    {
        return [
            'type'    => 'streams',
            'online'  => $this->HasActiveParent(),
            't'       => $this->TileTexts([
                'streams'    => 'Streams', 'clients' => 'Clients', 'noStreams' => 'Nobody is watching', 'noClients' => 'No clients connected',
                'disconnect' => 'Disconnect', 'confirm' => 'Disconnect this client?', 'yes' => 'Yes', 'no' => 'No',
                'offline'    => 'Server not reachable', 'since' => 'since', 'errors' => 'errors'
            ]),
            'streams' => $Sessions['streams'],
            'clients' => $Sessions['clients']
        ];
    }

    //Liest status/subscriptions und status/connections. Felder, die der Server nicht liefert, bleiben leer.
    private function LoadSessions(): array
    {
        $streams = [];
        $result = $this->ApiRequest('api/status/subscriptions');
        if ($result['code'] === 200 && is_array($result['data'])) {
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                $user = (string) ($entry['username'] ?? '');
                $host = (string) ($entry['hostname'] ?? '');
                $client = (string) ($entry['client'] ?? '');
                $who = trim($user . ($host !== '' ? '@' . $host : '') . ($client !== '' ? ' - ' . $client : ''));
                $streams[] = [
                    'id'      => (string) ($entry['id'] ?? ''),
                    'title'   => (string) ($entry['channel'] ?? $entry['title'] ?? $entry['service'] ?? ''),
                    'who'     => $who,
                    'profile' => (string) ($entry['profile'] ?? ''),
                    'state'   => (string) ($entry['state'] ?? ''),
                    'start'   => (int) ($entry['start'] ?? 0),
                    'errors'  => (int) ($entry['errors'] ?? 0)
                ];
            }
        }

        $clients = [];
        $result = $this->ApiRequest('api/status/connections');
        if ($result['code'] === 200 && is_array($result['data'])) {
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                $peer = (string) ($entry['peer'] ?? '');
                if (isset($entry['peer_port']) && $peer !== '') {
                    $peer .= ':' . $entry['peer_port'];
                }
                $clients[] = [
                    'id'      => (string) ($entry['id'] ?? ''),
                    'type'    => (string) ($entry['type'] ?? ''),
                    'user'    => (string) ($entry['user'] ?? ''),
                    'peer'    => $peer,
                    'started' => (int) ($entry['started'] ?? 0)
                ];
            }
        }
        return ['streams' => $streams, 'clients' => $clients];
    }
}
