<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TvheadendConstants.php';
require_once __DIR__ . '/../libs/TvheadendChild.php';
require_once __DIR__ . '/../libs/TvheadendTile.php';

//Verwaltet die Serien- (dvr/autorec) und Zeitaufnahmen (dvr/timerec) des Servers in einer Instanz: anlegen,
//bearbeiten, ein- und ausschalten und löschen, im Formular und per Funktion.
class TvheadendRules extends IPSModuleStrict
{
    use TvheadendChild;
    use TvheadendTile;

    private const TYPE_SERIES = 'series';
    private const TYPE_TIME = 'time';

    //Wochentage in der Zählung von Tvheadend: 1 = Montag bis 7 = Sonntag.
    private const WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterVariableInteger('RuleCount', $this->Translate('Recording Rules'), $this->ValuePresentation(), 1);
        $this->RegisterVariableInteger('ActiveCount', $this->Translate('Active Recording Rules'), $this->ValuePresentation(), 2);
        $this->RegisterVariableString('RuleList', $this->Translate('Recording Rules - List'), $this->HtmlPresentation(), 3);

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
            if (($element['name'] ?? '') === 'Rules') {
                $element['columns'] = $this->Columns();
                $element['values'] = $this->HasActiveParent() ? $this->BuildRows($this->LoadRules()) : [];
            }
        }
        return json_encode($form);
    }

    public function GetVisualizationTile(): string
    {
        return $this->RenderTile(__DIR__, $this->BuildTileData($this->HasActiveParent() ? $this->LoadRules() : []));

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
                //Die Klassennamen der Regeln sind nicht dokumentiert: jede DVR-Meldung löst einen Abgleich aus.
                if (strpos((string) ($data['Class'] ?? ''), 'dvr') === 0) {
                    $this->Update();
                }
                break;
        }
        return '';
    }

    //Liest die Regeln vom Server und aktualisiert Variablen und Liste im Formular.
    public function Update(): void
    {
        $rules = $this->LoadRules();
        $this->SetValue('RuleCount', count($rules));
        $this->SetValue('ActiveCount', count(array_filter($rules, fn (array $rule): bool => $rule['enabled'])));

        $rows = [];
        foreach ($rules as $rule) {
            $rows[] = [
                $this->Translate($rule['type'] === self::TYPE_TIME ? 'Time recording' : 'Series recording'),
                $rule['name'] !== '' ? $rule['name'] : $rule['title'],
                $rule['channelName'],
                $this->WeekdayText($rule['weekdays']),
                $this->Translate($rule['enabled'] ? 'Yes' : 'No')
            ];
        }
        $this->SetValue('RuleList', $this->BuildTable(['Type', 'Name', 'Channel', 'Weekdays', 'Enabled'], $rows));
        $this->UpdateFormField('Rules', 'values', json_encode($this->BuildRows($rules)));
        $this->PushTile($this->BuildTileData($rules));
    }

    //Die Regeln als JSON-Liste: uuid, type (series oder time), name, title, channel (UUID), channelName, enabled, ...
    public function GetRules(): string
    {
        return json_encode($this->LoadRules());
    }

    //Schaltet eine Regel ein oder aus. $Rule ist die UUID oder der Name der Regel.
    public function SetRuleEnabled(string $Rule, bool $Enabled): bool
    {
        $rule = $this->FindRule($Rule);
        if ($rule === null) {
            return false;
        }
        $result = $this->ApiRequest('api/idnode/save', ['node' => ['uuid' => $rule['uuid'], 'enabled' => $Enabled]], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Löscht eine Regel. $Rule ist die UUID oder der Name der Regel. Bereits geplante Aufnahmen bleiben bestehen.
    public function DeleteRule(string $Rule): bool
    {
        $rule = $this->FindRule($Rule);
        if ($rule === null) {
            return false;
        }
        $result = $this->ApiRequest('api/idnode/delete', ['uuid' => [$rule['uuid']]], 'POST');
        $this->Update();
        return $result['code'] === 200;
    }

    //Legt eine Serienaufnahme an. $Channel ist ein Kanalname oder eine UUID, leer bedeutet alle Kanäle.
    //$Title ist ein regulärer Ausdruck, der auf den Titel der Sendung passt.
    public function CreateSeriesRule(string $Title, string $Channel = '', string $Name = ''): bool
    {
        $channel = $Channel === '' ? '' : $this->ResolveChannel($Channel);
        if ($Channel !== '' && $channel === '') {
            $this->LogMessage(sprintf($this->Translate('Channel "%s" not found'), $Channel), KL_WARNING);
            return false;
        }
        $row = ['type' => self::TYPE_SERIES, 'enabled' => true, 'name' => $Name !== '' ? $Name : $Title, 'title' => $Title, 'channel' => $channel];
        return $this->SaveRow($row);
    }

    //Wird vom Formular aufgerufen: legt die Zeile an oder speichert sie (Hinzufügen, Bearbeiten, Haken in der Tabelle).
    public function ApplyRule(string $RowJSON): void
    {
        $row = json_decode($RowJSON, true);
        if (!is_array($row)) {
            return;
        }
        if (!$this->SaveRow($row)) {
            $this->UpdateFormField('ErrorLabel', 'caption', $this->Translate('The server rejected the recording rule'));
            $this->UpdateFormField('ErrorLabel', 'visible', true);
        } else {
            $this->UpdateFormField('ErrorLabel', 'visible', false);
        }
        $this->Update();
    }

    //Wird vom Formular aufgerufen: löscht die Regel einer Zeile.
    public function RemoveRule(string $Uuid): void
    {
        if ($Uuid !== '') {
            $this->ApiRequest('api/idnode/delete', ['uuid' => [$Uuid]], 'POST');
        }
        $this->Update();
    }

    //Wird vom Formular aufgerufen: Elemente des Dialogs zum Hinzufügen und Bearbeiten.
    public function RuleForm(string $Type, string $Uuid): string
    {
        $isNew = $Uuid === '';
        $isSeries = $Type !== self::TYPE_TIME;

        $channels = [['caption' => $this->Translate('All channels'), 'value' => '']];
        foreach ($this->GetChannelNames() as $uuid => $name) {
            $channels[] = ['caption' => $name, 'value' => $uuid];
        }
        $configs = [];
        foreach ($this->LoadConfigs() as $config) {
            $configs[] = ['caption' => $config['name'] !== '' ? $config['name'] : $this->Translate('Default'), 'value' => $config['uuid']];
        }

        $weekdays = [];
        foreach (self::WEEKDAYS as $number => $day) {
            $weekdays[] = ['type' => 'CheckBox', 'name' => 'wd' . $number, 'caption' => $this->Translate($day)];
        }

        $timeRule = '^(Any|[01]?[0-9]:[0-5][0-9]|2[0-3]:[0-5][0-9])$';
        $elements = [
            [
                'type'     => 'Select',
                'name'     => 'type',
                'caption'  => 'Type',
                'enabled'  => $isNew,
                'options'  => [
                    ['caption' => $this->Translate('Series recording'), 'value' => self::TYPE_SERIES],
                    ['caption' => $this->Translate('Time recording'), 'value' => self::TYPE_TIME]
                ],
                'onChange' => 'IPS_UpdateFormField("fulltext", "visible", $type === "series", $id);'
                    . 'IPS_UpdateFormField("startWindow", "visible", $type === "series", $id);'
                    . 'IPS_UpdateFormField("stop", "visible", $type === "time", $id);'
            ],
            ['type' => 'CheckBox', 'name' => 'enabled', 'caption' => 'Enabled'],
            ['type' => 'ValidationTextBox', 'name' => 'name', 'caption' => 'Name'],
            ['type' => 'ValidationTextBox', 'name' => 'title', 'caption' => $isSeries ? 'Title (regular expression)' : 'Title'],
            ['type' => 'CheckBox', 'name' => 'fulltext', 'caption' => 'Full text search', 'visible' => $isSeries],
            ['type' => 'Select', 'name' => 'channel', 'caption' => 'Channel', 'options' => $channels],
            ['type' => 'ValidationTextBox', 'name' => 'start', 'caption' => 'Start after (Any or HH:MM)', 'validate' => $timeRule],
            ['type' => 'ValidationTextBox', 'name' => 'startWindow', 'caption' => 'Start before (Any or HH:MM)', 'validate' => $timeRule, 'visible' => $isSeries],
            ['type' => 'ValidationTextBox', 'name' => 'stop', 'caption' => 'End (HH:MM)', 'validate' => '^([01]?[0-9]|2[0-3]):[0-5][0-9]$', 'visible' => !$isSeries],
            ['type' => 'RowLayout', 'items' => $weekdays],
            ['type' => 'Select', 'name' => 'config', 'caption' => 'Recording profile', 'options' => $configs],
            ['type' => 'ValidationTextBox', 'name' => 'comment', 'caption' => 'Comment']
        ];
        return json_encode($elements);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Update':
                $this->Update();
                break;
                //Aktionen der Kachel: der Wert ist ein JSON-Text mit der UUID der Regel.
            case 'Toggle':
            case 'Delete':
                $args = json_decode((string) $Value, true);
                $uuid = is_array($args) ? (string) ($args['uuid'] ?? '') : '';
                if ($uuid === '') {
                    break;
                }
                $ok = $Ident === 'Toggle' ? $this->SetRuleEnabled($uuid, !empty($args['enabled'])) : $this->DeleteRule($uuid);
                $this->PushTile(['type' => 'result', 'ok' => $ok, 'text' => $this->Translate($ok ? 'Done' : 'Failed')]);
                break;
        }
    }

    //Daten der Kachel: Texte und die Regeln.
    private function BuildTileData(array $Rules): array
    {
        $keys = [
            'all'     => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'search' => 'Search', 'delete' => 'Delete',
            'confirm' => 'Delete this recording rule?', 'yes' => 'Yes', 'no' => 'No', 'empty' => 'No recording rules',
            'offline' => 'Server not reachable', 'rules' => 'Recording rules', 'of' => 'of', 'noMatch' => 'No matching rules'
        ];
        $texts = $this->TileTexts($keys);

        $rules = [];

        foreach ($Rules as $rule) {
            $details = [
                $this->Translate($rule['type'] === self::TYPE_TIME ? 'Time recording' : 'Series recording'),
                $rule['channelName'],
                $this->WeekdayText($rule['weekdays'])
            ];
            if ($rule['start'] !== '' && $rule['start'] !== 'Any') {
                $details[] = $rule['start'] . ($rule['type'] === self::TYPE_TIME && $rule['stop'] !== '' ? ' - ' . $rule['stop'] : '');
            }
            $rules[] = [
                'u' => $rule['uuid'],
                'n' => $rule['name'] !== '' ? $rule['name'] : $rule['title'],
                'd' => implode(' | ', array_filter($details, fn (string $part): bool => $part !== '')),
                'e' => $rule['enabled']
            ];
        }
        return ['type' => 'rules', 'online' => $this->HasActiveParent(), 't' => $texts, 'rules' => $rules];
    }

    //Spalten der Liste im Formular. Die versteckten Spalten tragen die Werte, die der Dialog bearbeitet.
    private function Columns(): array
    {
        $columns = [
            ['caption' => 'Enabled', 'name' => 'enabled', 'width' => '70px', 'edit' => ['type' => 'CheckBox']],
            ['caption' => 'Type', 'name' => 'typeText', 'width' => '120px'],
            ['caption' => 'Name', 'name' => 'name', 'width' => 'auto', 'quickFilter' => true],
            ['caption' => 'Title', 'name' => 'title', 'width' => '200px'],
            ['caption' => 'Channel', 'name' => 'channelName', 'width' => '150px'],
            ['caption' => 'Weekdays', 'name' => 'weekdaysText', 'width' => '130px'],
            ['caption' => 'Start after', 'name' => 'start', 'width' => '90px']
        ];
        $hidden = [
            'uuid'        => '',
            'type'        => self::TYPE_SERIES,
            'channel'     => '',
            'startWindow' => 'Any',
            'stop'        => '',
            'fulltext'    => false,
            'config'      => '',
            'comment'     => ''
        ];
        foreach (array_keys(self::WEEKDAYS) as $number) {
            $hidden['wd' . $number] = true;
        }
        foreach ($hidden as $name => $add) {
            $columns[] = ['caption' => $name, 'name' => $name, 'width' => '0px', 'visible' => false, 'save' => true, 'add' => $add];
        }
        return $columns;
    }

    private function BuildRows(array $Rules): array
    {
        $rows = [];
        foreach ($Rules as $rule) {
            $row = [
                'uuid'         => $rule['uuid'],
                'type'         => $rule['type'],
                'typeText'     => $this->Translate($rule['type'] === self::TYPE_TIME ? 'Time recording' : 'Series recording'),
                'enabled'      => $rule['enabled'],
                'name'         => $rule['name'],
                'title'        => $rule['title'],
                'channel'      => $rule['channel'],
                'channelName'  => $rule['channelName'],
                'weekdaysText' => $this->WeekdayText($rule['weekdays']),
                'start'        => $rule['start'],
                'startWindow'  => $rule['startWindow'],
                'stop'         => $rule['stop'],
                'fulltext'     => $rule['fulltext'],
                'config'       => $rule['config'],
                'comment'      => $rule['comment']
            ];
            foreach (array_keys(self::WEEKDAYS) as $number) {
                $row['wd' . $number] = in_array($number, $rule['weekdays'], true);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    //Legt die Regel an oder speichert sie. $Row hat die Werte des Formulars (siehe Columns).
    private function SaveRow(array $Row): bool
    {
        $type = ($Row['type'] ?? self::TYPE_SERIES) === self::TYPE_TIME ? self::TYPE_TIME : self::TYPE_SERIES;
        $weekdays = [];
        foreach (array_keys(self::WEEKDAYS) as $number) {
            if (!array_key_exists('wd' . $number, $Row) || !empty($Row['wd' . $number])) {
                $weekdays[] = $number;
            }
        }

        $conf = [
            'enabled'  => !array_key_exists('enabled', $Row) || !empty($Row['enabled']),
            'name'     => (string) ($Row['name'] ?? ''),
            'title'    => (string) ($Row['title'] ?? ''),
            'channel'  => (string) ($Row['channel'] ?? ''),
            'start'    => (string) ($Row['start'] ?? ''),
            'weekdays' => $weekdays,
            'comment'  => (string) ($Row['comment'] ?? '')
        ];
        if (!empty($Row['config'])) {
            $conf['config_name'] = (string) $Row['config'];
        }
        if ($type === self::TYPE_SERIES) {
            $conf['fulltext'] = !empty($Row['fulltext']);
            $conf['start_window'] = (string) ($Row['startWindow'] ?? 'Any');
            if ($conf['start'] === '') {
                $conf['start'] = 'Any';
            }
        } else {
            $conf['stop'] = (string) ($Row['stop'] ?? '');
        }

        $uuid = (string) ($Row['uuid'] ?? '');
        if ($uuid === '') {
            $path = $type === self::TYPE_TIME ? 'api/dvr/timerec/create' : 'api/dvr/autorec/create';
            $result = $this->ApiRequest($path, ['conf' => $conf], 'POST');
        } else {
            $result = $this->ApiRequest('api/idnode/save', ['node' => ['uuid' => $uuid] + $conf], 'POST');
        }
        $this->SendDebug(__FUNCTION__, ($uuid === '' ? 'create ' : 'save ') . $type . ' => ' . $result['code'], 0);
        return $result['code'] === 200;
    }

    private function FindRule(string $Rule): ?array
    {
        foreach ($this->LoadRules() as $rule) {
            if ($rule['uuid'] === $Rule || strcasecmp($rule['name'], $Rule) === 0) {
                return $rule;
            }
        }
        $this->LogMessage(sprintf($this->Translate('Recording rule "%s" not found'), $Rule), KL_WARNING);
        return null;
    }

    //Liest Serien- und Zeitaufnahmen. Jede Regel hat dieselben Felder, nicht belegte sind leer.
    private function LoadRules(): array
    {
        $names = $this->GetChannelNames();
        $rules = [];
        foreach ([self::TYPE_SERIES => 'api/dvr/autorec/grid', self::TYPE_TIME => 'api/dvr/timerec/grid'] as $type => $path) {
            $result = $this->ApiRequest($path, ['limit' => 1000]);
            if ($result['code'] !== 200 || !is_array($result['data'])) {
                continue;
            }
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                if (empty($entry['uuid'])) {
                    continue;
                }
                $channel = (string) ($entry['channel'] ?? '');
                $rules[] = [
                    'uuid'        => (string) $entry['uuid'],
                    'type'        => $type,
                    'name'        => (string) ($entry['name'] ?? ''),
                    'title'       => (string) ($entry['title'] ?? ''),
                    'channel'     => $channel,
                    'channelName' => $channel === '' ? $this->Translate('All channels') : ($names[$channel] ?? $channel),
                    'enabled'     => !empty($entry['enabled']),
                    'weekdays'    => array_map('intval', (array) ($entry['weekdays'] ?? [])),
                    'start'       => (string) ($entry['start'] ?? ''),
                    'startWindow' => (string) ($entry['start_window'] ?? 'Any'),
                    'stop'        => (string) ($entry['stop'] ?? ''),
                    'fulltext'    => !empty($entry['fulltext']),
                    'config'      => (string) ($entry['config_name'] ?? ''),
                    'comment'     => (string) ($entry['comment'] ?? '')
                ];
            }
        }
        return $rules;
    }

    private function WeekdayText(array $Weekdays): string
    {
        if (count($Weekdays) === 7) {
            return $this->Translate('Every day');
        }
        $days = [];
        foreach (self::WEEKDAYS as $number => $day) {
            if (in_array($number, $Weekdays, true)) {
                $days[] = $this->Translate($day);
            }
        }
        return implode(', ', $days);
    }

    //Kanäle als UUID => Name.
    private function GetChannelNames(): array
    {
        $names = [];
        $result = $this->ApiRequest('api/channel/list');
        if ($result['code'] === 200 && is_array($result['data'])) {
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                $names[(string) ($entry['key'] ?? '')] = (string) ($entry['val'] ?? '');
            }
        }
        return $names;
    }

    //Name oder UUID eines Kanals zur UUID, leer wenn der Kanal nicht bekannt ist.
    private function ResolveChannel(string $Channel): string
    {
        if (preg_match('/^[0-9a-f]{32}$/i', $Channel)) {
            return $Channel;
        }
        foreach ($this->GetChannelNames() as $uuid => $name) {
            if (strcasecmp($name, $Channel) === 0) {
                return (string) $uuid;
            }
        }
        return '';
    }

    //Aufnahmeprofile des Servers: [{uuid, name}].
    private function LoadConfigs(): array
    {
        $configs = [];
        $result = $this->ApiRequest('api/dvr/config/grid', ['limit' => 50]);
        if ($result['code'] === 200 && is_array($result['data'])) {
            foreach ((array) ($result['data']['entries'] ?? []) as $entry) {
                if (!empty($entry['uuid'])) {
                    $configs[] = ['uuid' => (string) $entry['uuid'], 'name' => (string) ($entry['name'] ?? '')];
                }
            }
        }
        return $configs;
    }
}
