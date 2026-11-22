<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Les in de online leeromgeving. Inhoud in markdown. */
class Lesson extends Model
{
    protected $fillable = ['title', 'body', 'position', 'minutes'];

    /** Veilige HTML uit de markdown: geen eigen HTML, geen javascript-links. */
    public function html(): string
    {
        return (string) Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
