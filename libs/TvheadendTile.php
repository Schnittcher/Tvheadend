<?php

declare(strict_types=1);

//Gemeinsame Bausteine der Kacheln: setzt gemeinsames Stylesheet, Hilfsfunktionen, das HTML des Moduls und die
//Startdaten zusammen. Aussehen und Aufbau folgen standards/TILE_VISUALIZATION.md des Regelwerks.
trait TvheadendTile
{
    //Ab Symcon 9.1 zeigt die Kachel ihr HTML auch im Vollbild, davor nur in der normalen Kachel.
    protected function EnableTile(): void
    {
        $this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') ? INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN : INSTANCE_VISUALIZATION_TYPE_HTML);
    }

    //HTML der Kachel. Die module.html des Moduls definiert handleMessage(json), die Startdaten gehen am Ende mit.
    protected function RenderTile(string $Directory, array $Data): string
    {
        $css = file_get_contents(__DIR__ . '/tile.css');
        $js = file_get_contents(__DIR__ . '/tile.js');
        $html = file_get_contents($Directory . '/module.html');
        //json_encode zweimal: handleMessage bekommt einen JSON-Text wie bei UpdateVisualizationValue.
        return '<style>' . $css . '</style><script>' . $js . '</script>' . $html
            . '<script>handleMessage(' . json_encode(json_encode($Data)) . ');</script>';
    }

    //Schickt neue Daten an alle geöffneten Kacheln der Instanz.
    protected function PushTile(array $Data): void
    {
        $this->UpdateVisualizationValue(json_encode($Data));
    }

    //Übersetzte Texte für die Kachel: Schlüssel der Kachel => Text im Quellcode (Englisch).
    protected function TileTexts(array $Keys): array
    {
        $texts = [];
        foreach ($Keys as $key => $caption) {
            $texts[$key] = $this->Translate($caption);
        }
        return $texts;
    }
}
