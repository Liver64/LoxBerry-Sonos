## Sonos http Befehle rund um Loxone


### Einleitung 

*sonos2* ist eine Sammlung von Befehlen welche die php class PHPSonos nutzt um Sonos player via http Befehle von Loxone aus zu steuern. 

### Installation 

Sicherstellen dass das Verzeichnis in dem die Skript Dateien liegen vom php fähigen Webserver ausgeführt werden können (DEIN_VERZEICHNIS)

### Konfiguration 
Die Konfiguration wird in der config.php durchgeführt. 
Füge in der config.php deine Sonos Zonen inkl. IP-Adressen und auch deine gewünschten Radiostation hinzu.
Der Pfad für die Nachrichten bzw. Text-To-Speech (TTS) Dateien muss für Sonos erreichbar sein.
Für TTS benötigst du noch einen API-Key von VoicesRSS.org oder ivona.com, alternativ kann auch Mac OS X verwendet werden.

##### TTS OSX
Text-to-Speech kann auch ohne Cloud-Services mit Mac OS X umgesetzt werden. Hierzu empfiehlt es sich, die Server.app von Apple auf OS X 
installiert zu haben. Dieses Script kann direkt auf dem OS X Server als Website betrieben werden. Da der Mac nur AIFF Soundfiles erzeugt, 
die von SONOS nicht abgespielt werden können, benötigst du außerdem noch den LAME MP3 Encoder. Dieser lässt sich über Homebrew sehr 
einfach nach installieren.

Unbedingt sicherstellen das dein Webserver User Schreibrechte für DEIN_VEREICHNIS besitzt.

Syntax Beispiele für Browser:

##### Setzt den Playmode. Erlaubte Möglichkeiten sind: NORMAL, REPEAT_ALL, SHUFFLE und SHUFFLE_NOREPEAT
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=playmode&playmode=normal

##### Entfernt Track Nr. 1 von der laufenden Playliste
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=remove&track=1

##### Setzt die Lautstärke fix auf 30 %
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=volume&volume=30

##### Spielt nächsten Radio Sender aus der Liste in config.php
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=nextradio

##### Spielt vorherigen Radio Sender aus der Liste in config.php
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=previousradio

##### Startet play mit Lautstärke 20% und der Lautstärkeanhebung 'alarm'
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=play&Volume=20&rampto=alarm

##### Folgende action sind möglich: toggle, stop, next, pause, previous, rewind, 
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=stop

##### Laustärke wird innerhalb 17 Sekunden linear auf Null gefahren (Art Schlummerfunktion) und Wiedergabe gestoppt
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=softstop

##### Setzt angegebene Zone auf mute wenn Wert true, unmute mit Wert false
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=mute&mute=true

##### Lautstärke leiser (die Schritt Vorgabe wird in Prozent in der config.php gepflegt)
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=volumedown

##### Lautstärke lauter (die Schritt Vorgabe wird in Prozent in der config.php gepflegt)
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=volumeup

##### Löschen der laufenden Playliste
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=clearqueue

##### Spielt MP3 Datei ohne jingle/gong vor der Durchsage
Stoppt gegenwärtig laufende Playliste/Radiostation, speichert die Lautstärke und Playliste/Radiostation, setzt die Lautstärke auf 30%, spielt die Datei 1.mp3 die im Ordner 'messagepath' gespeichert ist,
nach dem Abspielen wird die vorherige Playliste/Radiostation wieder geladen und mit der vorher gespeicherten Lautstärke wieder abgespielt.
Wenn aktuell nichts läuft, denn noch eine Playliste/Radiostation in der Zone aktiv ist, bleibt die Zone stumm und die Playliste/Radiostation wird nur geladen.
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&volume=30&action=sendmessage&messageid=1

