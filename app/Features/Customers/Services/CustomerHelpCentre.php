<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerHelpArticle;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerHelpCentre
{
    public function adminInsights(): array
    {
        return [
            'unanswered' => DB::table('customer_help_searches')->where('result_count', 0)
                ->selectRaw('query, count(*) as searches, max(created_at) as last_searched_at')
                ->groupBy('query')->orderByDesc('searches')->orderByDesc('last_searched_at')->limit(20)->get(),
            'articleFeedback' => CustomerHelpArticle::query()->where('is_published', true)
                ->leftJoin('customer_help_article_feedback as feedback', 'feedback.customer_help_article_id', '=', 'customer_help_articles.id')
                ->select('customer_help_articles.id', 'customer_help_articles.title', 'customer_help_articles.category')
                ->selectRaw('sum(case when feedback.helpful = 1 then 1 else 0 end) as helpful_count')
                ->selectRaw('sum(case when feedback.helpful = 0 then 1 else 0 end) as unhelpful_count')
                ->groupBy('customer_help_articles.id', 'customer_help_articles.title', 'customer_help_articles.category')
                ->orderByDesc('unhelpful_count')->orderBy('customer_help_articles.sort_order')->get(),
        ];
    }

    public function articles(?string $query, ?User $user = null): Collection
    {
        $query = trim((string) $query);
        $articles = CustomerHelpArticle::query()->where('is_published', true)
            ->when($query !== '', function ($builder) use ($query): void {
                $terms = collect(preg_split('/\s+/', $query))->filter()->take(8);
                $builder->where(function ($search) use ($terms): void {
                    foreach ($terms as $term) {
                        $search->where(function ($match) use ($term): void {
                            $like = '%'.addcslashes($term, '%_').'%';
                            $match->where('title', 'like', $like)->orWhere('summary', 'like', $like)
                                ->orWhere('body', 'like', $like)->orWhere('keywords', 'like', $like);
                        });
                    }
                });
            })->orderBy('sort_order')->orderBy('title')->get();

        if ($query !== '' && $user) {
            DB::table('customer_help_searches')->insert([
                'user_id' => $user->id, 'query' => mb_substr($query, 0, 255),
                'result_count' => $articles->count(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $articles;
    }

    public function feedback(CustomerHelpArticle $article, User $user, bool $helpful): void
    {
        abort_unless($article->is_published, 404);
        DB::table('customer_help_article_feedback')->updateOrInsert(
            ['customer_help_article_id' => $article->id, 'user_id' => $user->id],
            ['helpful' => $helpful, 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
