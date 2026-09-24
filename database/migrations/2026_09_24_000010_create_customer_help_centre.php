<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_help_articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category', 100)->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('body');
            $table->text('keywords')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('customer_help_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query');
            $table->unsignedInteger('result_count')->default(0);
            $table->timestamps();
        });
        Schema::create('customer_help_article_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_help_article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamps();
            $table->unique(['customer_help_article_id', 'user_id'], 'help_article_user_feedback_unique');
        });

        $articles = [
            ['Getting started', 'How do I find my media?', 'Start in My Media. Add a competition using its event login code, or add your studio in My Profile to find concert media.', "Competition media: add your dancer first, then select the event and enter its event login code.\n\nConcert photos: add and verify your studio, then open the studio from My Media.\n\nConcert video: select the concert year and enter the supplied video login code.\n\nThis wording is provisional and will be reviewed before launch.", 'find photos videos add event studio login code'],
            ['Gallery access', 'Why do I need a gallery login code?', 'Gallery codes protect unreleased and customer media while still allowing simple guest access.', "Choose the correct event before entering its login code. Competition media codes and gallery login codes serve different purposes. Codes are supplied by DancePro or the event organiser.\n\nIf a saved event stops opening, confirm that you selected the correct event and current code.", 'code password access unlock event'],
            ['Profiles', 'How do dancers and studios work?', 'Saved dancers drive routine recommendations, while verified studios connect you with concert and studio media.', "Add each dancer name and any programme-name variations in My Profile. Add your studio separately and verify it with the studio gallery code.\n\nRecommendations only show media that is ready and matches your saved details.", 'profile dancer alias studio verify recommendations'],
            ['Shopping', 'What is the difference between favourites and the cart?', 'Favourites save items for later; the cart contains products you intend to purchase.', "Use the heart to save a photo or product to Favourites. Use the cart icon to add it to your order. Purchased items are removed from both lists after checkout.\n\nAn event may contain individual photos, all-routine photos, video and package products.", 'favourite heart cart product package'],
            ['Pricing', 'How do prices, discounts, credits and GST work?', 'Your cart applies eligible discounts and account credit before calculating the displayed paid amount and included GST.', "Product prices come from the price list assigned to the gallery or album. A valid discount code is applied before bulk-discount eligibility is checked. Account credit is then applied.\n\nGST is calculated on the amount actually paid after discounts and credit. Studio-principal $1 images do not qualify for bulk discounts.", 'price discount code credit gst bulk principal'],
            ['Orders', 'Where can I see my order?', 'Open My orders from the My Media section to see payment, readiness and download status.', "My orders lists each purchase and separates photo and video readiness. Some package video may still be preparing after the photo component becomes available.\n\nEach media type has its own download period beginning when that media becomes ready.", 'order receipt payment preparing ready status'],
            ['Downloads', 'How long are downloads available?', 'Photo and video access periods are shown separately beside each order item.', "Photo downloads are normally available for 12 months from readiness. Video downloads are normally available for 60 days from readiness. The dates shown in My orders are the dates that apply to your purchase.\n\nDownload and safely back up purchased files before the displayed end date.", 'download expiry 12 months 60 days backup'],
            ['Downloads', 'What if my download period has ended?', 'Expired media can be sent to DancePro for a reviewed access extension.', "Open My orders and choose Request reactivation beside the expired media. Explain what you need, then follow the request in Media Help.\n\nAn approved extension affects only the selected photo or video entitlement and displays its new end date.", 'expired reactivate extension ended'],
            ['Media quality', 'Why are gallery photos watermarked?', 'Gallery previews are protected samples; purchased downloads use the original purchased media.', "Watermarks protect preview images before purchase. They do not indicate a fault with the original file. Use the purchased download from My orders or My Media after checkout.\n\nDo not screenshot gallery previews as a substitute for purchased media.", 'watermark preview original quality screenshot'],
            ['Account access', 'I cannot sign in or see expected media', 'Check the email account, verified profile details and event access before contacting DancePro.', "Confirm that you signed in with the same email used for your order or gallery access. Check that your email is verified, your dancer and studio details are current, and the event has been added to My Media.\n\nIf media is still missing, include the event and order number in a help request.", 'sign in email missing media account verification'],
        ];
        foreach ($articles as $index => [$category, $title, $summary, $body, $keywords]) {
            DB::table('customer_help_articles')->insert([
                'uuid' => (string) Str::uuid(), 'category' => $category, 'title' => $title,
                'slug' => Str::slug($title), 'summary' => $summary, 'body' => $body,
                'keywords' => $keywords, 'sort_order' => $index + 1, 'is_published' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_help_article_feedback');
        Schema::dropIfExists('customer_help_searches');
        Schema::dropIfExists('customer_help_articles');
    }
};
