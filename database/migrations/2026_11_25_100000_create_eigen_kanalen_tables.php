<?php

use App\Models\MessageTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module eigen kanalen: het eigen Instagram- en TikTok-account van MEBRANDING in het portaal.
 * De koppeling zelf is een SocialAccount met owner_type 'mebranding' (zie SocialAccount::EIGEN).
 * Ruwe cijfers van de API's staan hier; berekende waarden (som over 28 dagen, betrokkenheid) maakt het scherm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('handle'); // Instagram user_id of TikTok open_id
            $table->json('scopes')->nullable()->after('external_id'); // verleende rechten
            $table->dateTime('token_expires_at')->nullable()->after('expires_at'); // toegangstoken (TikTok 24 uur); expires_at = einde koppeling
            $table->json('profiel')->nullable()->after('scopes'); // naam en profielfoto zoals het platform ze laatst gaf
        });

        Schema::create('kanaal_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('extern_id');
            $table->string('soort', 30)->nullable(); // IMAGE, VIDEO, CAROUSEL_ALBUM (Instagram), VIDEO (TikTok)
            $table->string('product', 20)->nullable(); // FEED, REELS, STORY (Instagram)
            $table->text('bijschrift')->nullable();
            $table->text('media_url')->nullable();
            $table->text('miniatuur_url')->nullable();
            $table->text('permalink')->nullable();
            $table->unsignedInteger('duur')->nullable(); // seconden (TikTok)
            $table->dateTime('geplaatst_op')->nullable();
            $table->json('cijfers')->nullable(); // laatste ruwe waarden: views, reach, likes, comments, shares, saved, total_interactions
            $table->dateTime('cijfers_op')->nullable();
            $table->timestamps();
            $table->unique(['social_account_id', 'extern_id']);
            $table->index(['social_account_id', 'geplaatst_op']);
        });

        Schema::create('kanaal_metingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('datum');
            $table->string('metriek', 40); // volgers, volgend, media, likes_totaal, weergaven, bereik, accounts_betrokken, video_weergaven_totaal
            $table->bigInteger('waarde');
            $table->timestamps();
            $table->unique(['social_account_id', 'datum', 'metriek']);
        });

        Schema::create('kanaal_gesprekken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('extern_id')->nullable(); // gesprek-id van Meta (komt niet mee in webhooks)
            $table->string('deelnemer_id'); // Instagram-scoped id van de ander
            $table->string('deelnemer_naam')->nullable();
            $table->dateTime('laatste_bericht_op')->nullable();
            $table->dateTime('laatste_inkomend_op')->nullable(); // begin van het 24-uursvenster
            $table->unsignedInteger('ongelezen')->default(0);
            $table->timestamps();
            $table->unique(['social_account_id', 'deelnemer_id']);
        });

        Schema::create('kanaal_berichten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanaal_gesprek_id')->constrained('kanaal_gesprekken')->cascadeOnDelete();
            $table->string('extern_id')->nullable()->unique(); // mid van Meta
            $table->string('richting', 3); // in, uit
            $table->text('tekst')->nullable();
            $table->json('bijlagen')->nullable();
            $table->dateTime('verzonden_op');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // wie het antwoord stuurde
            $table->timestamps();
            $table->index(['kanaal_gesprek_id', 'verzonden_op']);
        });

        Schema::create('kanaal_reacties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanaal_media_id')->constrained('kanaal_media')->cascadeOnDelete();
            $table->string('extern_id')->unique();
            $table->string('ouder_extern_id')->nullable(); // antwoord op deze reactie
            $table->string('gebruikersnaam')->nullable();
            $table->text('tekst')->nullable();
            $table->unsignedInteger('likes')->default(0);
            $table->boolean('eigen')->default(false); // geplaatst door MEBRANDING
            $table->dateTime('geplaatst_op')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['kanaal_media_id', 'geplaatst_op']);
        });

        // Meldingen aan het team (sjablonen zijn bewerkbaar in Berichtsjablonen; alleen ontbrekende worden aangemaakt).
        foreach ($this->sjablonen() as $key => $s) {
            MessageTemplate::firstOrCreate(['key' => $key], $s);
        }
    }

    public function down(): void
    {
        MessageTemplate::whereIn('key', array_keys($this->sjablonen()))->delete();
        Schema::dropIfExists('kanaal_reacties');
        Schema::dropIfExists('kanaal_berichten');
        Schema::dropIfExists('kanaal_gesprekken');
        Schema::dropIfExists('kanaal_metingen');
        Schema::dropIfExists('kanaal_media');
        Schema::table('social_accounts', fn (Blueprint $table) => $table->dropColumn(['external_id', 'scopes', 'profiel', 'token_expires_at']));
    }

    /** @return array<string, array<string, mixed>> */
    private function sjablonen(): array
    {
        return [
            't-kanaal-bericht' => [
                'role' => 't', 'event' => 'Nieuw bericht op het eigen Instagram-account',
                'channels' => ['inapp' => 'aan', 'email' => 'uit', 'push' => 'uit'],
                'subject' => 'Nieuw Instagram-bericht van {afzender}',
                'email' => ['aanhef' => 'Hoi {voornaam},', 'alineas' => ['{afzender} stuurde een bericht naar het Instagram-account van MEBRANDING: "{tekst}"', 'Antwoord binnen 24 uur, daarna staat Meta geen antwoord meer toe.'], 'knop' => 'Bericht openen'],
                'inapp' => 'Nieuw Instagram-bericht van {afzender}: {tekst}',
            ],
            't-kanaal-reactie' => [
                'role' => 't', 'event' => 'Nieuwe reactie op een post van het eigen Instagram-account',
                'channels' => ['inapp' => 'aan', 'email' => 'uit', 'push' => 'uit'],
                'subject' => 'Nieuwe reactie van {afzender}',
                'email' => ['aanhef' => 'Hoi {voornaam},', 'alineas' => ['{afzender} reageerde op een post van MEBRANDING: "{tekst}"'], 'knop' => 'Reactie openen'],
                'inapp' => 'Nieuwe reactie op Instagram van {afzender}: {tekst}',
            ],
            't-kanaal-plaatsen' => [
                'role' => 't', 'event' => 'Ontwerp uit MeStudio geplaatst op Instagram (of mislukt)',
                'channels' => ['inapp' => 'verplicht', 'email' => 'uit', 'push' => 'uit'],
                'subject' => 'Plaatsen op Instagram: {ontwerp}',
                'email' => ['aanhef' => 'Hoi {voornaam},', 'alineas' => ['"{ontwerp}" {uitkomst}.'], 'knop' => 'Feedplanner openen'],
                'inapp' => '"{ontwerp}" {uitkomst}.',
            ],
            't-kanaal-koppeling' => [
                'role' => 't', 'event' => 'Koppeling van een eigen kanaal werkt niet meer',
                'channels' => ['inapp' => 'verplicht', 'email' => 'aan', 'push' => 'uit'],
                'subject' => 'Koppel {platform} opnieuw',
                'email' => ['aanhef' => 'Hoi {voornaam},', 'alineas' => ['De koppeling met het {platform}-account van MEBRANDING werkt niet meer: {reden}', 'Koppel het account opnieuw onder Eigen kanalen.'], 'knop' => 'Opnieuw koppelen'],
                'inapp' => 'De koppeling met {platform} werkt niet meer. Koppel opnieuw onder Eigen kanalen.',
            ],
        ];
    }
};
