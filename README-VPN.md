# VPN iPhone <-> FRITZ!Box (mit DynDNS `home.rski.org`)

Kurzanleitung für eine WireGuard-VPN-Verbindung zwischen iPhone und FRITZ!Box.

## Voraussetzungen

- FRITZ!OS 7.50 oder neuer (WireGuard verfügbar)
- DynDNS-Hostname `home.rski.org` zeigt auf die öffentliche IP der FRITZ!Box
- iPhone mit installierter WireGuard-App

## 1. VPN in der FRITZ!Box anlegen

1. In der FRITZ!Box: `Internet -> Freigaben -> VPN (WireGuard)`.
2. `VPN-Verbindung hinzufügen` wählen.
3. Typ `Einzelgerät (Smartphone)` auswählen.
4. Konfiguration erzeugen und als QR-Code oder Datei bereitstellen.

## 2. VPN auf dem iPhone einrichten

1. WireGuard-App öffnen und Tunnel per QR-Code oder Datei importieren.
2. Im importierten Tunnel den Peer-Endpoint prüfen:
   `Endpoint = home.rski.org:51820`
3. Tunnel speichern und aktivieren.

## 3. Verbindung testen

1. iPhone aus dem Heim-WLAN nehmen (Mobilfunk nutzen).
2. WireGuard-Tunnel aktivieren.
3. Testen, ob Heimnetz erreichbar ist (z. B. `http://fritz.box`).

## Fehlerbehebung (kurz)

- `home.rski.org` muss auf die aktuelle WAN-IP auflösen.
- FRITZ!Box muss von außen erreichbar sein (kein blockierendes Upstream-NAT/DS-Lite-Problem).
- WireGuard nutzt standardmäßig UDP-Port `51820`.
