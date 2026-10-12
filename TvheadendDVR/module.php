<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';
require_once __DIR__ . '/../libs/TvheadendTile.php';

class TvheadendDVR extends IPSModuleStrict
{
    use TvheadendChild;
    use TvheadendTile;

    //Anzahl Einträge, die in der Liste der geplanten Aufnahmen erscheinen.
    private const LIST_LIMIT = 10;
    //Einträge je Liste (fertig, fehlgeschlagen) in der Kachel.
    private const TILE_LIMIT = 50;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterVariableBoolean('RecordingActive', $this->Translate('Active Recording'), $this->BooleanPresentation('Yes', 'No'), 1);
        $this->RegisterVariableInteger('RecordingCount', $this->Translate('Running Recordings'), $this->ValuePresentation(), 2);
        $this->RegisterVariableInteger('UpcomingCount', $this->Translate('Scheduled Recordings'), $this->ValuePresentation(), 3);
        $this->RegisterVariableInteger('FinishedCount', $this->Translate('Finished Recordings'), $this->ValuePresentation(), 4);
        $this->RegisterVariableInteger('FailedCount', $this->Translate('Failed Recordings'), $this->ValuePresentation(), 5);
        $this->RegisterVariableString('NextChannel', $this->Translate('Next Recording - Channel'), $this->ValuePresentation(), 6);
        $this->RegisterVariableString('NextTitle', $this->Translate('Next Recording'), $this->ValuePresentation(), 7);
        $this->RegisterVariableInteger('NextStart', $this->Translate('Next Recording - Starttime'), $this->DateTimePresentation(), 8);
        $this->RegisterVariableInteger('NextStop', $this->Translate('Next Recording - Endtime'), $this->DateTimePresentation(), 9);
        $this->RegisterVariableString('RecordingList', $this->Translate('Recordings'), $this->HtmlPresentation(), 10);

