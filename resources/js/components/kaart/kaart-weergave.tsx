import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { AttributionControl, MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';

/** Een punt op de kaart: kleur en teller bepaalt de pagina, de inhoud van de popup ook. */
export type KaartPunt = {
    sleutel: string;
    lat: number;
    lng: number;
    kleur: 'klant' | 'eigen' | 'zonder' | 'aanmelding';
    teller?: number;
    titel: string;
    popup: ReactNode;
};

const KLEUR: Record<KaartPunt['kleur'], string> = {
    klant: 'background:#614ED9;border:3px solid #fff',
    eigen: 'background:linear-gradient(135deg,#D9303E,#614ED9);border:3px solid #fff',
    zonder: 'background:#8A86A3;border:3px solid #fff',
    aanmelding: 'background:#fff;border:3px dashed #D97706',
};

function icoon(p: KaartPunt): L.DivIcon {
    const teller = p.teller
        ? `<span style="position:absolute;top:-8px;right:-10px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#D9303E;color:#fff;font:700 11px/18px Montserrat,Arial;text-align:center;box-shadow:0 0 0 2px #fff">${p.teller}</span>`
        : '';

    return L.divIcon({
        className: '',
        iconSize: [26, 26],
        iconAnchor: [13, 13],
        popupAnchor: [0, -14],
        html: `<span style="position:relative;display:block;width:26px;height:26px;border-radius:50%;box-shadow:0 2px 6px rgba(46,43,72,.4);${KLEUR[p.kleur]}">${teller}</span>`,
    });
}

/** Zoom zo dat alle punten in beeld zijn; bij een gekozen punt daarheen vliegen. */
function Beeld({ punten, focus }: { punten: KaartPunt[]; focus: KaartPunt | null }) {
    const kaart = useMap();
    const sleutel = punten.map((p) => p.sleutel).join(',');

    useEffect(() => {
        if (punten.length === 1) {
            kaart.setView([punten[0].lat, punten[0].lng], 13);
        } else if (punten.length > 1) {
            kaart.fitBounds(L.latLngBounds(punten.map((p) => [p.lat, p.lng])), { padding: [40, 40], maxZoom: 13 });
        }
        // Alleen opnieuw passen als de verzameling punten verandert (filters), niet bij elke render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sleutel]);

    useEffect(() => {
        if (focus) {
            kaart.flyTo([focus.lat, focus.lng], Math.max(kaart.getZoom(), 14), { duration: 0.6 });
        }
    }, [focus, kaart]);

    return null;
}

/** De kaart zelf (Leaflet met de pastelkaart van PDOK/Kadaster). Alleen in de browser geladen. */
export default function KaartWeergave({ punten, focus }: { punten: KaartPunt[]; focus: KaartPunt | null }) {
    return (
        <MapContainer center={[52.6, 5.6]} zoom={8} scrollWheelZoom attributionControl={false} className="mb-kaart h-full w-full rounded-2xl" data-test="kaart">
            <AttributionControl prefix={false} />
            <TileLayer
                url="https://service.pdok.nl/brt/achtergrondkaart/wmts/v2_0/pastel/EPSG:3857/{z}/{x}/{y}.png"
                attribution='Kaartgegevens &copy; <a href="https://www.kadaster.nl">Kadaster</a>'
                maxZoom={19}
            />
            {punten.map((p) => (
                <Marker key={p.sleutel} position={[p.lat, p.lng]} icon={icoon(p)} title={p.titel}>
                    <Popup>{p.popup}</Popup>
                </Marker>
            ))}
            <Beeld punten={punten} focus={focus} />
        </MapContainer>
    );
}