##### Spielt MP3 Datei mit jingle/gong vor der Durchsage
Stoppt gegenwärtig laufende Playliste/Radiostation, speichert die Lautstärke und Playliste/Radiostation, setzt die Lautstärke auf 40%, spielt vorher ein Jingle/Gong MP3 ab, spielt dann die Datei 1.mp3 die im Ordner 'messagepath' gespeichert ist, nach dem Abspielen wird die vorherige Playliste/Radiostation wieder geladen und mit der vorher gespeicherten Lautstärke wieder abgespielt. Wenn aktuell nichts läuft, denn noch eine Playliste/Radiostation in der Zone aktiv ist, bleibt die Zone stumm und die Playliste/Radiostation wird nur geladen.
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&volume=40&playgong=yes&action=sendmessage&messageid=1

##### Spielt TTS Text ohne jingle/gong vor der Durchsage
Stoppt gegenwärtig laufende Playliste/Radiostation, speichert die Lautstärke und Playliste/Radiostation, 
setzt die Lautstärke auf 20%, wandelt dann den text in MP3 mit Hillfe von TTS um und 
spielt Sie ab, nach dem Abspielen wird die vorherige Playliste/Radiostation wieder geladen und mit der vorher gespeicherten 
Lautstärke wieder abgespielt.
Wenn aktuell nichts läuft, denn noch eine Playliste/Radiostation in der Zone aktiv ist, bleibt die Zone stumm
und die Playliste/Radiostation wird nur geladen.

##### Für Loxone kann auch beim analogen Ausgangsbefehl der Parameter <v> für Durchsagen verwendet werden.
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&volume=20&action=sendmessage&text=Dies ist ein Test

##### Fügt der angegebenen Zone eine weitere hinzu
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=addmember&member=ANDERE_ZONE

##### Löscht aus der Gruppe die angegebenen Zone wieder
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=removemember&member=ANDERE_ZONE

##### Lädt die angegebene Sonos Playliste und spielt Sie mit Lautstärke 15% ab
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=sonosplaylist&playlist=NAME_DER_PLAYLISTE&volume=15

##### Lädt die angegebene Radioliste unter "Meine Radiosender" aus Sonos 
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=radioplaylist&playlist=NAME_DER_RADIOSTATION&volume=15

##### ändert die Lautstärkeanhebung bei Play (siehe config.php) sleep, alarm und auto sind erlaubt
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=play&rampto=sleep

##### Crossfade 1 schaltet Überblenden ein, 0 schaltet Überblenden aus
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=crossfade&crossfade=1

##### Stoppt alle Zonen
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=stop&stopall

##### Gruppiert alle Zonen
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=group

##### Hebt die Gruppierung wieder auf
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=ungroup

