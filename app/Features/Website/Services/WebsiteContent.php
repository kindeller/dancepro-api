<?php

namespace App\Features\Website\Services;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Website\Models\WebsiteBiography;
use App\Features\Website\Models\WebsitePage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteContent
{
    public function pages()
    {
        return WebsitePage::orderBy('id')->get();
    }

    public function helpArticles()
    {
        return CustomerHelpArticle::orderBy('category')->orderBy('sort_order')->paginate(30);
    }

    public function page(string $slug, bool $preview = false): array
    {
        $page = WebsitePage::where('slug', $slug)->firstOrFail();
        abort_unless($preview || $page->published !== null, 404);

        return ['page' => $page, 'content' => $preview ? $page->draft : $page->published, 'preview' => $preview,
            ...($slug === 'home' ? app(WebsiteEvents::class)->homepage() : ['competitions' => collect(), 'concertStudios' => collect()]),
            'bios' => $slug === 'about' ? WebsiteBiography::whereNotNull('published')->whereHas('crewProfile.user', fn ($q) => $q->where('is_active', true))->orderBy('sort_order')->get() : collect()];
    }

    public function events(?string $search)
    {
        return SchedulingEvent::with(['studio', 'competitionContact'])->when($search, fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_').'%'))
            ->orderByDesc('event_date')->paginate(30)->withQueryString();
    }

    public function setEventVisibility(SchedulingEvent $event, bool $visible): void
    {
        if ($visible && $event->cancelled_at) {
            throw ValidationException::withMessages(['website_visible' => 'Cancelled events cannot be shown on the website.']);
        }
        $event->forceFill(['website_visible' => $visible, 'website_hidden' => ! $visible])->save();
    }

    public function savePage(WebsitePage $page, array $input, User $user): void
    {
        $content = collect($input)->only(['eyebrow', 'heading', 'description', 'body', 'sections'])->all();
        $content['sections'] = array_values($content['sections'] ?? []);
        $content['image'] = $this->image($input, $page->draft['image'] ?? null);
        $page->draft = $content;
        if ($input['action'] === 'publish') {
            $page->published = $content;
            $page->published_at = now();
            $page->published_by = $user->id;
        }
        if ($input['action'] === 'hide') {
            $page->published = null;
            $page->published_at = null;
        }
        $page->save();
    }

    public function biographies()
    {
        return CrewProfile::with(['user', 'websiteBiography'])->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->orderBy('preferred_name')->paginate(30);
    }

    public function pendingBios(): int
    {
        return CrewProfile::whereNotNull('biography')->where('biography', '!=', '')->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q->whereDoesntHave('websiteBiography')->orWhereHas('websiteBiography', fn ($q) => $q->whereNull('reviewed_source')->orWhereColumn('reviewed_source', '!=', 'crew_profiles.biography')))->count();
    }

    public function biography(CrewProfile $crew): array
    {
        $bio = $crew->websiteBiography;

        return ['crew' => $crew, 'bio' => $bio, 'draft' => $bio?->draft ?? ['name' => $crew->preferred_name, 'role' => 'DancePro crew', 'body' => $crew->biography, 'image' => null], 'sourceHash' => hash('sha256', (string) $crew->biography)];
    }

    public function saveBiography(CrewProfile $crew, array $input, User $user): void
    {
        DB::transaction(function () use ($crew, $input, $user) {
            $crew = CrewProfile::whereKey($crew->id)->lockForUpdate()->firstOrFail();
            if ($input['action'] === 'publish' && (! hash_equals(hash('sha256', (string) $crew->biography), $input['source_hash']) || ! $crew->user->is_active)) {
                throw ValidationException::withMessages(['body' => 'The crew biography changed or the account became inactive. Reload and review it before publishing.']);
            }
            $bio = WebsiteBiography::firstOrNew(['crew_profile_id' => $crew->id]);
            $content = collect($input)->only(['name', 'role', 'body'])->all();
            $content['image'] = $this->image($input, $bio->draft['image'] ?? null);
            $bio->draft = $content;
            $bio->sort_order = $input['sort_order'];
            if ($input['action'] === 'publish') {
                $bio->published = $content;
                $bio->reviewed_source = $crew->biography;
                $bio->published_at = now();
                $bio->published_by = $user->id;
            }
            if ($input['action'] === 'hide') {
                $bio->published = null;
                $bio->published_at = null;
                $bio->reviewed_source = $crew->biography;
            }
            $bio->save();
        });
    }

    public function saveHelp(?CustomerHelpArticle $article, array $input): CustomerHelpArticle
    {
        $content = collect($input)->except('action')->all();
        $article ??= new CustomerHelpArticle(['uuid' => (string) Str::uuid(), 'slug' => 'help-'.Str::lower(Str::random(16)), ...$content, 'is_published' => false]);
        $article->draft = $content;
        if ($input['action'] === 'publish') {
            $article->fill([...$content, 'is_published' => true]);
        }
        if ($input['action'] === 'hide') {
            $article->is_published = false;
        }
        $article->save();

        return $article;
    }

    private function image(array $input, ?string $current): ?string
    {
        if (($input['image'] ?? null) instanceof UploadedFile) {
            return $input['image']->store('website', config('uploads.public_disk'));
        }

        return ($input['remove_image'] ?? false) ? null : $current;
    }
}
