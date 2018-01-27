Das Sonos4Lox Plugin für den LoxBerry ermöglicht die nahezu komplette Steuerung einer Sonos Multiroom Installation über Loxone. Sämtliche gängigen Funktionen/Befehle, die auch aus der Sonos App bekannt sind, stehen zur Verfügung.

Darüber hinaus beinhaltet das Plugin die Möglichkeit Text-to-speech (T2S) Nachrichten auf einer Zone oder einer Gruppe von Zonen abzuspielen. Dabei werden vorher die Zustände jeder Zone gespeichert und nach erfolgter Durchsage wiederhergestellt. Die T2S Texte werden entweder über Online T2S Engines (4 zur Auswahl) oder über Offline T2S Engines (2 zur Auswahl) erstellt und als MP3 im Cache gespeichert. Bereits im Cache befindliche MP3 Files werden nicht wieder zum T2S Provider geschickt, sondern erneut lokal abgespielt.

Außerdem ist es möglich analoge Daten aus Loxone (z.B. Temperaturwerte, etc.) in die Texte mit einzubinden. Es können auch einzelne oft benötigte MP3 Files direkt abgespielt werden (z.B. "Waschmaschine ist fertig", "Das Garagentor ist offen", etc.) ohne ständig die T2S Online Engines zu nutzen. Als sogenannte Addon's stehen derzeit folgende zur Verfügung:

weather-to-speech (nur in Verbindung mit dem Wunderground 4 Loxone Plugin)
Sonos-to-speech (Ansage des gegenwärtigen Titel/Interpreten oder Radiosender)
clock-to-speech (aktuelle Zeitansage)
pollen-to-speech (Aktuelle Vorhersage des Pollenwetters)
weather-warning-to-speech (Aktuelle Wetter Warnhinweise)
time-to-destination (Ansage Fahrzeit und Fahrstrecke)
waste-calendar-to-speech (Ansage der Abfalltermine)
Sämtliche Steuerungsparameter müssen über virtuelle Ausgangsverbinder im MS angelegt werden. Eine Inbound Anbindung an den Miniserver ist über UDP Pakete bzw. virtuelle Texteingänge ebenso enthalten um Informationen wie Titel/Interpret, Play/Stop/Pause, Radio oder Playliste und aktuelle Lautstärke je Zone im MS nutzen zu können.

Das neue Release unterstützt bis zu 51 verschiedene Sprachen mit max. 87 unterschiedlichen Stimmen.

Alle Details zu dem Sonos Plugin befinden sich im LoxWiki





The Loxberry Plugin for Sonos enables you to fully control your Sonos Installation via Loxone. Most likely all functions you know from the Sonos App are available.

In addition to that you can generate text-to-speech voices and play them on a single zone or on a group of zones. Before the speech take place all settings per Zone will be saved and after T2S fully restored. For generating the T2S you can either use Online Services (5 diff. available) or Offline Services (2 diff. available). The resulting MP3 files will be stored in Cache and later on deleted. In case of usage Online Services if a file is already cached the Online Services will not be triggered again. You can also integrate values of Loxone (eg. Temperature values) or you can also use pre-saved MP3 files (“Washing machine is ready” or “The garage is still open”).

(the available Addon’s could only be supported by manual changing the texts inside the files)

All HTTP Requests must be send through usage of virtual Outbounds in MS. The Inbound data transfer takes place using UDP packages as well as Text Inbounds in order to obtain infos like Volume, Artist/Songname, Play/Stop/Pause, Radio/Playlist per Zone to MS.

The new release supports up to 51 different languages by using max. 87 different voices (depending on T2S Engine you are using)

For details please visit http://itools.com/tool/google-translate-web-page-translator, copy in URL of Wiki http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone, select German as source and the language you would like to have Wiki translated.