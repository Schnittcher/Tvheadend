<?php

declare(strict_types=1);

require_once __DIR__ . '/libs/TVH.php';

class Tvheadend extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('TvhIP', '');
        $this->RegisterPropertyInteger('TvhPort', 9981);
        $this->RegisterPropertyString('TvhMac', '');
        $this->RegisterPropertyString('Broadcast', '');
        $this->RegisterPropertyString('ServerUsername', 'root');
        $this->RegisterPropertyString('ServerPassword', '');
        $this->RegisterPropertyString('WebinterfaceUsername', 'admin');
        $this->RegisterPropertyString('WebinterfacePassword', '');

        $this->RegisterPropertyInteger('StartTimeRecording', 5);
        $this->RegisterPropertyInteger('EndTimeRecording', 5);
        $this->RegisterPropertyInteger('UpdateTimerInterval', 20);

        $this->createVariablenProfiles();
        $this->RegisterVariableBoolean('TVHStatus', $this->Translate('Server State'), 'TVH.ServerStatus', 1);
        $this->RegisterVariableBoolean('TVHPower', $this->Translate('Power'), '~Switch', 2);
        $this->RegisterVariableInteger('TVHConnections', $this->Translate('Connections'), '', 3);
        $this->RegisterVariableInteger('TVHSubscriptions', $this->Translate('Subscriptions'), '', 4);
        $this->RegisterVariableString('TVHSubscriptionsInfo', $this->Translate('Subscription Infos'), '~HTMLBox', 5);
        $this->RegisterVariableString('TVHNextRecordingChannel', $this->Translate('Next Recording - Channel'), '', 6);
        $this->RegisterVariableString('TVHNextRecording', $this->Translate('Next Recording'), '', 7);
        $this->RegisterVariableInteger('TVHNextRecordingStartTime', $this->Translate('Next Recording - Starttime'), '~UnixTimestamp', 8);
        $this->RegisterVariableInteger('TVHNextRecordingEndTime', $this->Translate('Next Recording - Endtime'), '~UnixTimestamp', 9);
        $this->RegisterVariableBoolean('TVHActiveRecording', $this->Translate('Active Recording'), 'TVH.ActiveRecording', 10);

        $this->RegisterTimer('TVH_UpdateActuallyStatus', 0, 'TVH_updateActuallyStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetTimerInterval('TVH_UpdateActuallyStatus', $this->ReadPropertyInteger('UpdateTimerInterval') * 1000);
        $this->EnableAction('TVHPower');
    }

    public function updateActuallyStatus()
    {
        $this->checkActiveRecording();
        $this->checkServerStatus();
        $this->getNextRecording();
        $this->getConnections();
        $this->getSubscriptions();
    }

    public function checkServerStatus()
    {
        $TVH = new TVH($this->ReadPropertyString('TvhIP'), $this->ReadPropertyInteger('TvhPort'), $this->ReadPropertyString('TvhMac'), $this->ReadPropertyString('WebinterfaceUsername'), $this->ReadPropertyString('WebinterfacePassword'));
        $this->SetValue('TVHStatus', $TVH->getServerStatus());
        $this->SetValue('TVHPower', $TVH->getServerStatus());
    }

    public function wakeUP()
    {
        $TVH = new TVH($this->ReadPropertyString('TvhIP'), $this->ReadPropertyInteger('TvhPort'), $this->ReadPropertyString('TvhMac'), $this->ReadPropertyString('WebinterfaceUsername'), $this->ReadPropertyString('WebinterfacePassword'));
        $TVH->WakeOnLan($this->ReadPropertyString('Broadcast'));
    }

    public function shutdown()
    {
        set_include_path(__DIR__ . '/libs/vendor');
        require_once __DIR__ . '/libs/vendor/Net/SSH2.php';
        $ssh = new Net_SSH2($this->ReadPropertyString('TvhIP'));

        if (@!$ssh->login($this->ReadPropertyString('ServerUsername'), $this->ReadPropertyString('ServerPassword'))) {
            $this->LogMessage('Login failed - Server offline?', KL_NOTIFY);
            return;
        }
        @$ssh->exec('shutdown -h now');
    }

    public function getNextRecording()
    {
        $TVH = new TVH($this->ReadPropertyString('TvhIP'), $this->ReadPropertyInteger('TvhPort'), $this->ReadPropertyString('TvhMac'), $this->ReadPropertyString('WebinterfaceUsername'), $this->ReadPropertyString('WebinterfacePassword'));
        $recordings = $TVH->getUpcomingRecordings();

        if (is_array($recordings)) {
            if (count($recordings['entries']) > 0) {
                $this->SendDebug('Geplante Aufnahmen', json_encode($recordings), 0);
                $startTime = $recordings['entries'][0]['start'];
                $endTime = $recordings['entries'][0]['stop'];
                $channel = $recordings['entries'][0]['channelname'];
                $title = @$recordings['entries'][0]['title']['ger'] . ' - ' . $recordings['entries'][0]['disp_subtitle'];

                $this->SetValue('TVHNextRecordingChannel', $channel);
                $this->SetValue('TVHNextRecording', $title);
                $this->SetValue('TVHNextRecordingStartTime', $startTime);
                $this->SetValue('TVHNextRecordingEndTime', $endTime);
            } else {
                $this->SetValue('TVHNextRecordingChannel', '');
                $this->SetValue('TVHNextRecording', 'Keine Aufnahme geplant');
                $this->SetValue('TVHNextRecordingStartTime', 0);
                $this->SetValue('TVHNextRecordingEndTime', 0);
            }
        }
    }

    public function getConnections()
    {
        $TVH = new TVH($this->ReadPropertyString('TvhIP'), $this->ReadPropertyInteger('TvhPort'), $this->ReadPropertyString('TvhMac'), $this->ReadPropertyString('WebinterfaceUsername'), $this->ReadPropertyString('WebinterfacePassword'));
        $connections = $TVH->getConnections();
        if (is_array($connections)) {
            $this->SetValue('TVHConnections', $connections['totalCount']);
        } else {
            $this->SetValue('TVHConnections', 0);
        }
    }

    public function getSubscriptions()
    {
        $TVH = new TVH($this->ReadPropertyString('TvhIP'), $this->ReadPropertyInteger('TvhPort'), $this->ReadPropertyString('TvhMac'), $this->ReadPropertyString('WebinterfaceUsername'), $this->ReadPropertyString('WebinterfacePassword'));
        $connections = $TVH->getSubscriptions();
        if (is_array($connections)) {
            $this->SetValue('TVHSubscriptions', $connections['totalCount']);
        } else {
            $this->SetValue('TVHSubscriptions', 0);
        }

        $htmlbox = '
        <style type="text/css">
            .Subscriptions{
                width:100%; 
                border-collapse:collapse; 
            }
            .Subscriptions td{ 
                padding:7px; border:#111c2d 3px solid;
            }
            /* provide some minimal visual accomodation for IE8 and below */
            .Subscriptions tr{
                background: #000000;
                text-align:center;
            }
            /*  Define the background color for all the ODD background rows  */
            .Subscriptions tr:nth-child(odd){ 
                background: #1d304f;
            }
            /*  Define the background color for all the EVEN background rows  */
            .Subscriptions tr:nth-child(even){
                background: #142135;
            }
        </style>
        <table class="Subscriptions">
	    <tbody>
		<tr>
		<th>User</th>
		<th>Host</th>
		<th>' . $this->Translate('Starttime') . '</th>
		<th>Client</th>
		<th>' . $this->Translate('Channel') . '</th>
		<th>' . $this->Translate('Profile') . '</th>
		</tr>
		<tr>';
        if (is_array($connections)) {
            foreach ($connections['entries'] as $connection) {
                if (array_key_exists('username', $connection)) {
                    if (array_key_exists('username', $connection)) {
                        $htmlbox .= '<td class="odd">' . $connection['username'] . '</td>';
                    } else {
                        $htmlbox .= '<td class="odd"> </td>';
                    }
                    if (array_key_exists('hostname', $connection)) {
                        $htmlbox .= '<td>' . $connection['hostname'] . '</td>';
                    } else {
                        $htmlbox .= '<td> </td>';
                    }
                    if (array_key_exists('start', $connection)) {
                        $htmlbox .= '<td>' . date('d.m.Y H:i', $connection['start']) . '</td>';
                    } else {
                        $htmlbox .= '<td> </td>';
                    }
                    if (array_key_exists('title', $connection)) {
                        $htmlbox .= '<td>' . $connection['title'] . '</td>';
                    } else {
                        $htmlbox .= '<td> </td>';
                    }
                    if (array_key_exists('channel', $connection)) {
                        $htmlbox .= '<td>' . $connection['channel'] . '</td>';
                    } else {
                        $htmlbox .= '<td> </td>';
                    }
                    if (array_key_exists('profile', $connection)) {
                        $htmlbox .= '<td>' . $connection['profile'] . '</td>';
                    } else {
                        $htmlbox .= '<td> </td>';
                    }
                    $htmlbox .= '</tr>';
                }
            }
        }
        $htmlbox .= '</tbody></table>';
        $this->SetValue('TVHSubscriptionsInfo', $htmlbox);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'TVHPower':
                if ($Value) {
                    $this->SendDebug(__FUNCTION__ . ' TVH Wakeup', $Value, 0);
                    $this->wakeUP();
                    $this->SetValue($Ident, true);
                } else {
                    $this->SendDebug(__FUNCTION__ . ' TVH Shutdown', $Value, 0);
                    $this->shutdown();
                    $this->SetValue($Ident, false);
                }
                break;
        }
    }

    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 0);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 0) {
                throw new Exception('Variable profile type does not match for profile' . $Name);
            }
        }
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }
        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    private function createVariablenProfiles()
    {
        //Online / Offline Profile
        $this->RegisterProfileBooleanEx('TVH.ServerStatus', 'Network', '', '', [
            [false, 'Offline',  '', 0xFF0000],
            [true, 'Online',  '', 0x00FF00]
        ]);
        $this->RegisterProfileBooleanEx('TVH.ActiveRecording', 'TV', '', '', [
            [false, 'Nein',  '', 0xFF0000],
            [true, 'Ja',  '', 0x00FF00]
        ]);
    }

    private function checkActiveRecording()
    {
        $RecordingStartTime = $this->GetValue('TVHNextRecordingStartTime');
        $RecordingEndTime = $this->GetValue('TVHNextRecordingEndTime');

        $RecordingStartTime = (int) $RecordingStartTime - ($this->ReadPropertyInteger('StartTimeRecording') * 60);
        $RecordingEndTime = (int) $RecordingEndTime + ($this->ReadPropertyInteger('EndTimeRecording') * 60);

        $this->SendDebug(__FUNCTION__, 'Aufnahme Startzeit: ' . date('d.m.Y - H:i', $RecordingStartTime), 0);
        $this->SendDebug(__FUNCTION__, 'Aufnahme Endzeit: ' . date('d.m.Y - H:i', $RecordingEndTime), 0);

        if ($RecordingStartTime < time() && ($RecordingEndTime > time())) {
            $this->SendDebug(__FUNCTION__, 'Aktuelle Zeit: ' . time(), 0);
            $this->SendDebug(__FUNCTION__, 'Aufnahme Startzeit: ' . $RecordingEndTime, 0);
            $this->SendDebug(__FUNCTION__, 'Aufnahme Endzeit: ' . $RecordingEndTime, 0);
            $this->SetValue('TVHActiveRecording', true);
        } else {
            $this->SetValue('TVHActiveRecording', false);
        }
    }
}
