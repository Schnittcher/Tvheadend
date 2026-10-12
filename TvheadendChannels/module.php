<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';
require_once __DIR__ . '/../libs/TvheadendTile.php';

//Kanalverwaltung: alle Kanäle des Servers nach Kanalnummer, aktivieren, deaktivieren und umbenennen, im Formular,
//in der Kachel und per Funktion.
class TvheadendChannels extends IPSModuleStrict
{
    use TvheadendChild;
    use TvheadendTile;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterVariableInteger('ChannelCount', $this->Translate('Channels'), $this->ValuePresentation(), 1);
        $this->RegisterVariableInteger('EnabledCount', $this->Translate('Enabled Channels'), $this->ValuePresentation(), 2);

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

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        foreach ($form['actions'] as &$element) {
            if (($element['name'] ?? '') === 'Channels') {
                $element['values'] = $this->HasActiveParent() ? $this->BuildRows($this->LoadChannels()) : [];
            }
        }
        return json_encode($form);
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->BuildTileData($this->HasActiveParent() ? $this->LoadChannels() : []));
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
                if (($data['Class'] ?? '') === 'channel') {
                    $this->Update();
                }
                break;
        }
        return '';
    }

    //Liest die Kanäle vom Server und aktualisiert Variablen, Formular und Kachel.
    public function Update(): void
    {
        $channels = $this->LoadChannels();
        $this->SetValue('ChannelCount', count($channels));
        $this->SetValue('EnabledCount', count(array_filter($channels, fn (array $channel): bool => $channel['enabled'])));
        $this->UpdateFormField('Channels', 'values', json_encode($this->BuildRows($channels)));
        $this->PushTile($this->BuildTileData($channels));
    }

    //Die Kanäle als JSON-Liste nach Kanalnummer: uuid, number, name, enabled, tags.
    public function GetChannels(): string
    {
        return json_encode($this->LoadChannels());
    }

    //Schaltet einen Kanal ein oder aus. $Channel ist die UUID oder der Name.
    public function SetChannelEnabled(string $Channel, bool $Enabled): bool
    {
        $channel = $this->FindChannel($Channel);
        if ($channel === null) {
            return false;
        }
        $result = $this->ApiRequest('api/idnode/save', ['node' => ['uuid' => $channel['uuid'], 'enabled' => $Enabled]], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Benennt einen Kanal um. $Channel ist die UUID oder der bisherige Name.
    public function RenameChannel(string $Channel, string $NewName): bool
    {
        $channel = $this->FindChannel($Channel);
        if ($channel === null || trim($NewName) === '') {
            return false;
        }
        $result = $this->ApiRequest('api/idnode/save', ['node' => ['uuid' => $channel['uuid'], 'name' => trim($NewName)]], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Wird vom Formular aufgerufen: speichert Name und Aktiv-Haken einer Zeile.
    public function ApplyChannel(string $RowJSON): void
    {
        $row = json_decode($RowJSON, true);
        if (is_array($row) && !empty($row['uuid'])) {
            $node = ['uuid' => (string) $row['uuid'], 'enabled' => !empty($row['enabled'])];
            if (trim((string) ($row['name'] ?? '')) !== '') {
                $node['name'] = trim((string) $row['name']);
            }
            $this->ApiRequest('api/idnode/save', ['node' => $node], 'POST');
        }
        $this->Update();
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $args = json_decode((string) $Value, true);
        $args = is_array($args) ? $args : [];
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
                //Aktionen der Kachel: Wert ist ein JSON-Text mit der UUID des Kanals.
            case 'Toggle':
            case 'Rename':
                $uuid = (string) ($args['uuid'] ?? '');
                if ($uuid !== '') {
                    $ok = $Ident === 'Toggle' ? $this->SetChannelEnabled($uuid, !empty($args['enabled'])) : $this->RenameChannel($uuid, (string) ($args['name'] ?? ''));
                    $this->PushTile(['type' => 'result', 'ok' => $ok, 'text' => $this->Translate($ok ? 'Done' : 'Failed')]);
                }
                break;
        }
    }

    private function BuildTileData(array $Channels): array
    {
        $channels = [];
        foreach ($Channels as $channel) {
            $channels[] = [
                'u'    => $channel['uuid'],
                'n'    => $channel['number'],
                'name' => $channel['name'],
                'tags' => implode(', ', $channel['tagNames']),
                'e'    => $channel['enabled']
            ];
        }
        return [
            'type'     => 'channels',
            'online'   => $this->HasActiveParent(),
            't'        => $this->TileTexts([
                'all'   => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'search' => 'Search', 'of' => 'of',
                'empty' => 'No channels', 'noMatch' => 'No matching channels', 'rename' => 'Rename', 'offline' => 'Server not reachable'
            ]),
            'channels' => $channels
        ];
    }

    //Zeilen der Liste im Formular.
    private function BuildRows(array $Channels): array
    {
        $rows = [];
        foreach ($Channels as $channel) {
            $rows[] = [
                'uuid'    => $channel['uuid'],
                'enabled' => $channel['enabled'],
                'number'  => $channel['number'],
                'name'    => $channel['name'],
                'tags'    => implode(', ', $channel['tagNames'])
            ];
        }
        return $rows;
    }

    private function FindChannel(string $Channel): ?array
    {
        foreach ($this->LoadChannels() as $channel) {
            if ($channel['uuid'] === $Channel || strcasecmp($channel['name'], $Channel) === 0) {
                return $channel;
            }
        }
        $this->LogMessage(sprintf($this->Translate('Channel "%s" not found'), $Channel), KL_WARNING);
        return null;
    }

    //Liest die Kanäle (auch abgeschaltete) und sortiert sie nach Kanalnummer, Kanäle ohne Nummer ans Ende.
    private function LoadChannels(): array
    {
        $tagNames = [];
        $tags = $this->ApiRequest('api/channeltag/list');
        if ($tags['code'] === 200 && is_array($tags['data'])) {
            foreach ((array) ($tags['data']['entries'] ?? []) as $entry) {
                $tagNames[(string) ($entry['key'] ?? '')] = (string) ($entry['val'] ?? '');
            }
        }

        $result = $this->ApiRequest('api/channel/grid', ['limit' => 2000, 'sort' => 'number', 'all' => 1]);
        $channels = [];
        if ($result['code'] === 200 && is_array($result['data'])) {
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                if (empty($entry['uuid'])) {
                    continue;
                }
                $channels[] = [
                    'uuid'     => (string) $entry['uuid'],
                    'number'   => (string) ($entry['number'] ?? ''),
                    'name'     => (string) ($entry['name'] ?? ''),
                    'enabled'  => !empty($entry['enabled']),
                    'tagNames' => array_values(array_filter(array_map(fn ($tag): string => $tagNames[(string) $tag] ?? '', (array) ($entry['tags'] ?? []))))
                ];
            }
        }
        usort($channels, function (array $a, array $b): int
        {
            $na = $a['number'] === '' ? PHP_FLOAT_MAX : (float) $a['number'];
            $nb = $b['number'] === '' ? PHP_FLOAT_MAX : (float) $b['number'];
            return $na <=> $nb ?: strcasecmp($a['name'], $b['name']);
        });
        return $channels;
    }
}
