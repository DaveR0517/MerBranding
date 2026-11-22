<?php

namespace App\Http\Controllers\Team\Studio;

use App\Http\Controllers\Controller;
use App\Models\DeliverableVersion;
use App\Models\StudioDesign;
use App\Models\StudioMedia;
use App\Models\StudioTemplate;
use App\Models\UsageRight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Basis voor MeStudio (team; het recht studio.gebruiken staat op de routegroep). Eén plek voor rechten, formaten, voorvertoningen
 * en de controle van de lagen-JSON, zodat ontwerpen en sjablonen dezelfde regels volgen.
 */
abstract class StudioController extends Controller
{
    /** Sjabloon gebruiken: een nieuw ontwerp als kopie, het sjabloon zelf blijft onaangeroerd. */
    protected function nieuwVanSjabloon(StudioTemplate $sjabloon, Request $request): StudioDesign
    {
        abort_if($sjabloon->archived_at !== null, 422, 'Dit sjabloon is gearchiveerd.');

        return StudioDesign::create([
            'user_id' => $request->user()->id, 'template_id' => $sjabloon->id, 'name' => $sjabloon->name,
            'format' => $sjabloon->format, 'width' => $sjabloon->width, 'height' => $sjabloon->height,
            'pages' => $sjabloon->pages, 'status' => 'concept',
        ]);
    }

    /** @return array<string, array{naam: string, breedte: int, hoogte: int}> */
    protected function formaten(): array
    {
        return config('studio.formaten');
    }

    /** Regels voor de lagen per pagina. De vorm van een laag bepaalt de editor; hier alleen grenzen. */
    protected function paginaRegels(): array
    {
        return [
            'pages' => ['required', 'array', 'min:1', 'max:10'],
            'pages.*.achtergrond' => ['nullable', 'string', 'max:20'],
            'pages.*.lagen' => ['present', 'array', 'max:80'],
            'pages.*.lagen.*.id' => ['required', 'string', 'max:40'],
            'pages.*.lagen.*.soort' => ['required', 'in:tekst,foto,video,vorm,logo,verloop'],
            'voorvertoning' => ['nullable', 'string', 'max:3000000'],
        ];
    }

    /** Bewaar een kleine voorvertoning (data-URL uit de browser) op de privé-disk. */
    protected function bewaarVoorvertoning(?string $dataUrl, string $naam): ?string
    {
        if (! $dataUrl || ! preg_match('#^data:image/(jpeg|png);base64,(.+)$#', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || @getimagesizefromstring($bytes) === false) {
            return null;
        }
        $pad = "studio/voorvertoningen/{$naam}.".($m[1] === 'png' ? 'png' : 'jpg');
        Storage::disk('local')->put($pad, $bytes);

        return $pad;
    }

    protected function ontwerp(StudioDesign $d, bool $metLagen = false): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'format' => $d->format,
            'format_naam' => $this->formaten()[$d->format]['naam'] ?? $d->format,
            'width' => $d->width,
            'height' => $d->height,
            'aantal_paginas' => count($d->pages ?? []),
            'status' => $d->status,
            'scheduled_at' => $d->scheduled_at?->toIso8601String(),
            'caption' => $d->caption,
            'feed_position' => $d->feed_position,
            'post_url' => $d->post_url,
            'posted_at' => $d->posted_at?->toIso8601String(),
            'preview' => $d->preview_path ? route('team.studio.ontwerpen.voorvertoning', [$d, 'v' => $d->updated_at?->timestamp]) : null,
            'maker' => $d->user?->name,
            'updated_at' => $d->updated_at?->toIso8601String(),
            'heeft_video' => $d->heeftVideo(),
            // Zonder voorvertoning tekent de browser het ontwerp zelf (klein).
            'pages' => $metLagen || ! $d->preview_path ? $d->pages : null,
        ];
    }

    protected function sjabloon(StudioTemplate $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'format' => $s->format,
            'format_naam' => $this->formaten()[$s->format]['naam'] ?? $s->format,
            'width' => $s->width,
            'height' => $s->height,
            'aantal_paginas' => count($s->pages ?? []),
            'pages' => $s->pages,
            'is_builtin' => $s->is_builtin,
            'archived' => $s->archived_at !== null,
            'maker' => $s->user?->name,
            'vakken' => collect($s->pages)->flatMap(fn ($p) => $p['lagen'] ?? [])->where('vak', true)->count(),
        ];
    }

    protected function media(StudioMedia $m): array
    {
        return [
            'id' => $m->id, 'kind' => $m->kind, 'name' => $m->name, 'mime' => $m->mime, 'size' => $m->size,
            'width' => $m->width, 'height' => $m->height, 'url' => route('team.studio.mediatheek.bestand', $m),
            'created_at' => $m->created_at?->toIso8601String(), 'maker' => $m->user?->name,
        ];
    }

    /** Wat de editor nodig heeft om bronnen te kiezen: mediatheek, video's van creators en de logo's. */
    protected function bronnen(): array
    {
        return [
            'media' => StudioMedia::with('user')->latest('id')->limit(300)->get()->map(fn ($m) => $this->media($m)),
            'creatorVideos' => $this->creatorVideos(),
            'logos' => self::logos(),
        ];
    }

    /** Logo's uit public/brand (alleen svg). */
    public static function logos(): array
    {
        return collect(glob(public_path('brand/*logo*.svg')) ?: [])
            ->merge(glob(public_path('brand/*icoon*.svg')) ?: [])
            ->map(fn ($pad) => ['naam' => basename($pad), 'label' => str(basename($pad, '.svg'))->after('mebranding-')->replace('-', ' ')->ucfirst()->toString(), 'url' => '/brand/'.basename($pad)])
            ->values()->all();
    }

    /**
     * Goedgekeurde video's uit samenwerkingen, met de gebruiksrechten die er zijn.
     * Gebruik voor eigen marketing vraagt toestemming van creator en ondernemer (open punt 7): het scherm waarschuwt, beslist niets.
     */
    protected function creatorVideos(): array
    {
        $versies = DeliverableVersion::with(['collaboration.company', 'collaboration.creator.user'])
            ->where('outcome', 'goedgekeurd')->latest('id')->limit(100)->get();
        $rechten = UsageRight::whereIn('deliverable_version_id', $versies->pluck('id'))
            ->orWhereIn('collaboration_id', $versies->pluck('collaboration_id'))->get();

        return $versies->map(fn (DeliverableVersion $v) => [
            'id' => $v->id,
            'titel' => $v->collaboration?->title,
            'nummer' => $v->collaboration?->number,
            'bedrijf' => $v->collaboration?->company?->name,
            'creator' => $v->collaboration?->creator?->user?->name,
            'url' => route('video', $v),
            'mime' => $v->mime,
            'gebruiksrechten' => $rechten
                ->filter(fn ($r) => $r->deliverable_version_id === $v->id || ($r->deliverable_version_id === null && $r->collaboration_id === $v->collaboration_id))
                ->map(fn ($r) => ['kind' => $r->kind, 'starts_on' => $r->starts_on?->toDateString(), 'ends_on' => $r->ends_on?->toDateString(), 'status' => $r->status, 'is_proposal' => $r->is_proposal])
                ->values()->all(),
        ])->all();
    }
}
