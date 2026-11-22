<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\CourseDay;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demodata academy (docs/02 flow 3): een cursusdag, 3 lessen, 2 oefenopdrachten en studente Lisa. Geen echte mensen.
 * Los aan te roepen en veilig om vaker te draaien: php artisan db:seed --class=DemoAcademySeeder
 */
class DemoAcademySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, MessageTemplateSeeder::class]);

        $dag = CourseDay::firstOrCreate(['title' => 'Basis: video maken met je telefoon', 'location_name' => 'Hotel Wadzicht'], [
            'date' => today()->next('Saturday'), 'starts_at' => '10:00', 'ends_at' => '16:00',
            'address' => 'Noorderhaven 1', 'city' => 'Harlingen', 'max_participants' => 16, 'teacher_name' => 'Sophie de Vries',
            'price_cents' => (int) config('academy.price_cents'), 'exam_required' => (bool) config('academy.exam_required'), 'status' => CourseDay::OPEN,
            'notes' => 'Je telefoon met minimaal 10 GB vrij, een oplader en als je hebt een klein statief. Parkeren kan gratis achter het hotel.',
        ]);

        $lessen = [
            ['Licht en beeldopbouw', 8, "Goed licht maakt het verschil. Film met het raam **voor** je, niet achter je.\n\n- Zoek daglicht\n- Houd je telefoon staand (9:16)\n- Laat je onderwerp het kader vullen"],
            ['Monteren op je telefoon', 9, "Knip op het ritme van je muziek en voeg ondertitels toe.\n\n1. Kies je beste 3 seconden als begin\n2. Knip alles weg wat niet nodig is\n3. Exporteer in 9:16"],
            ['Werken met een onderneming', 11, "Een samenwerking begint met de briefing. Lees hem goed en stel vragen voor je komt.\n\nVermeld altijd dat het een betaalde samenwerking is."],
        ];
        foreach ($lessen as $i => [$titel, $minuten, $tekst]) {
            Lesson::firstOrCreate(['title' => $titel], ['body' => $tekst, 'minutes' => $minuten, 'position' => $i + 1]);
        }

        Assignment::firstOrCreate(['title' => 'Een hook van 3 seconden'], [
            'description' => 'Film drie verschillende openingen van maximaal 3 seconden voor dezelfde zaak. Welke pakt het snelst?',
            'criteria' => [['label' => 'Hook', 'hint' => 'Pakt in de eerste 2 seconden'], ['label' => 'Beeld', 'hint' => 'Stabiel, 9:16']],
            'position' => 1,
        ]);
        Assignment::firstOrCreate(['title' => 'Product in beeld'], [
            'description' => 'Laat een product of gerecht zien in een video van maximaal 30 seconden, met een duidelijke oproep aan het eind.',
            'criteria' => [['label' => 'Licht', 'hint' => 'Daglicht, geen harde schaduw'], ['label' => 'Boodschap', 'hint' => 'Duidelijk wat de zaak biedt'], ['label' => 'CTA', 'hint' => 'Oproep aan het eind']],
            'position' => 2,
        ]);

        $lisa = User::firstOrCreate(['email' => 'lisa@demo.mebranding.nl'], ['name' => 'Lisa Hoekstra', 'password' => Str::password(40)]);
        $lisa->email_verified_at ?? $lisa->markEmailAsVerified();
        $lisa->assignRole('Student');
        Enrollment::firstOrCreate(['course_day_id' => $dag->id, 'user_id' => $lisa->id], [
            'status' => Enrollment::INGESCHREVEN, 'checkin_code' => Str::random(32), 'phone' => '0612345678', 'city' => 'Franeker',
            'tiktok' => 'lisa.filmt', 'instagram' => 'lisa.filmt', 'birthdate' => '2001-05-14',
        ]);

        // In het voorbeeld doet Mirthe het academybeheer (docs/02).
        User::where('email', 'mirthe@demo.mebranding.nl')->first()?->assignRole('Academybeheer');
    }
}