        $this->EnableTile();
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->BuildTileData($this->HasActiveParent() ? $this->LoadLists() : ['upcoming' => [], 'finished' => [], 'failed' => []]));
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent()) {
            $this->Update();
        }
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
                //Änderungen an Aufnahmen und neue EPG-Daten mit Aufnahmebezug.
                if (in_array($data['Class'] ?? '', ['dvrentry', 'dvr_entry', 'epg'], true)) {
                    $this->Update();
                }
                break;
        }
        return '';
    }

    //Liest die Aufnahmen neu vom Server.
    public function Update(): void
    {
        $upcoming = $this->ApiRequest('api/dvr/entry/grid_upcoming', ['limit' => 200, 'sort' => 'start']);
        if ($upcoming['code'] !== 200 || !is_array($upcoming['data'])) {
            $this->SendDebug(__FUNCTION__, 'grid_upcoming: ' . $upcoming['error'], 0);
            return;
        }

        $running = [];
        $scheduled = [];
        foreach ((array) ($upcoming['data']['entries'] ?? []) as $entry) {
            if (($entry['sched_status'] ?? '') === 'recording') {
                $running[] = $entry;
            } else {
                $scheduled[] = $entry;
            }
        }

        $this->SetValue('RecordingActive', count($running) > 0);
        $this->SetValue('RecordingCount', count($running));
        $this->SetValue('UpcomingCount', count($scheduled));

        //Die nächste Aufnahme ist die laufende, sonst die als Nächstes geplante.
        $next = $running[0] ?? $scheduled[0] ?? null;
        if ($next !== null) {
            $this->SetValue('NextChannel', (string) ($next['channelname'] ?? ''));
            $this->SetValue('NextTitle', (string) ($next['disp_title'] ?? ''));
            $this->SetValue('NextStart', (int) ($next['start'] ?? 0));
            $this->SetValue('NextStop', (int) ($next['stop'] ?? 0));
        } else {
            $this->SetValue('NextChannel', '');
            $this->SetValue('NextTitle', $this->Translate('No recording scheduled'));
            $this->SetValue('NextStart', 0);
            $this->SetValue('NextStop', 0);
        }

        $rows = [];
        foreach (array_slice(array_merge($running, $scheduled), 0, self::LIST_LIMIT) as $entry) {
            $rows[] = [
                ($entry['sched_status'] ?? '') === 'recording' ? $this->Translate('Recording') : $this->Translate('Scheduled'),
                $entry['disp_title'] ?? '',
                $entry['channelname'] ?? '',
                isset($entry['start']) ? date('d.m.Y H:i', (int) $entry['start']) : '',
                isset($entry['stop']) ? date('H:i', (int) $entry['stop']) : ''
            ];
        }
        $this->SetValue('RecordingList', $this->BuildTable(['Status', 'Title', 'Channel', 'Start', 'End'], $rows));

        foreach (['FinishedCount' => 'grid_finished', 'FailedCount' => 'grid_failed'] as $ident => $grid) {
            $result = $this->ApiRequest('api/dvr/entry/' . $grid, ['limit' => 1]);
            if ($result['code'] === 200 && is_array($result['data'])) {
                $this->SetValue($ident, (int) ($result['data']['totalCount'] ?? 0));
            }
        }

        $this->PushTile($this->BuildTileData($this->LoadLists()));
    }

    //Löscht eine fertige oder fehlgeschlagene Aufnahme samt Datei vom Server. Das lässt sich nicht rückgängig machen.
    public function RemoveRecording(string $Uuid): bool
    {
        $result = $this->ApiRequest('api/dvr/entry/remove', ['uuid' => $Uuid], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Legt eine Aufnahme für eine Sendung aus dem EPG an (eventId aus epg/events/grid).
    //Ohne Aufnahmeprofil nutzt Tvheadend das Standardprofil.
    public function RecordEvent(int $EventId, string $ConfigUuid = ''): bool
    {
        $params = ['event_id' => $EventId];
        if ($ConfigUuid !== '') {
            $params['config_uuid'] = $ConfigUuid;
        }
        $result = $this->ApiRequest('api/dvr/entry/create_by_event', $params, 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Beendet eine laufende Aufnahme sauber. Die Datei bleibt erhalten.
    public function StopRecording(string $Uuid): bool
    {
        $result = $this->ApiRequest('api/dvr/entry/stop', ['uuid' => $Uuid], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Löscht einen Timer. Eine laufende Aufnahme wird abgebrochen und als fehlgeschlagen geführt.
    public function CancelRecording(string $Uuid): bool
    {
        $result = $this->ApiRequest('api/dvr/entry/cancel', ['uuid' => $Uuid], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
                //Aktionen der Kachel: Wert ist ein JSON-Text mit der UUID der Aufnahme.
            case 'Stop':
            case 'Cancel':
            case 'Remove':
                $args = json_decode((string) $Value, true);
                $uuid = is_array($args) ? (string) ($args['uuid'] ?? '') : '';
                if ($uuid !== '') {
                    $ok = $Ident === 'Stop' ? $this->StopRecording($uuid) : ($Ident === 'Cancel' ? $this->CancelRecording($uuid) : $this->RemoveRecording($uuid));
                    $this->PushTile(['type' => 'result', 'ok' => $ok, 'text' => $this->Translate($ok ? 'Done' : 'Failed')]);
                }
                break;
        }
    }

    //Daten der Kachel: Texte und die drei Listen.
    private function BuildTileData(array $Lists): array
    {
        return [
            'type'   => 'recordings',
            'online' => $this->HasActiveParent(),
            't'      => $this->TileTexts([
                'upcoming'      => 'Scheduled', 'finished' => 'Finished', 'failed' => 'Failed', 'empty' => 'No recordings', 'running' => 'Recording now',
                'stop'          => 'Stop', 'cancel' => 'Delete timer', 'remove' => 'Delete recording', 'yes' => 'Yes', 'no' => 'No',
                'confirmCancel' => 'Delete this timer?', 'confirmRemove' => 'Delete the recording and its file?', 'offline' => 'Server not reachable',
                'search'        => 'Search'
            ]),
            'lists' => $Lists
        ];
    }

    //Die Aufnahmen für die Kachel: geplant und laufend, fertig, fehlgeschlagen. 'a' nennt die möglichen Aktionen.
    private function LoadLists(): array
    {
        $lists = ['upcoming' => [], 'finished' => [], 'failed' => []];
        $sources = [
            'upcoming' => ['api/dvr/entry/grid_upcoming', ['limit' => self::TILE_LIMIT, 'sort' => 'start']],
            'finished' => ['api/dvr/entry/grid_finished', ['limit' => self::TILE_LIMIT, 'sort' => 'stop', 'dir' => 'desc']],
            'failed'   => ['api/dvr/entry/grid_failed', ['limit' => self::TILE_LIMIT, 'sort' => 'stop', 'dir' => 'desc']]
        ];
        foreach ($sources as $name => [$path, $params]) {
            $result = $this->ApiRequest($path, $params);
            if ($result['code'] !== 200 || !is_array($result['data'])) {
                continue;
            }
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                if (empty($entry['uuid'])) {
                    continue;
                }
                $running = ($entry['sched_status'] ?? '') === 'recording';
                $lists[$name][] = [
                    'u'  => (string) $entry['uuid'],
                    't'  => (string) ($entry['disp_title'] ?? ''),
                    'ch' => (string) ($entry['channelname'] ?? ''),
                    's'  => (int) ($entry['start_real'] ?? $entry['start'] ?? 0),
                    'e'  => (int) ($entry['stop_real'] ?? $entry['stop'] ?? 0),
                    'sz' => (int) ($entry['filesize'] ?? 0),
                    'st' => (string) ($entry['status'] ?? ''),
                    'r'  => $running,
                    'a'  => $name === 'upcoming' ? ($running ? ['stop', 'cancel'] : ['cancel']) : ['remove']
                ];
            }
        }
        //Laufende Aufnahmen stehen oben.
        usort($lists['upcoming'], fn (array $a, array $b): int => [$b['r'], $a['s']] <=> [$a['r'], $b['s']]);
        return $lists;
    }
}
