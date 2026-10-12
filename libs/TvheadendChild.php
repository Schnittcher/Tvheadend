<?php

declare(strict_types=1);

require_once __DIR__ . '/TvheadendConstants.php';

//Gemeinsame Bausteine der Kind-Instanzen (Server, DVR, ...): Anfragen über das Gateway und
//Darstellung von Variablen.
trait TvheadendChild
{
    //Ruft eine API-Funktion über das Gateway auf. Liefert code, data und error.
    protected function ApiRequest(string $Path, array $Params = [], string $Method = 'GET'): array
    {
        if (!$this->HasActiveParent()) {
            return ['code' => 0, 'data' => null, 'error' => 'No active gateway'];
        }
        $answer = $this->SendDataToParent(json_encode([
            'DataID' => TVH_DATA_ID,
            'Action' => TVH_ACTION_REQUEST,
            'Path'   => $Path,
            'Params' => $Params,
            'Method' => $Method
        ]));
        $result = json_decode($answer, true);
        return is_array($result) ? $result : ['code' => 0, 'data' => null, 'error' => 'No answer'];
    }

    //Lädt eine Datei vom Server (zum Beispiel ein Senderlogo). Liefert code, type und body (binär).
    protected function ApiRaw(string $Path): array
    {
        if (!$this->HasActiveParent()) {
            return ['code' => 0, 'type' => '', 'body' => ''];
        }
        $answer = $this->SendDataToParent(json_encode(['DataID' => TVH_DATA_ID, 'Action' => TVH_ACTION_RAW, 'Path' => $Path]));
        $result = json_decode($answer, true);
        if (!is_array($result)) {
            return ['code' => 0, 'type' => '', 'body' => ''];
        }
        $result['body'] = base64_decode((string) ($result['body'] ?? ''), true) ?: '';
        return $result;
    }

    //Version, Rechte und Fähigkeiten des Servers aus der letzten Abfrage des Gateways.
    protected function GetServerInfo(): array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }
        $answer = $this->SendDataToParent(json_encode(['DataID' => TVH_DATA_ID, 'Action' => TVH_ACTION_INFO]));
        $info = json_decode($answer, true);
        return is_array($info) ? $info : [];
    }

    //Nachkommastellen ($Digits) gibt es nur bei Zahlen, bei Text lehnt Symcon den Parameter ab.
    protected function ValuePresentation(string $Suffix = '', ?int $Digits = null): array
    {
        $presentation = [
            'PRESENTATION' => TVH_PRESENTATION_VALUE,
            'SUFFIX'       => $Suffix
        ];
        if ($Digits !== null) {
            $presentation['DIGITS'] = $Digits;
        }
        return $presentation;
    }

    protected function BooleanPresentation(string $TrueCaption, string $FalseCaption): array
    {
        return [
            'PRESENTATION' => TVH_PRESENTATION_VALUE,
            'OPTIONS'      => json_encode([
                ['Value' => false, 'Caption' => $this->Translate($FalseCaption), 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 16077123, 'ContentColorActive' => false],
                ['Value' => true, 'Caption' => $this->Translate($TrueCaption), 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 1692672, 'ContentColorActive' => false]
            ])
        ];
    }

    //Bedienbare Schalter brauchen die Darstellung "Schalter", die Werteanzeige ist nur lesbar.
    protected function SwitchPresentation(): array
    {
        return ['PRESENTATION' => TVH_PRESENTATION_SWITCH];
    }

    protected function DateTimePresentation(): array
    {
        return [
            'PRESENTATION'    => TVH_PRESENTATION_DATETIME,
            'DATE'            => 1,
            'TIME'            => 1,
            'DAY_OF_THE_WEEK' => false
        ];
    }

    protected function HtmlPresentation(): array
    {
        return ['PRESENTATION' => TVH_PRESENTATION_WEB_CONTENT];
    }

    //Tabelle für HTML-Variablen. Texte aus dem Server werden maskiert.
    protected function BuildTable(array $Header, array $Rows): string
    {
        $html = '<table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr>';
        foreach ($Header as $caption) {
            $html .= '<th style="padding:4px 8px">' . htmlspecialchars($this->Translate($caption)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (count($Rows) === 0) {
            $html .= '<tr><td style="padding:4px 8px" colspan="' . count($Header) . '">-</td></tr>';
        }
        foreach ($Rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td style="padding:4px 8px">' . htmlspecialchars((string) $cell) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }
}
