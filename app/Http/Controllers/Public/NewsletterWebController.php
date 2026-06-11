<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterWebController extends Controller
{
    public function show(Newsletter $newsletter): Response
    {
        $newsletter->load([
            'municipality',
            'summaries' => fn ($q) => $q->orderByPivot('position'),
        ]);

        return Inertia::render('Newsletter/Web', [
            'pageTitle' => $newsletter->subject,
            'newsletter' => [
                'id' => $newsletter->id,
                'subject' => $newsletter->subject,
                'intro' => $newsletter->intro,
                'municipality' => $newsletter->municipality->only('id', 'slug', 'name'),
                'summaries' => $newsletter->summaries->map(fn ($s) => [
                    'id' => $s->id,
                    'level' => $s->level->value,
                    'title' => $s->title,
                    'body' => $s->body,
                    'position' => $s->pivot->position,
                ])->values(),
            ],
        ]);
    }
}