### weather-to-speech (in Verbindung mit wunderground.com [NUR API key benötigt])
je nach Tageszeit werden unterschiedliche Wettervorhersagen erstellt und per TTS durchgegeben
Regenwahrscheinlichkeit und Windwarnung nur ab überschreiten von Grenzwerten (siehe config.php)
Ansagetexte können in der Datei 'w2s.php' individualisiert werden (VORSICHT!!
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=sendmessage&weather&volume=30

### clock-to-speech (Die Uhrzeit + Anrede werden über TTS ausgegeben)
Ansagetexte können in der Datei 'c2s.php' individualisiert werden (VORSICHT!!
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=sendmessage&clock&volume=20



### Syntax zur Info oder Fehlersuche (nur in Browser zu nutzen)
folgende actions könnengenutzt werden: getmedianfo, getpositioninfo, gettransportsettings, gettransportinfo,
getradiotimegetnowplaying, getvolume, radiourl, titelinfo, getledstate, getzoneattributes
http://DEINE_IP/DEIN_VERZEICHNIS/sonos2.php?zone=DEINE_ZONE&action=getmedianfo

##### Zur detaillierten Fehlersuche kann auch folgende Syntax genutzt werden:
In der Syntax 'sonos2.php' durch 'index.php' ersetzen
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=play





### Loxone Integration

Syntax Beispiel wie der Browser Befehl zu teilen ist um in Loxone verwendet zu werden:
Ausgangsverbinder erstellen = http://DEINE_IP		
und Haken bei "Verbindung nach senden schließen" setzen
Ausgangsbefehl hinzufügen = /DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=play
etc.

Titel und Interpret können jetzt auch getrennt in Loxone verwendet werden
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=loxgettitel

Der Text Eingangsverbinder für die Kombination lautet: S-Titel<ZONE>
Der Text Eingangsverbinder für den Titel lautet: S-Titelinfo<ZONE>
Der Text Eingangsverbinder für den Interpret lautet: S-Interpretinfo<ZONE>



### NEUE FUNKTIONEN v1.4.9

##### Gibt den gegenwärtigen Mute Status einer Gruppe zurück
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=getgroupmute

##### Setzt den Mute Status für eine Gruppe (1=Mute, 0=Unmute)
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=setgroupmute&mute=1

##### Gibt die gegenwärtige mittlere Lautstärke einer Gruppe zurück
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=getgroupvolume

##### Setzt die angegebene Lautstärke für eine Gruppe auf 40% (Master muss DEINE_ZONE sein)
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=setgroupvolume&volume=40

##### Erhöht die jeweilige Lautstärke je Zone einer Gruppe um 20% (Master muss DEINE_ZONE sein)
ACHTUNG! Basierend auf derzeitiger Lautstärke wird LS um Wert x erhöht (könnte u.U. laut werden)
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=setrelativegroupvolume&volume=20

##### Setzt den Schlummermodus für angegebene Zone auf 5 Minuten. Bei kleiner 10 Minuten nur einstellige Eingabe Bsp.:5 für 5 Minuten) 
generell erlaubte Eingabe: 1-59
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=sleeptimer&timer=5


### NEUE FUNKTIONEN v1.5.0

Offline T2S Engine basierend auf Mac OS X hinzugefügt



### NEUE FUNKTIONEN v1.5.1

##### T2S Durchsagen an Gruppe von Zonen
Gruppiert aufgelistete Zonen der Syntax, speichert vorher den jeweiligen Zustand jeder einzelnen Zone, spielt dann 
anschließend eine T2S ab, löst anschließend die Gruppe wieder auf und stellt die Originalzustände je Zone wieder her.
Bsp.: Bei der unten aufgeführten Syntax werden zuerst die gegenwärtigen Zustände der Zonen wohnen, küche und schlafen in einer JSON Datei gespeichert,
anschließend werden die Zonen gruppiert, die T2S Lautstärke aus der config.php je Zone gesetzt, dann aber die Lautstärke um 15% zusätzlich angehoben.
ACHTUNG: Der Parameter groupvolume erhöht die Lautstärke je Zone um den angegebenen Wert!!!!!!!!
Dann wird die T2S in der Gruppe abgespielt und anschließend wird die Gruppe wieder aufgelöst und der Originalzustand je Zone geladen.
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=sendgroupmessage&member=wohnen,kueche,schlafen&text=dies ist ein Test&groupvolume=15

##### Lädt eine Sonos Playliste in eine Gruppe von Zonen
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=groupsonosplaylist&member=wohnen,kueche,schlafen&playlist=<NAME PLAYLISTE>

##### Lädt einen Radiosender in eine Gruppe von Zonen
http://DEINE_IP/DEIN_VERZEICHNIS/index.php?zone=DEINE_ZONE&action=groupradioplaylist&member=wohnen,kueche,schlafen&playlist=<NAME RADIOSENDER>




#### Fehler

keine bekannten  

#### Danke an

thanks to Stefan Nikolaus, Thomas Trautner and Patrick (patriwag) for there coding

#### Link zu Beitrag im Forum

https://www.loxforum.com/forum/german/software-konfiguration-programm-und-visualisierung/18094-sonos-mittels-php-skript-steuern


